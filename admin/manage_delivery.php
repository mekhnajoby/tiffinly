<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once('../config/db_connect.php');

// Get admin data from session
$admin_name = $_SESSION['name'];
$admin_email = $_SESSION['email'];

// Fetch delivery history (delivered or cancelled)
$history_sql = "
    SELECT
        s.subscription_id, s.start_date, s.end_date, s.schedule, s.plan_id, s.dietary_preference,
        u.name as user_name, u.phone, u.user_id,
        p.name as partner_name,
        p.phone as partner_phone,
        mp.plan_name,
        da.assignment_id,
        da.status as delivery_status,
        da.meal_type,
        da.delivery_date,
        da.assigned_at,
        dp.time_slot,
        a.address_type
    FROM subscriptions s
    JOIN users u ON s.user_id = u.user_id
    JOIN meal_plans mp ON s.plan_id = mp.plan_id
    LEFT JOIN delivery_assignments da ON s.subscription_id = da.subscription_id
    LEFT JOIN users p ON da.partner_id = p.user_id
    LEFT JOIN delivery_preferences dp ON s.user_id = dp.user_id AND LOWER(da.meal_type) = LOWER(dp.meal_type)
    LEFT JOIN addresses a ON dp.address_id = a.address_id
    WHERE s.status = 'active'
    ORDER BY s.subscription_id DESC, da.delivery_date DESC, FIELD(da.meal_type, 'Breakfast', 'Lunch', 'Dinner')
";

$history_res = $conn->query($history_sql);

