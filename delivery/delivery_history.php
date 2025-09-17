<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'delivery') {
    header("Location: ../login.php");
    exit();
}

require_once('../config/db_connect.php');

$partner_id = $_SESSION['user_id'];

// Fetch delivery partner details for sidebar
$user_stmt = $conn->prepare("SELECT name, email FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $partner_id);
$user_stmt->execute();
$user_res = $user_stmt->get_result();
$user = $user_res->fetch_assoc();
$user_stmt->close();

// Fetch delivery history (delivered or cancelled)
$history_sql = "
        SELECT DISTINCT 
                da.assignment_id,
                da.status as delivery_status,
                da.meal_type,
                da.delivery_date,
                da.assigned_at,
                s.subscription_id, s.start_date, s.end_date, s.schedule, s.plan_id, s.dietary_preference, s.status,
                u.name as user_name, u.phone, u.user_id,
                mp.plan_name,
                dp.time_slot,
                a.address_type,
                COALESCE(sm.meal_name, m.meal_name) AS meal_name
        FROM delivery_assignments da
        JOIN subscriptions s ON da.subscription_id = s.subscription_id
        JOIN users u ON s.user_id = u.user_id
        JOIN meal_plans mp ON s.plan_id = mp.plan_id
        LEFT JOIN delivery_preferences dp ON s.user_id = dp.user_id AND LOWER(da.meal_type) = LOWER(dp.meal_type)
        LEFT JOIN addresses a ON dp.address_id = a.address_id
        LEFT JOIN subscription_meals sm ON da.subscription_id = sm.subscription_id AND UPPER(DAYNAME(da.delivery_date)) = sm.day_of_week AND da.meal_type = sm.meal_type
        LEFT JOIN meals m ON da.meal_id = m.meal_id
            WHERE da.partner_id = ?
                AND da.status IN ('delivered', 'cancelled')
                AND da.delivery_date <= CURDATE()
        ORDER BY da.delivery_date DESC, s.subscription_id, FIELD(da.meal_type, 'Breakfast', 'Lunch', 'Dinner')
";

$hist_stmt = $conn->prepare($history_sql);
$hist_stmt->bind_param("i", $partner_id);
$hist_stmt->execute();
$history_res = $hist_stmt->get_result();

$history = [];
$history = [];
while ($row = $history_res->fetch_assoc()) {
    $sid = $row['subscription_id'];
    $date = $row['delivery_date'] ?? 'Unknown Date';
    if (!isset($history[$sid])) {
        $history[$sid] = [
            'details' => $row,
            'items_by_date' => []
        ];
    }
    if (!isset($history[$sid]['items_by_date'][$date])) {
        $history[$sid]['items_by_date'][$date] = [];
    }
    $history[$sid]['items_by_date'][$date][] = $row;
}
$hist_stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery History - Tiffinly</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../user/user_css/profile_style.css">
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
        .header h1 {
            font-size: 28px;
            margin: 0 0 10px 0;
            position: relative;
            display: inline-block;
        }
        
        .header h1:after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--primary-color);
            border-radius: 3px;
            transition: all 0.3s ease;
        }
        
        .header:hover h1:after {
            width: 100%;
        }

        .delivery-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(44,122,123,0.09);
            padding: 28px 32px;
            margin-bottom: 25px;
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
            <div class="admin-avatar"><?php echo strtoupper(substr($user['name'] ?? 'P', 0, 1)); ?></div>
            <div class="admin-info">
                <h4><?php echo htmlspecialchars($user['name'] ?? 'Partner'); ?></h4>
                <p><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
            </div>
        </div>
        <div class="sidebar-menu">
            <a href="partner_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="partner_profile.php" class="menu-item"><i class="fas fa-user"></i> My Profile</a>
            <div class="menu-category">Manage Deliveries</div>
            <a href="available_orders.php" class="menu-item"><i class="fas fa-search"></i> Available Orders</a>
            <a href="my_deliveries.php" class="menu-item"><i class="fas fa-truck"></i> My Deliveries</a>
            <a href="delivery_history.php" class="menu-item active"><i class="fas fa-history"></i> Delivery History</a>
            <a href="performance_review.php" class="menu-item"><i class="fas fa-chart-line"></i> Performance Review</a>
            <a href="earnings.php" class="menu-item"><i class="fas fa-wallet"></i> Earnings & Incentives</a>
            <a href="log_issues.php" class="menu-item"><i class="fas fa-exclamation-triangle"></i> Log Issues</a>
            <div style="margin-top: 30px;"><a href="../logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
        </div>
    </div>
    <div class="main-content">
        <div class="content-wrapper">
            <div class="header">
                <div>
                    <h1>Delivery History</h1>
                    <p>Review your completed and cancelled deliveries.</p>
                </div>
            </div>

            <?php if (empty($history)): ?>
                <div class="delivery-card" style="text-align:center;color:#888;">
                    <i class="fas fa-box-open"></i> No past deliveries found.
                </div>
            <?php else: ?>
                <?php foreach ($history as $sub_id => $data): ?>
                    <div class="delivery-card">
                        <div class="card-header">
                            <div class="icon"><i class="fas fa-box"></i></div>
                            <div>
                                <div class="title">Order #<?php echo (int)$sub_id; ?></div>
                                <div style="font-size: 14px; color: #888;">
                                    Subscription: <?php echo date('d M Y', strtotime($data['details']['start_date'])); ?> to <?php echo date('d M Y', strtotime($data['details']['end_date'])); ?>
                                    <?php
                                        $status = strtolower($data['details']['status'] ?? 'active');
                                        $status_label = ucfirst($status);
                                        $badge_style = '';
                                        switch ($status) {
                                            case 'completed':
                                                $badge_style = 'background:#27ae60 !important; color:#fff !important;';
                                                break;
                                            case 'active':
                                                $badge_style = 'background:#2C7A7B !important; color:#fff !important;';
                                                break;
                                            case 'cancelled':
                                                $badge_style = 'background:#e74c3c !important; color:#fff !important;';
                                                break;
                                            case 'expired':
                                                $badge_style = 'background:#888 !important; color:#fff !important;';
                                                break;
                                            case 'pending':
                                                $badge_style = 'background:#f39c12 !important; color:#fff !important;';
                                                break;
                                            default:
                                                $badge_style = 'background:#888 !important; color:#fff !important;';
                                        }
                                    ?>
                                    <span class="badge badge-<?php echo $status; ?>" style="margin-left:10px;<?php echo $badge_style; ?>">
                                        <?php echo $status_label; ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="info-grid">
                            <div class="info-item">
                                <div class="label"><i class="fas fa-user"></i> Customer</div>
                                <div class="value"><?php echo htmlspecialchars($data['details']['user_name']); ?> (<?php echo htmlspecialchars($data['details']['phone']); ?>)</div>
                            </div>
                            <div class="info-item">
                                <div class="label"><i class="fas fa-calendar-alt"></i> Plan & Schedule</div>
                                <div class="value"><?php echo htmlspecialchars($data['details']['plan_name']); ?>, <?php echo htmlspecialchars($data['details']['schedule']); ?></div>
                            </div>
                        </div>

                        <div class="collapsible-details" id="details-<?php echo (int)$sub_id; ?>" style="display:none; margin-top: 20px; border-top: 1px dashed #eee; padding-top: 20px;">
                            <div class="date-scroller">
                                <ul>
                                    <?php
                                    $period = new DatePeriod(
                                        new DateTime($data['details']['start_date']),
                                        new DateInterval('P1D'),
                                        (new DateTime($data['details']['end_date']))->modify('+1 day')
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
                                            <?php
                                                $status = $item['delivery_status'];
                                                $badge_class = '';
                                                $badge_icon = '';
                                                $badge_text = ucfirst(str_replace('_',' ', $status));
                                                if ($status === 'delivered') {
                                                    $badge_class = 'delivered';
                                                    $badge_icon = '<i class="fas fa-check-circle" style="color:#27ae60"></i> ';
                                                } elseif ($status === 'cancelled') {
                                                    $badge_class = 'cancelled';
                                                    $badge_icon = '<i class="fas fa-times-circle" style="color:#c62828"></i> ';
                                                } elseif ($status === 'out_for_delivery') {
                                                    $badge_class = 'outfordelivery';
                                                    $badge_icon = '<i class="fas fa-truck" style="color:#f39c12"></i> ';
                                                }
                                            ?>
                                            <div class="badge <?php echo $badge_class; ?>">
                                                <?php echo $badge_icon . $badge_text; ?>
                                            </div>
                                            <div style="font-weight:600; color:#2C7A7B; min-width:100px;">
                                                <?php echo ucfirst($item['meal_type']); ?>
                                                <?php if (!empty($item['meal_name'])): ?>
                                                    <span style="color:#888; font-size:13px; margin-left:8px;">(<?php echo htmlspecialchars($item['meal_name']); ?>)</span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="color:#555; font-size: 13px;">
                                                <i class="fas fa-clock"></i>
                                                <?php echo htmlspecialchars($item['time_slot'] ?? 'N/A'); ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
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
                &copy; <?php echo date('Y'); ?> Tiffinly. All rights reserved. Partner Portal
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