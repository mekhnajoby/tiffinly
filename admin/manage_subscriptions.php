<?php
session_start();
// Admin auth
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// DB connection
$db = new mysqli('localhost', 'root', '', 'tiffinly');
if ($db->connect_error) { die('Connection failed: ' . $db->connect_error); }

$admin_name = $_SESSION['name'] ?? 'Admin';
$admin_email = $_SESSION['email'] ?? '';

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function to_int($v) { return intval($v ?? 0); }

// Helpers to compute duration (days) and expected deliveries (meals)
function norm_sched($s) {
    $s = strtolower(trim((string)$s));
    $map = [
        'daily' => 'daily', 'everyday' => 'daily', 'all' => 'daily', 'all days' => 'daily',
        'fullweek' => 'fullweek', 'full week' => 'fullweek', 'full_week' => 'fullweek',
        'extended' => 'extended',
        'weekday' => 'weekdays', 'weekdays' => 'weekdays', 'mon-fri' => 'weekdays', 'mon to fri' => 'weekdays',
        'weekend' => 'weekends', 'weekends' => 'weekends', 'sat-sun' => 'weekends', 'sat & sun' => 'weekends'
    ];
    return $map[$s] ?? 'daily';
}
function count_days_for_range($start_date, $end_date, $schedule) {
    if (empty($start_date) || empty($end_date)) return 0;
    try {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
    } catch (Exception $e) { return 0; }
    if ($end < $start) return 0;
    $norm = norm_sched($schedule);
    $count = 0; $cur = clone $start;
    while ($cur <= $end) {
        $dow = (int)$cur->format('N');
        $ok = (
            $norm === 'daily' ||
            $norm === 'fullweek' ||
            ($norm === 'extended' && $dow >= 1 && $dow <= 6) ||
            ($norm === 'weekdays' && $dow >= 1 && $dow <= 5) ||
            ($norm === 'weekends' && $dow >= 6)
        );
        if ($ok) $count++;
        $cur->modify('+1 day');
    }
    return $count;
}

$selected_subscription_id = to_int($_GET['subscription_id'] ?? 0);

// Overview list: one row per subscription
$subs = [];
$sql = "
    SELECT s.subscription_id, s.user_id, s.plan_id, s.schedule, s.start_date, s.end_date, s.status, s.payment_status,
           mp.plan_name, mp.plan_type
    FROM subscriptions s
    LEFT JOIN meal_plans mp ON s.plan_id = mp.plan_id
    ORDER BY s.subscription_id DESC";
$res = $db->query($sql);
while ($row = $res->fetch_assoc()) { $subs[] = $row; }
$res->close();