$history = [];
while ($row = $history_res->fetch_assoc()) {
    $sid = $row['subscription_id'];
    if (!isset($history[$sid])) {
        $history[$sid] = [
            'details' => $row,
            'items_by_date' => []
        ];
    }
    if ($row['assignment_id']) {
        $date = $row['delivery_date'] ?? 'Unknown Date';
        if (!isset($history[$sid]['items_by_date'][$date])) {
            $history[$sid]['items_by_date'][$date] = [];
        }
        $history[$sid]['items_by_date'][$date][] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Delivery - Tiffinly</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #2C7A7B;
            --secondary-color: #F39C12;
            --dark-color: #2C3E50;
            --light-color: #F9F9F9;
            --light-gray: #e9ecef;
            --border-radius: 10px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.12);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
        }

        body { font-family: 'Poppins', sans-serif; margin: 0; padding: 0; background-color: var(--light-color); color: var(--dark-color); }

        .dashboard-container { display: grid; grid-template-columns: 280px 1fr; min-height: 100vh; height: 100vh; overflow: hidden; }
        .sidebar { background-color: #fff; box-shadow: var(--shadow-md); padding: 30px 0; height: 100vh; overflow-y: auto; position: sticky; top: 0; }
        .sidebar-header { font-weight: 700; font-size: 24px; padding: 0 25px 15px; border-bottom: 1px solid #f0f0f0; text-align: center; color: #2C3E50; }
        .admin-profile { display: flex; align-items: center; padding: 20px 25px; border-bottom: 1px solid #f0f0f0; margin-bottom: 15px; }
        .admin-avatar { width: 50px; height: 50px; border-radius: 50%; background-color: #F39C12; display: flex; align-items: center; justify-content: center; margin-right: 15px; font-size: 20px; font-weight: 600; color: white; }
        .admin-info h4 { margin: 0; font-size: 16px; }
        .admin-info p { margin: 3px 0 0; font-size: 13px; opacity: 0.8; }
        .menu-category { color: var(--primary-color); font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; padding: 12px 25px; margin-top: 15px; }
        .menu-item { padding: 12px 25px; display: flex; align-items: center; color: var(--dark-color); text-decoration: none; transition: all .2s; font-size: 15px; border-left: 3px solid transparent; }
        .menu-item i { margin-right: 12px; font-size: 16px; width: 20px; text-align: center; }
        .menu-item:hover, .menu-item.active { background-color: #F0F7F7; color: var(--primary-color); border-left: 3px solid var(--primary-color); transform: translateX(5px); }

        .main-content { padding: 30px; background-color: var(--light-color); min-height: 100vh; overflow-y: auto; display: flex; flex-direction: column; }
        .content-wrapper { flex: 1; display: flex; flex-direction: column; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { font-size: 28px; margin: 0; position: relative; display: inline-block; }
        .header h1:after { content: ''; position: absolute; bottom: -5px; left: 0; width: 50px; height: 3px; background-color: var(--primary-color); border-radius: 3px; transition: width .3s ease; }
        .header h1:hover:after { width: 100%; }
        .header p { margin: 5px 0 0; color: #777; font-size: 16px; }

        .delivery-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(44,122,123,0.09);
            padding: 28px 32px;
            margin-bottom: 25px;
            border: 1.5px solid var(--light-gray);
        }
        .card-header { display: flex; align-items: center; gap: 18px; margin-bottom: 20px; }
        .card-header .icon { font-size: 22px; width: 44px; height: 44px; background: #2C7A7B; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .card-header .title { font-size: 18px; font-weight: 600; color: var(--primary-color); }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 10px; }
        .info-item .label { color: #888; font-size: 14px; }
        .info-item .value { font-weight: 500; }

        .badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .badge.delivered { background: #e8f5e9; color: #2e7d32; }
        .badge.cancelled { background: #fdecea; color: #c62828; }

        .date-scroller {
            overflow-x: auto;
            white-space: nowrap;
            padding: 10px 0;
        }

        .date-scroller ul {
            padding: 0;
            margin: 0;
            display: inline-block;
        }

        .date-scroller li {
            display: inline-block;
            margin-right: 10px;
        }

        .date-button {
            background: #f0f0f0;
            border: 1px solid #ddd;
            color: #333;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .date-button.selected {
            background: var(--primary-color);
            color: #fff;
            border-color: var(--primary-color);
        }

        .card-footer {
            display: flex;
            justify-content: flex-end;
            margin-top: 15px;
        }

        footer { text-align: center; padding: 20px; margin-top: auto; color: #777; font-size: 13px; border-top: 1px solid #eee; width: 100%; }
        @media (max-width: 992px) { .dashboard-container { grid-template-columns: 1fr; } .sidebar { display: none; } }
    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="sidebar">
        <div class="sidebar-header"><i class="fas fa-utensils"></i>&nbsp; Tiffinly</div>
        <div class="admin-profile">
            <div class="admin-avatar"><?php echo strtoupper(substr($admin_name, 0, 1)); ?></div>
            <div class="admin-info">
                <h4><?php echo htmlspecialchars($admin_name); ?></h4>
                <p><?php echo htmlspecialchars($admin_email); ?></p>
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
            <a href="manage_subscriptions.php" class="menu-item"><i class="fas fa-calendar-check"></i> Subscriptions Data</a>
            <div class="menu-category">Delivery & Partner Management</div>
            <a href="manage_partners.php" class="menu-item"><i class="fas fa-hands-helping"></i> Manage Partners</a>
            <a href="manage_delivery.php" class="menu-item active"><i class="fas fa-truck"></i>  Delivery Data</a>
            <a href="pending_delivery.php" class="menu-item"><i class="fas fa-clock"></i> Pending Delivery</a>
            <div class="menu-category">Inquiry & Feedback Management</div>
            <a href="manage_inquiries.php" class="menu-item"><i class="fas fa-users"></i> Manage Inquiries</a>
            <a href="view_feedback.php" class="menu-item"><i class="fas fa-comment-alt"></i> View Feedback</a>
            <div style="margin-top: 30px;"><a href="../logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
        </div>
    </div>
    <div class="main-content">
        <div class="content-wrapper">
            <div class="header">
                <div>
                    <h1> Delivery Data</h1>
                    <p>Review completed and pending deliveries for all subscriptions.</p>
                </div>
            </div>

            <?php if (empty($history)): ?>
                <div class="delivery-card" style="text-align:center;color:#888;">
                    <i class="fas fa-box-open"></i> No active subscriptions found.
                </div>
            <?php else: ?>
                <?php foreach ($history as $sub_id => $data): ?>
                    <div class="delivery-card">
                        <div class="card-header">
                            <div class="icon"><i class="fas fa-box"></i></div>
                            <div>
                                <div class="title">Subscription #<?php echo (int)$sub_id; ?></div>
                                <div style="font-size: 14px; color: #888;">
                                    <?php echo date('d M Y', strtotime($data['details']['start_date'])); ?> to <?php echo date('d M Y', strtotime($data['details']['end_date'])); ?>
                                </div>
                            </div>
                        </div>

                        <div class="info-grid">
                            <div class="info-item">
                                <div class="label"><i class="fas fa-user"></i> Customer</div>
                                <div class="value"><?php echo htmlspecialchars($data['details']['user_name']); ?> (<?php echo htmlspecialchars($data['details']['phone']); ?>)</div>
                            </div>
                            <div class="info-item">
                                <div class="label"><i class="fas fa-truck"></i> Partner</div>
                                <div class="value"><?php echo htmlspecialchars($data['details']['partner_name'] ?? 'Not Assigned'); ?> (<?php echo htmlspecialchars($data['details']['partner_phone'] ?? 'N/A'); ?>)</div>
                            </div>
                            <div class="info-item">
                                <div class="label"><i class="fas fa-calendar-alt"></i> Plan & Schedule</div>
                                <div class="value"><?php echo htmlspecialchars($data['details']['plan_name']); ?>, <?php echo htmlspecialchars($data['details']['schedule']); ?></div>
                            </div>
                        </div>

                        <div class="collapsible-details" id="details-<?php echo (int)$sub_id; ?>" style="display:none; margin-top: 20px; border-top: 1px dashed #eee; padding-top: 20px;">
                            <?php if (empty($data['items_by_date'])): ?>
                                <div style="text-align:center; color:#888; margin-top: 20px;">No deliveries assigned yet.</div>
                            <?php else: ?>
                                <div class="date-scroller">
                                    <ul>
                                        <?php
                                        $period = new DatePeriod(
                                            new DateTime($data['details']['start_date']),
                                            new DateInterval('P1D'),
                                            new DateTime($data['details']['end_date'])
                                        );
                                        foreach ($period as $key => $value) {
                                            $day_of_week = $value->format('N');
                                            $schedule = $data['details']['schedule'];
                                            $should_display = false;
                                            switch ($schedule) {
                                                case 'Weekdays':
                                                    if ($day_of_week <= 5) $should_display = true;
                                                    break;
                                                case 'Extended':
                                                    if ($day_of_week <= 6) $should_display = true;
                                                    break;
                                                case 'Full Week':
                                                    $should_display = true;
                                                    break;
                                            }
                                            if ($should_display) {
                                                echo '<li><button class="date-button" data-subscription-id="' . (int)$sub_id . '" data-date="' . $value->format('Y-m-d') . '">' . $value->format('d M') . '</button></li>';
                                            }
                                        }
                                        ?>
                                    </ul>
                                </div>

                                <div class="details-panel" data-subscription-id="<?php echo (int)$sub_id; ?>" style="margin-top:12px;">
                                    <?php foreach ($data['items_by_date'] as $date => $items): ?>
                                        <div class="delivery-day-group" data-date="<?php echo $date; ?>" style="display:none; margin-bottom: 15px; padding: 10px; border: 1px solid #f0f0f0; border-radius: 8px;">
                                            <h4 class="delivery-date-header" style="margin: 0 0 10px 0; font-size: 15px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 5px;">
                                                <i class="far fa-calendar-alt"></i> <?php echo date('d M Y, l', strtotime($date)); ?>
                                            </h4>
                                            <?php foreach ($items as $item): ?>
                                            <div style="display:flex; align-items:center; gap:12px; padding:8px; border-radius:6px; margin-bottom:6px; background:#fdfdfd;">
                                                <div class="badge <?php echo $item['delivery_status']==='delivered' ? 'delivered' : 'cancelled'; ?>">
                                                    <?php echo ucfirst(str_replace('_',' ', $item['delivery_status'])); ?>
                                                </div>
                                                <div style="font-weight:600; color:#2C7A7B; min-width:100px; flex-basis: 120px;">
                                                    <?php echo ucfirst($item['meal_type']); ?>
                                                </div>
                                                <div style="color:#555; font-size: 13px; display: flex; align-items: center; gap: 6px;">
                                                    <i class="fas fa-clock"></i>
                                                    <span><?php echo htmlspecialchars($item['time_slot'] ?? 'N/A'); ?></span>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <button class="date-button toggle-details" data-target="details-<?php echo (int)$sub_id; ?>">
                                <i class="fas fa-chevron-down"></i> View More
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <footer>
                &copy; <?php echo date('Y'); ?> Tiffinly. All rights reserved.
            </footer>
        </div>
    </div>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.toggle-details').forEach(function(btn){
        btn.addEventListener('click', function(){
            const targetId = this.getAttribute('data-target');
            const panel = document.getElementById(targetId);
            if (panel.style.display === 'none' || panel.style.display === '') {
                panel.style.display = 'block';
                this.innerHTML = '<i class="fas fa-chevron-up"></i> Show Less';
            } else {
                panel.style.display = 'none';
                this.innerHTML = '<i class="fas fa-chevron-down"></i> View More';
            }
        });
    });

    document.querySelectorAll('.date-button').forEach(function(btn){
      btn.addEventListener('click', function(){
        var subId = this.getAttribute('data-subscription-id');
        var selectedDate = this.getAttribute('data-date');

        // Handle selected state for buttons
        document.querySelectorAll('.date-button[data-subscription-id="' + subId + '"]').forEach(function(otherBtn) {
            otherBtn.classList.remove('selected');
        });
        this.classList.add('selected');

        // Show/hide details
        var allDetails = document.querySelectorAll('.details-panel[data-subscription-id="' + subId + '"] .delivery-day-group');
        allDetails.forEach(function(detail) {
            detail.style.display = 'none';
        });

        var selectedDetail = document.querySelector('.details-panel[data-subscription-id="' + subId + '"] .delivery-day-group[data-date="' + selectedDate + '"]');
        if (selectedDetail) {
            selectedDetail.style.display = 'block';
        }
      });
    });
  });
</script>
</body>
</html>