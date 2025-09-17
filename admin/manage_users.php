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

$alert = null; $alert_type = 'success';

// Helpers
function to_int($v) { return intval($v ?? 0); }
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Removal of users is disabled per admin request.

$selected_user_id = to_int($_GET['user_id'] ?? 0);

// Fetch users (customer role)
$users = [];
$res = $db->query("SELECT user_id, name, email FROM users WHERE role = 'user' ORDER BY user_id ASC");
while ($row = $res->fetch_assoc()) { $users[] = $row; }
$res->close();

// If a user is selected, fetch details
$user_details = null;
$subscriptions = [];
$payments = [];
if ($selected_user_id > 0) {
    $stmt = $db->prepare("SELECT user_id, name, email, phone FROM users WHERE user_id = ? AND role = 'user'");
    $stmt->bind_param('i', $selected_user_id);
    $stmt->execute();
    $user_details = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user_details) {
        // Subscriptions with plan details and a single assigned partner per subscription (avoid duplicates)
        $sql = "
            SELECT s.subscription_id, s.plan_id, s.schedule, s.start_date, s.end_date, s.dietary_preference,
                   s.status, s.payment_status,
                   mp.plan_name,
                   p.user_id AS partner_id, p.name AS partner_name, p.phone AS partner_phone
            FROM subscriptions s
            LEFT JOIN meal_plans mp ON s.plan_id = mp.plan_id
            LEFT JOIN (
                SELECT da.subscription_id, da.partner_id
                FROM delivery_assignments da
                INNER JOIN (
                    SELECT subscription_id, MIN(assignment_id) AS min_assignment_id
                    FROM delivery_assignments
                    GROUP BY subscription_id
                ) x ON x.subscription_id = da.subscription_id AND x.min_assignment_id = da.assignment_id
            ) dap ON s.subscription_id = dap.subscription_id
            LEFT JOIN users p ON dap.partner_id = p.user_id
            WHERE s.user_id = ?
            ORDER BY s.subscription_id DESC";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $selected_user_id);
        $stmt->execute();
        $rs = $stmt->get_result();
        while ($r = $rs->fetch_assoc()) { $subscriptions[] = $r; }
        $stmt->close();

        // Per admin request, do not fetch or display specific meal types or meal lists for subscriptions.

        // Payments
        $stmt = $db->prepare("SELECT payment_id, amount, payment_method AS method, payment_status, created_at FROM payments WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->bind_param('i', $selected_user_id);
        $stmt->execute();
        $rs = $stmt->get_result();
        while ($r = $rs->fetch_assoc()) { $payments[] = $r; }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Tiffinly - Manage Users</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Reuse Admin theme from admin_dashboard.php */
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
        .btn-primary{background:var(--primary-color);color:#fff;} .btn-danger{background:#e74c3c;color:#fff;} .btn-outline{background:#fff;border:1px solid #ddd;color:#333;} 
        .grid{display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:16px;} 
        .muted{color:#666;font-size:13px;} .badge{display:inline-block;background:#e0e7ff;color:#4f46e5;font-size:12px;border-radius:10px;padding:2px 8px;margin-left:6px;} 
        footer{text-align:center;padding:12px;margin-top:20px;color:#777;font-size:13px;border-top:1px solid #eee;} 
        .actions{display:flex;gap:8px;}
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
            <a href="manage_users.php" class="menu-item active"><i class="fas fa-users"></i> Users Data</a>
            <a href="manage_subscriptions.php" class="menu-item"><i class="fas fa-calendar-check"></i>Subscriptions Data</a>
            <div class="menu-category">Delivery & Partner Management</div>
            <a href="manage_partners.php" class="menu-item"><i class="fas fa-hands-helping"></i> Manage Partners</a>
            <a href="manage_delivery.php" class="menu-item"><i class="fas fa-truck"></i> Delivery Data</a>
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
            <h1>Users Data</h1>
            <div class="muted">View customer details, subscriptions, delivery partner and payments.</div>
        </div>

        <?php if ($alert): ?>
            <div class="content-card" style="border-left:4px solid <?php echo $alert_type==='success' ? '#27ae60' : '#e74c3c'; ?>;">
                <?php echo h($alert); ?>
            </div>
        <?php endif; ?>

        <div class="content-card">
            <h3 style="margin-top:0;">Customers</h3>
            <table>
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="4" class="muted">No customers found.</td></tr>
                <?php else: foreach ($users as $u): ?>
                    <tr>
                        <td>#<?php echo (int)$u['user_id']; ?></td>
                        <td><?php echo h($u['name']); ?></td>
                        <td><?php echo h($u['email']); ?></td>
                        <td class="actions">
                            <a class="btn btn-primary" href="manage_users.php?user_id=<?php echo (int)$u['user_id']; ?>"><i class="fas fa-eye"></i> View Details</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($user_details): ?>
            <div class="content-card">
                <h3 style="margin-top:0;">User Details</h3>
                <div class="user-details-grid">
                    <div class="detail-row">
                        <strong>Name:</strong>
                        <span><?php echo h($user_details['name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <strong>Email:</strong>
                        <span><?php echo h($user_details['email']); ?></span>
                    </div>
                    <div class="detail-row">
                        <strong>Phone:</strong>
                        <span><?php echo h($user_details['phone'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <strong>User ID:</strong>
                        <span>#<?php echo (int)$user_details['user_id']; ?></span>
                    </div>
                </div>
                <style>
                    .user-details-grid {
                        display: grid;
                        grid-template-columns: repeat(2, 1fr);
                        gap: 15px;
                    }
                    .detail-row {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        padding: 8px 0;
                        border-bottom: 1px solid #eee;
                    }
                    .detail-row strong {
                        min-width: 100px;
                        color: #555;
                    }
                    .detail-row span {
                        color: #333;
                    }
                </style>
            </div>

            <div class="content-card">
                <h3 style="margin-top:0;">Subscriptions</h3>
                <?php if (empty($subscriptions)): ?>
                    <div class="muted">No subscriptions found.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Subscription</th>
                                <th>Plan</th>
                                <th>Diet</th>
                                <th>Schedule</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Partner</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subscriptions as $s): 
                                $duration = '';
                                if (!empty($s['start_date']) && !empty($s['end_date'])) {
                                    try {
                                        $sd = new DateTime($s['start_date']);
                                        $ed = new DateTime($s['end_date']);
                                        $days = 0;
                                        $interval = DateInterval::createFromDateString('1 day');
                                        $period = new DatePeriod($sd, $interval, $ed->modify('+1 day'));
                                        $schedule = strtolower($s['schedule'] ?? 'daily');
                                        foreach ($period as $dt) {
                                            $w = (int)$dt->format('N'); // 1=Mon ... 7=Sun
                                            if ($schedule === 'weekdays') {
                                                if ($w >= 1 && $w <= 5) $days++;
                                            } elseif ($schedule === 'extended') {
                                                if ($w >= 1 && $w <= 6) $days++;
                                            } else { // full weeks/daily
                                                $days++;
                                            }
                                        }
                                        $duration = $days . ' day' . ($days != 1 ? 's' : '');
                                    } catch (Throwable $e) { $duration = '—'; }
                                }
                                $partner = $s['partner_name'] ? ($s['partner_name'].' ('.($s['partner_phone'] ?? '—').')') : 'Not Assigned';
                            ?>
                            <tr>
                                <td>#<?php echo (int)$s['subscription_id']; ?></td>
                                <td><?php echo h($s['plan_name'] ?? ('Plan #'.(int)$s['plan_id'])); ?></td>
                                <td><?php echo h(ucfirst($s['dietary_preference'] ?? 'All')); ?></td>
                                <td><?php echo h(ucfirst($s['schedule'] ?? 'daily')); ?></td>
                                <td><?php echo h($duration ?: '—'); ?></td>
                                <td>
                                    <span class="badge"><?php echo h(ucfirst($s['status'] ?? '')); ?></span>
                                </td>
                                <td>
                                    <span class="badge" style="background:<?php echo (strtolower($s['payment_status'] ?? '')==='paid' || strtolower($s['payment_status'] ?? '')==='success' ? '#e8f5e9' : '#fff3e0'); ?>;color:<?php echo (strtolower($s['payment_status'] ?? '')==='paid' || strtolower($s['payment_status'] ?? '')==='success' ? '#2e7d32' : '#ef6c00'); ?>;">
                                        <?php echo h(ucfirst($s['payment_status'] ?? '')); ?>
                                    </span>
                                </td>
                                <td><?php echo h($partner); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="content-card">
                <h3 style="margin-top:0;">Payments</h3>
                <?php if (empty($payments)): ?>
                    <div class="muted">No payments found.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Payment ID</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td>#<?php echo (int)$p['payment_id']; ?></td>
                                    <td><?php echo h(date('d M Y H:i', strtotime($p['created_at']))); ?></td>
                                    <td>₹<?php echo number_format((float)$p['amount'], 2); ?></td>
                                    <td><?php echo h(ucfirst($p['method'] ?? '')); ?></td>
                                    <td><span class="badge" style="background:<?php echo ($p['payment_status']==='success'?'#e8f5e9':'#fff3e0'); ?>;color:<?php echo ($p['payment_status']==='success'?'#2e7d32':'#ef6c00'); ?>;"><?php echo h(ucfirst($p['payment_status'] ?? '')); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