// Details for a selected subscription
$details = null; $menu = []; $buyer = null; $partner = null; $price = [];
if ($selected_subscription_id > 0) {
    // subscription + plan
    $stmt = $db->prepare("SELECT s.*, mp.plan_name, mp.plan_type, mp.base_price FROM subscriptions s LEFT JOIN meal_plans mp ON s.plan_id = mp.plan_id WHERE s.subscription_id = ?");
    $stmt->bind_param('i', $selected_subscription_id);
    $stmt->execute();
    $details = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($details) {
        // purchaser
        $stmt = $db->prepare("SELECT user_id, name, phone FROM users WHERE user_id = ?");
        $stmt->bind_param('i', $details['user_id']);
        $stmt->execute();
        $buyer = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // partner (pick earliest assignment)
        $sqlPartner = "
            SELECT p.user_id AS partner_id, p.name AS partner_name, p.phone AS partner_phone
            FROM (
                SELECT da.subscription_id, da.partner_id
                FROM delivery_assignments da
                INNER JOIN (
                    SELECT subscription_id, MIN(assignment_id) AS min_assignment_id
                    FROM delivery_assignments
                    WHERE subscription_id = ?
                    GROUP BY subscription_id
                ) x ON x.subscription_id = da.subscription_id AND x.min_assignment_id = da.assignment_id
            ) dap
            LEFT JOIN users p ON dap.partner_id = p.user_id";
        $stmt = $db->prepare($sqlPartner);
        $stmt->bind_param('i', $selected_subscription_id);
        $stmt->execute();
        $partner = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // price details
        $price = [
            'base_price' => (float)($details['base_price'] ?? 0),
            'total_price' => (float)($details['total_price'] ?? 0),
            'payment_status' => $details['payment_status'] ?? ''
        ];

        // Get the user's selected meals for this subscription
        $menu = [];
        $q = "
            SELECT 
                UPPER(day_of_week) as day_of_week, 
                meal_type, 
                meal_name
            FROM subscription_meals 
            WHERE subscription_id = ? 
            ORDER BY 
                FIELD(day_of_week, 'MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY','SUNDAY'), 
                FIELD(meal_type, 'Breakfast', 'Lunch', 'Dinner')";
        $stmt = $db->prepare($q);
        $stmt->bind_param('i', $selected_subscription_id);
        $stmt->execute();
        $rs = $stmt->get_result();
        
        // Get all selected meals
        while ($r = $rs->fetch_assoc()) {
            $menu[] = [
                'day_of_week' => $r['day_of_week'],
                'meal_type' => $r['meal_type'],
                'meal_name' => $r['meal_name']
            ];
        }
        $stmt->close();
        
        // If no specific meals found for this subscription, fall back to plan meals with dietary preference
        if (empty($menu)) {
            $diet = strtolower(trim($details['dietary_preference'] ?? ''));
            $q = "
                SELECT 
                    UPPER(pm.day_of_week) as day_of_week, 
                    pm.meal_type, 
                    m.meal_name, 
                    mc.option_type
                FROM plan_meals pm
                JOIN meals m ON pm.meal_id = m.meal_id
                JOIN meal_categories mc ON m.category_id = mc.category_id
                WHERE pm.plan_id = ? 
                  AND pm.meal_id > 0 
                  AND m.meal_id > 0 
                  AND m.is_active = 1
                  AND mc.meal_type = pm.meal_type 
                  AND LOWER(mc.slot) = LOWER(pm.meal_type)";
            
            if ($diet !== '' && $diet !== 'all' && $diet === 'veg') { 
                $q .= " AND LOWER(mc.option_type) = 'veg' "; 
            }
            
            $q .= " ORDER BY 
                FIELD(pm.day_of_week, 'MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY','SUNDAY'), 
                FIELD(pm.meal_type, 'Breakfast', 'Lunch', 'Dinner')";
                
            $stmt = $db->prepare($q);
            $stmt->bind_param('i', $details['plan_id']);
            $stmt->execute();
            $rs = $stmt->get_result();
            
            while ($r = $rs->fetch_assoc()) {
                $menu[] = [
                    'day_of_week' => $r['day_of_week'],
                    'meal_type' => $r['meal_type'],
                    'meal_name' => $r['meal_name']
                ];
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Tiffinly - Manage Subscriptions</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root { --primary-color:#2C7A7B; --secondary-color:#F39C12; --light-color:#F9F9F9; --dark-color:#2C3E50; --shadow-md:0 4px 6px rgba(0,0,0,0.1); --shadow-sm:0 1px 3px rgba(0,0,0,0.12); --transition-medium: all .3s ease; --transition-fast: all .2s ease;}
        @keyframes fadeIn { from { opacity:0; transform: translateY(10px);} to { opacity:1; transform: translateY(0);} }
        @keyframes slideInLeft { from { transform: translateX(-20px); opacity:0;} to { transform: translateX(0); opacity:1;} }
        @keyframes slideInRight { from { transform: translateX(20px); opacity:0;} to { transform: translateX(0); opacity:1;} }
        body{font-family:'Poppins',sans-serif;margin:0;padding:0;background:var(--light-color);color:var(--dark-color);} 
        .dashboard-container{display:grid;grid-template-columns:280px 1fr;min-height:100vh;height:100vh;overflow:hidden;} 
        .sidebar{background:#fff;box-shadow:var(--shadow-md);padding:30px 0;position:sticky;top:0;height:100vh;overflow-y:auto;animation: slideInLeft .6s ease-out;} 
        .sidebar-header{font-weight:700;font-size:24px;padding:0 25px 15px;border-bottom:1px solid #f0f0f0;text-align:center;color:#2C3E50;animation: fadeIn .8s ease-out;} 
        .admin-profile{display:flex;align-items:center;padding:20px 25px;border-bottom:1px solid #f0f0f0;margin-bottom:15px;} 
        .admin-avatar{width:50px;height:50px;border-radius:50%;background:#F39C12;display:flex;align-items:center;justify-content:center;margin-right:15px;color:#fff;font-weight:600;} 
        .admin-info h4{margin:0;font-size:16px;} .admin-info p{margin:3px 0 0;font-size:13px;opacity:.8;} 
        .sidebar-menu{padding:15px 0;} .menu-category{color:var(--primary-color);font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;padding:12px 25px;margin-top:15px;animation: fadeIn .6s ease-out;} 
        .menu-item{padding:12px 25px;display:flex;align-items:center;color:var(--dark-color);text-decoration:none;font-size:15px;border-left:3px solid transparent;position:relative;overflow:hidden;} 
        .menu-item i{margin-right:12px;width:20px;text-align:center;transition: var(--transition-fast);} .menu-item.active,.menu-item:hover{background:#F0F7F7;color:var(--primary-color);border-left-color:var(--primary-color);} 
        .menu-item:before{content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;background:linear-gradient(90deg,transparent,rgba(44,122,123,.1),transparent);transition: var(--transition-medium);} 
        .menu-item:hover:before{left:100%;}
        .main-content{padding:30px;background:var(--light-color);height:100vh;overflow-y:auto;animation: fadeIn .8s ease-out;} 
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;animation: slideInRight .6s ease-out;} 
        .header h1{margin:0;font-size:26px;position:relative;display:inline-block;} 
        .header h1:after{content:'';position:absolute;bottom:-5px;left:0;width:50px;height:3px;background:var(--primary-color);border-radius:3px;transition: var(--transition-medium);} 
        .header:hover h1:after{width:100%;} 
        .content-card{background:#fff;border-radius:12px;padding:20px;box-shadow:var(--shadow-sm);margin-bottom:20px;transition: var(--transition-medium);} 
        .content-card:hover{box-shadow:0 10px 25px rgba(0,0,0,0.1);transform: translateY(-3px);} 
        table{width:100%;border-collapse:collapse;} th,td{padding:10px;border-bottom:1px solid #eee;text-align:left;font-size:14px;} th{background:#f8f9fa;font-weight:600;} 
        .btn{display:inline-block;padding:8px 12px;border-radius:6px;text-decoration:none;font-size:13px;border:0;cursor:pointer;} 
        .btn-primary{background:var(--primary-color);color:#fff;} .btn-outline{background:#fff;border:1px solid #ddd;color:#333;} 
        .grid{display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:16px;} 
        .muted{color:#666;font-size:13px;} .badge{display:inline-block;background:#e0e7ff;color:#4f46e5;font-size:12px;border-radius:10px;padding:2px 8px;margin-left:6px;} 
        footer{text-align:center;padding:12px;margin-top:20px;color:#777;font-size:13px;border-top:1px solid #eee;} 
    </style>
</head>
<body>
<div class="dashboard-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header"><i class="fas fa-utensils"></i>&nbsp; Tiffinly</div>
        <div class="admin-profile">
            <div class="admin-avatar"><?php echo strtoupper(substr($admin_name,0,1)); ?></div>
            <div class="admin-info">
                <h4><?php echo h($admin_name); ?></h4>
                <p><?php echo h($admin_email); ?></p>
            </div>
        </div>
        <div class="sidebar-menu">
            <div class="menu-category">Dashboard & Overview</div>
            <a href="admin_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <div class="menu-category">Meal Management</div>
            <a href="manage_menu.php" class="menu-item"><i class="fas fa-utensils"></i> Manage Menu</a>
            <a href="manage_plans.php" class="menu-item"><i class="fas fa-box"></i> Manage Plans</a>
            <a href="manage_popular_meals.php" class="menu-item"><i class="fas fa-star"></i> Manage Popular Meals</a>
            <div class="menu-category">User & Subscriptions</div>
            <a href="manage_users.php" class="menu-item"><i class="fas fa-users"></i> Users Data</a>
            <a href="manage_subscriptions.php" class="menu-item active"><i class="fas fa-calendar-check"></i> Subscriptions Data</a>
            <div class="menu-category">Delivery & Partner Management</div>
            <a href="manage_partners.php" class="menu-item"><i class="fas fa-hands-helping"></i> Manage Partners</a>
            <a href="manage_delivery.php" class="menu-item"><i class="fas fa-truck"></i>  Delivery Data</a>
            <a href="pending_delivery.php" class="menu-item"><i class="fas fa-clock"></i> Pending Delivery</a>
            <div class="menu-category">Inquiry & Feedback</div>
            <a href="manage_inquiries.php" class="menu-item"><i class="fas fa-users"></i> Manage Inquiries</a>
            <a href="view_feedback.php" class="menu-item"><i class="fas fa-comment-alt"></i> View Feedback</a>
            <div style="margin-top: 30px;"><a href="../logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Subscriptions Data</h1>
            <div class="muted">Overview of subscriptions with plan type and schedule. View more for full details.</div>
        </div>

        <div class="content-card">
            <h3 style="margin-top:0;">All Subscriptions</h3>
            <table>
                <thead>
                    <tr>
                        <th>Subscription</th>
                        <th>Plan</th>
                        <th>Plan Type</th>
                        <th>Schedule</th>
                        <th>Start - End</th>
                        <th>Duration</th>
                        <th>Expected Meals</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($subs)): ?>
                    <tr><td colspan="8" class="muted">No subscriptions found.</td></tr>
                <?php else: foreach ($subs as $s): ?>
                    <tr>
                        <td>#<?php echo (int)$s['subscription_id']; ?></td>
                        <td><?php echo h($s['plan_name'] ?? ('Plan #'.(int)$s['plan_id'])); ?></td>
                        <td><span class="badge"><?php echo h(ucfirst($s['plan_type'] ?? '')); ?></span></td>
                        <td><?php echo h(ucfirst($s['schedule'])); ?></td>
                        <td><?php echo date('M d, Y', strtotime($s['start_date'])) . ' to ' . date('M d, Y', strtotime($s['end_date'])); ?></td>
                        <?php $days = count_days_for_range($s['start_date'] ?? null, $s['end_date'] ?? null, $s['schedule'] ?? 'daily'); ?>
                        <td><?php echo (int)$days; ?> days</td>
                        <td><?php echo number_format($days * 3); ?> meals</td>
                        <td><span class="badge"><?php echo h(ucfirst($s['status'] ?? '')); ?></span></td>
                        <td>
                            <span class="badge" style="background:<?php echo (strtolower($s['payment_status'] ?? '')==='paid' || strtolower($s['payment_status'] ?? '')==='success' ? '#e8f5e9' : '#fff3e0'); ?>;color:<?php echo (strtolower($s['payment_status'] ?? '')==='paid' || strtolower($s['payment_status'] ?? '')==='success' ? '#2e7d32' : '#ef6c00'); ?>;">
                                <?php echo h(ucfirst($s['payment_status'] ?? '')); ?>
                            </span>
                        </td>
                        <td>
                            <?php $isCurrent = ($selected_subscription_id === (int)$s['subscription_id']); ?>
                            <?php if ($isCurrent): ?>
                                <a class="btn btn-outline" href="manage_subscriptions.php">
                                    <i class="fas fa-eye-slash"></i> View Less
                                </a>
                            <?php else: ?>
                                <a class="btn btn-primary" href="manage_subscriptions.php?subscription_id=<?php echo (int)$s['subscription_id']; ?>">
                                    <i class="fas fa-eye"></i> View More
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($details): ?>
        <div class="content-card">
            <h3 style="margin-top:0;">Subscription #<?php echo (int)$details['subscription_id']; ?> Details</h3>
            <div class="detail-grid">
                <div class="detail-row">
                    <strong>Plan:</strong>
                    <span><?php echo h($details['plan_name'] ?? ('Plan #'.$details['plan_id'])); ?></span>
                </div>
                <div class="detail-row">
                    <strong>Schedule:</strong>
                    <span><?php echo h($details['schedule']); ?></span>
                </div>
                <div class="detail-row">
                    <strong>Start - End:</strong>
                    <span><?php echo date('M d, Y', strtotime($details['start_date'])) . ' to ' . date('M d, Y', strtotime($details['end_date'])); ?></span>
                </div>
                <?php $dets_days = count_days_for_range($details['start_date'] ?? null, $details['end_date'] ?? null, $details['schedule'] ?? 'daily'); ?>
                <div class="detail-row">
                    <strong>Duration:</strong>
                    <span><?php echo (int)$dets_days; ?> days</span>
                </div>
                <div class="detail-row">
                    <strong>Expected Deliveries:</strong>
                    <span><?php echo number_format($dets_days * 3); ?> meals</span>
                </div>
                <div class="detail-row">
                    <strong>Status:</strong>
                    <span class="badge"><?php echo h(ucfirst($details['status'] ?? '')); ?></span>
                </div>
                <div class="detail-row">
                    <strong>Payment:</strong>
                    <span class="badge"><?php echo h(ucfirst($details['payment_status'] ?? '')); ?></span>
                </div>
            </div>
            <style>
                .detail-grid {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 12px;
                }
                .detail-row {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                    padding: 8px 0;
                    border-bottom: 1px solid #f0f0f0;
                }
                .detail-row strong {
                    min-width: 150px;
                    color: #555;
                    font-weight: 500;
                }
                .detail-row span {
                    color: #333;
                }
                .badge {
                    display: inline-block;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 12px;
                    font-weight: 500;
                    text-transform: capitalize;
                }
            </style>
        </div>

        <div class="content-card">
            <h3 style="margin-top:0;">Menu Details</h3>
            <?php if (empty($menu)): ?>
                <div class="muted">No menu details available for this plan.</div>
            <?php else: ?>
            <?php
                // Determine allowed days by schedule for this subscription
                $schedule = strtolower(trim($details['schedule'] ?? 'daily'));
                $allDays = ['MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY','SUNDAY'];
                if ($schedule === 'weekdays' || $schedule === 'weekday') {
                    $allowedDays = ['MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY'];
                } elseif ($schedule === 'weekends' || $schedule === 'weekend') {
                    $allowedDays = ['SATURDAY','SUNDAY'];
                } else {
                    // default to daily
                    $allowedDays = $allDays;
                }

                // Group menu items by day with Breakfast/Lunch/Dinner columns, filtered by allowed days
                $menuByDay = [];
                foreach ($menu as $m) {
                    $day = strtoupper($m['day_of_week']);
                    if (!in_array($day, $allowedDays, true)) { continue; }
                    if (!isset($menuByDay[$day])) {
                        $menuByDay[$day] = ['Breakfast' => '', 'Lunch' => '', 'Dinner' => ''];
                    }
                    $mt = $m['meal_type'];
                    if (isset($menuByDay[$day][$mt])) {
                        $menuByDay[$day][$mt] = trim($menuByDay[$day][$mt] . (empty($menuByDay[$day][$mt]) ? '' : ', ') . $m['meal_name']);
                    }
                }
                // Order only the allowed days in normal order
                $dayOrder = array_values(array_filter($allDays, function($d) use ($allowedDays) { return in_array($d, $allowedDays, true); }));
            ?>
            <table>
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Breakfast</th>
                        <th>Lunch</th>
                        <th>Dinner</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dayOrder as $d): if (!isset($menuByDay[$d])) continue; ?>
                        <tr>
                            <td><?php echo h(ucfirst(strtolower($d))); ?></td>
                            <td><?php echo h($menuByDay[$d]['Breakfast']); ?></td>
                            <td><?php echo h($menuByDay[$d]['Lunch']); ?></td>
                            <td><?php echo h($menuByDay[$d]['Dinner']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="content-card">
            <h3 style="margin-top:0;">Price Details</h3>
            <div class="detail-grid">
                <div class="detail-row">
                    <strong>Base Price (Plan):</strong>
                    <span>₹<?php echo number_format($price['base_price'], 2); ?></span>
                </div>
                <div class="detail-row">
                    <strong>Total Paid/Payable:</strong>
                    <span>₹<?php echo number_format($price['total_price'], 2); ?></span>
                </div>
                <div class="detail-row">
                    <strong>Duration of Days:</strong>
                    <?php $dets_days = count_days_for_range($details['start_date'] ?? null, $details['end_date'] ?? null, $details['schedule'] ?? 'daily'); ?>
                    <span><?php echo (int)$dets_days; ?> days</span>
                </div>
                <div class="detail-row">
                    <strong>Payment Status:</strong>
                    <span class="badge"><?php echo h(ucfirst($price['payment_status'] ?? '')); ?></span>
                </div>
            </div>
        </div>

        <div class="content-card">
            <h3 style="margin-top:0;">Purchased By</h3>
            <div class="detail-grid">
                <div class="detail-row">
                    <strong>User ID:</strong>
                    <span>#<?php echo (int)($buyer['user_id'] ?? 0); ?></span>
                </div>
                <div class="detail-row">
                    <strong>Name:</strong>
                    <span><?php echo h($buyer['name'] ?? ''); ?></span>
                </div>
                <div class="detail-row">
                    <strong>Contact:</strong>
                    <span><?php echo h($buyer['phone'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>

        <div class="content-card">
            <h3 style="margin-top:0;">Delivered By</h3>
            <?php if (empty($partner)): ?>
                <div class="muted">No partner assigned.</div>
            <?php else: ?>
            <div class="grid">
                <div><strong>Partner ID:</strong><br>#<?php echo (int)($partner['partner_id'] ?? 0); ?></div>
                <div><strong>Name:</strong><br><?php echo h($partner['partner_name'] ?? ''); ?></div>
                <div><strong>Contact:</strong><br><?php echo h($partner['partner_phone'] ?? 'N/A'); ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> Tiffinly. All rights reserved.</p>
        </footer>
    </div>
</div>
</body>
</html>
<?php $db->close(); ?>
