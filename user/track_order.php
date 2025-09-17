<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
date_default_timezone_set('Asia/Kolkata');
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include('../config/db_connect.php');

$user_id = $_SESSION['user_id'];

// Fetch user data for the sidebar
$user_query = $conn->prepare("SELECT name, email FROM users WHERE user_id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user = $user_result->fetch_assoc();

// Normalize schedule values to one of: daily, weekdays, weekends
function normalize_schedule($schedule) {
    $s = strtolower(trim((string)$schedule));
    $map = [
        'daily' => 'daily', 'everyday' => 'daily', 'all' => 'daily', 'all days' => 'daily',
        // treat a true full week (7 days) distinctly from generic daily for clarity
        'fullweek' => 'fullweek', 'full week' => 'fullweek', 'full_week' => 'fullweek',
        // extended means Mon-Sat (6 days)
        'extended' => 'extended',
        'weekday' => 'weekdays', 'weekdays' => 'weekdays', 'mon-fri' => 'weekdays', 'mon to fri' => 'weekdays',
        'weekend' => 'weekends', 'weekends' => 'weekends', 'sat-sun' => 'weekends', 'sat & sun' => 'weekends'
    ];
    return $map[$s] ?? 'daily';
}

// Count total delivery days within start/end dates according to normalized schedule
function count_total_delivery_days($start_date, $end_date, $schedule) {
    if (empty($start_date) || empty($end_date)) return 0;
    try {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
    } catch (Exception $e) {
        return 0;
    }
    if ($end < $start) return 0;
    $norm = normalize_schedule($schedule);
    $count = 0;
    $cursor = clone $start;
    while ($cursor <= $end) {
        $dow = (int)$cursor->format('N'); // 1=Mon ... 7=Sun
        $ok = (
            $norm === 'daily' ||
            $norm === 'fullweek' || // 7 days
            ($norm === 'extended' && $dow >= 1 && $dow <= 6) || // Mon-Sat
            ($norm === 'weekdays' && $dow >= 1 && $dow <= 5) ||
            ($norm === 'weekends' && $dow >= 6)
        );
        if ($ok) $count++;
        $cursor->modify('+1 day');
    }
    return $count;
}

// Count eligible delivery days from start_date up to reference date (capped by end_date)
function count_eligible_days_until($start_date, $end_date, $schedule, $reference = null) {
    if (empty($start_date) || empty($end_date)) return 0;
    try {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $ref = $reference ? new DateTime($reference) : new DateTime('today');
    } catch (Exception $e) {
        return 0;
    }
    if ($end < $start) return 0;
    if ($ref < $start) return 0;
    if ($ref > $end) $ref = $end;
    $norm = normalize_schedule($schedule);
    $count = 0;
    $cursor = clone $start;
    while ($cursor <= $ref) {
        $dow = (int)$cursor->format('N');
        $ok = (
            $norm === 'daily' ||
            $norm === 'fullweek' ||
            ($norm === 'extended' && $dow >= 1 && $dow <= 6) ||
            ($norm === 'weekdays' && $dow >= 1 && $dow <= 5) ||
            ($norm === 'weekends' && $dow >= 6)
        );
        if ($ok) $count++;
        $cursor->modify('+1 day');
    }
    return $count;
}

// Fetch active subscription and delivery partner details
$tracking_data = null;
$assigned_meals_today = [];
$upcoming_meals = [];
$has_active_subscription = false;
$delivered_meals_count = 0;
$next_delivery_info = null;

// First, check if user has any active subscriptions
$subscription_check_sql = "
    SELECT s.subscription_id, s.plan_id, s.dietary_preference, s.schedule,
           s.start_date, s.end_date
    FROM subscriptions s
    WHERE s.user_id = ?
      AND UPPER(s.status) IN ('ACTIVE','ACTIVATED')
      AND UPPER(s.payment_status) IN ('PAID','COMPLETED','SUCCESS')
    ORDER BY s.created_at DESC
    LIMIT 1
";

$stmt_check = $conn->prepare($subscription_check_sql);
$stmt_check->bind_param("i", $user_id);
$stmt_check->execute();
$subscription_result = $stmt_check->get_result();

if ($subscription_result->num_rows > 0) {
    $has_active_subscription = true;
    $subscription_data = $subscription_result->fetch_assoc();
    
    // Now, check if a partner is assigned to this subscription at all
    $partner_info = null;
    $partner_sql = "
        SELECT
            p.user_id as partner_id,
            p.name AS partner_name, p.phone AS partner_phone,
            dpd.vehicle_type, dpd.vehicle_number
        FROM delivery_assignments da
        LEFT JOIN users p ON da.partner_id = p.user_id
        LEFT JOIN delivery_partner_details dpd ON da.partner_id = dpd.partner_id
        WHERE da.subscription_id = ? AND da.partner_id IS NOT NULL
        LIMIT 1
    ";
    $partner_stmt = $conn->prepare($partner_sql);
    $partner_stmt->bind_param("i", $subscription_data['subscription_id']);
    $partner_stmt->execute();
    $partner_result = $partner_stmt->get_result();
    if ($partner_result->num_rows > 0) {
        $partner_info = $partner_result->fetch_assoc();
        $tracking_data = array_merge($subscription_data, $partner_info);
    }
    $partner_stmt->close();

    // If a partner is assigned, fetch today's meals
    if ($tracking_data) {
        $today = date('Y-m-d');
        $meals_sql = "
            SELECT
                da.assignment_id,
                COALESCE(sm.meal_name, m.meal_name) as meal_name,
                da.status AS delivery_status,
                dpref.time_slot,
                a.line1, a.line2, a.city, a.pincode,
                CONCAT(UCASE(LEFT(da.meal_type,1)), LCASE(SUBSTRING(da.meal_type,2))) AS meal_type
            FROM delivery_assignments da
            JOIN subscriptions s ON da.subscription_id = s.subscription_id
            LEFT JOIN meals m ON da.meal_id = m.meal_id
            LEFT JOIN subscription_meals sm ON da.subscription_id = sm.subscription_id AND UPPER(DAYNAME(da.delivery_date)) = sm.day_of_week AND da.meal_type = sm.meal_type
            LEFT JOIN delivery_preferences dpref ON s.user_id = dpref.user_id AND LOWER(da.meal_type) = LOWER(dpref.meal_type)
            LEFT JOIN addresses a ON dpref.address_id = a.address_id
            WHERE da.subscription_id = ? AND da.delivery_date = ?
            ORDER BY FIELD(CONCAT(UCASE(LEFT(da.meal_type,1)), LCASE(SUBSTRING(da.meal_type,2))), 'Breakfast', 'Lunch', 'Dinner')
        ";
        $meals_stmt = $conn->prepare($meals_sql);
        $meals_stmt->bind_param("is", $subscription_data['subscription_id'], $today);
        $meals_stmt->execute();
        $meals_result = $meals_stmt->get_result();
        while ($row = $meals_result->fetch_assoc()) {
            $assigned_meals_today[] = $row;
        }
        $meals_stmt->close();
    }
    
    // Fetch ALL delivered meals for progress calculation
    $all_meals_sql = "
        SELECT status AS delivery_status
        FROM delivery_assignments WHERE subscription_id = ? AND status IN ('delivered', 'completed')
    ";
    $all_meals_stmt = $conn->prepare($all_meals_sql);
    $all_meals_stmt->bind_param("i", $subscription_data['subscription_id']);
    $all_meals_stmt->execute();
    $delivered_meals_count = $all_meals_stmt->get_result()->num_rows;
    $all_meals_stmt->close();

    // Fetch ALL cancelled meals for progress calculation
    $cancelled_meals_count = 0;
    $cancelled_meals_sql = "
        SELECT COUNT(*) as count
        FROM delivery_assignments WHERE subscription_id = ? AND status = 'cancelled'
    ";
    $cancelled_meals_stmt = $conn->prepare($cancelled_meals_sql);
    $cancelled_meals_stmt->bind_param("i", $subscription_data['subscription_id']);
    $cancelled_meals_stmt->execute();
    $cancelled_meals_count = $cancelled_meals_stmt->get_result()->fetch_assoc()['count'] ?? 0;
    $cancelled_meals_stmt->close();

    // If no meals today, find the next delivery date
    if ($tracking_data && empty($assigned_meals_today)) {
        $next_delivery_sql = "SELECT delivery_date FROM delivery_assignments WHERE subscription_id = ? AND delivery_date >= CURDATE() ORDER BY delivery_date ASC LIMIT 1";
        $next_stmt = $conn->prepare($next_delivery_sql);
        $next_stmt->bind_param("i", $subscription_data['subscription_id']);
        $next_stmt->execute();
        $next_result = $next_stmt->get_result();
        if ($next_row = $next_result->fetch_assoc()) {
            $next_date = new DateTime($next_row['delivery_date']);
            $date_sql = $next_date->format('Y-m-d');
            $days_map = [1=>'MONDAY',2=>'TUESDAY',3=>'WEDNESDAY',4=>'THURSDAY',5=>'FRIDAY',6=>'SATURDAY',7=>'SUNDAY'];
            $next_delivery_info = [
                'date_label' => $next_date->format('d M Y'),
                'day_str' => $days_map[(int)$next_date->format('N')],
                'date_sql' => $date_sql
            ];

            // Fetch meals for the upcoming date
            $upcoming_meals_sql = "
                SELECT
                    da.assignment_id,
                    COALESCE(sm.meal_name, m.meal_name) as meal_name,
                    da.status AS delivery_status,
                    dpref.time_slot,
                    a.line1, a.line2, a.city, a.pincode,
                    CONCAT(UCASE(LEFT(da.meal_type,1)), LCASE(SUBSTRING(da.meal_type,2))) AS meal_type
                FROM delivery_assignments da
                JOIN subscriptions s ON da.subscription_id = s.subscription_id
                LEFT JOIN meals m ON da.meal_id = m.meal_id
                LEFT JOIN subscription_meals sm ON da.subscription_id = sm.subscription_id AND UPPER(DAYNAME(da.delivery_date)) = sm.day_of_week AND da.meal_type = sm.meal_type
                LEFT JOIN delivery_preferences dpref ON s.user_id = dpref.user_id AND LOWER(da.meal_type) = LOWER(dpref.meal_type)
                LEFT JOIN addresses a ON dpref.address_id = a.address_id
                WHERE da.subscription_id = ? AND da.delivery_date = ?
                ORDER BY FIELD(CONCAT(UCASE(LEFT(da.meal_type,1)), LCASE(SUBSTRING(da.meal_type,2))), 'Breakfast', 'Lunch', 'Dinner')
            ";
            $upcoming_meals_stmt = $conn->prepare($upcoming_meals_sql);
            $upcoming_meals_stmt->bind_param("is", $subscription_data['subscription_id'], $date_sql);
            $upcoming_meals_stmt->execute();
            $upcoming_meals_result = $upcoming_meals_stmt->get_result();
            while ($row = $upcoming_meals_result->fetch_assoc()) {
                $upcoming_meals[] = $row;
            }
            $upcoming_meals_stmt->close();
        }
        $next_stmt->close();
    }
}

// Fetch all past deliveries for the active subscription
$past_deliveries_by_date = [];
if ($has_active_subscription) {
    $past_sql = "
        SELECT da.delivery_date, da.meal_type, da.status,
               COALESCE(sm.meal_name, m.meal_name) AS meal_name
        FROM delivery_assignments da
        LEFT JOIN meals m ON da.meal_id = m.meal_id
        LEFT JOIN subscription_meals sm ON da.subscription_id = sm.subscription_id AND UPPER(DAYNAME(da.delivery_date)) = sm.day_of_week AND da.meal_type = sm.meal_type
        WHERE da.subscription_id = ? AND da.status IN ('delivered', 'cancelled')
        ORDER BY da.delivery_date DESC, FIELD(da.meal_type, 'Breakfast', 'Lunch', 'Dinner')
    ";
    $past_stmt = $conn->prepare($past_sql);
    $past_stmt->bind_param("i", $subscription_data['subscription_id']);
    $past_stmt->execute();
    $past_result = $past_stmt->get_result();
    while ($row = $past_result->fetch_assoc()) {
        $past_deliveries_by_date[$row['delivery_date']][] = $row;
    }
    $past_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Order - Tiffinly</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="user_css/user_dashboard_style.css">
    <style>
        .main-content { padding: 30px; }
        .content-card { background-color: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 5rem; color: #2C7A7B; opacity: 0.3; margin-bottom: 20px; }
        .empty-state h3 { font-size: 1.5rem; color: #333; }
        .empty-state p { color: #666; margin-bottom: 25px; }
        .btn-primary { background-color: #2C7A7B; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; transition: background-color 0.3s; }
        .btn-primary:hover { background-color: #1D5F60; }

        .tracking-container { display: grid; grid-template-columns: 1fr 2fr; gap: 30px; }
        .partner-card, .delivery-details-card { background: #f8f9fa; border-radius: 10px; padding: 25px; border: 1px solid #e9ecef; }
        .card-title { font-size: 1.3rem; font-weight: 600; color: #1D5F60; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e9ecef; }
        .partner-info .info-item { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
        .partner-info .info-item i { font-size: 1.2rem; color: #2C7A7B; width: 25px; text-align: center; }
        .partner-info .info-item span { font-size: 1rem; color: #333; }

        .meal-item { border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 20px; background-color: #fff; }
        .meal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .meal-type { font-size: 1.1rem; font-weight: 600; color: #333; }
        .delivery-time { font-size: 0.9rem; color: #555; background: #e9f7f7; padding: 5px 10px; border-radius: 15px; }
        .meal-contents ul { list-style: none; padding-left: 0; margin: 0; }
        .meal-contents li { padding: 5px 0; color: #444; }
        .meal-contents li i { color: #27ae60; margin-right: 8px; }
        .delivery-status { margin-top: 15px; font-weight: 600; }
        .status-pending { color: #f39c12; }
        .status-delivered { color: #27ae60; }
        .status-not-delivered { color: #e74c3c; }

        .cancel-btn {
            background-color: #e74c3c; color: white; border: none;
            padding: 4px 10px; font-size: 12px; border-radius: 5px;
            cursor: pointer; transition: background-color 0.2s;
        }
        .cancel-btn:hover { background-color: #c0392b; }
        .cancel-btn:disabled { background-color: #ccc; cursor: not-allowed; }
        /* Past Deliveries Styles */
.past-deliveries-card { 
    background: #fff; 
    border-radius: 10px; 
    padding: 25px; 
    border: 1px solid #e9ecef; 
    margin-top: 20px; 
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.delivery-history { 
    max-height: 300px; 
    overflow-y: auto;
    padding-right: 10px;
}

.delivery-history::-webkit-scrollbar {
    width: 6px;
}

.delivery-history::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.delivery-history::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 10px;
}

.delivery-day { 
    border-bottom: 1px solid #e9ecef; 
    padding: 15px 0; 
}

.delivery-day:last-child { 
    border-bottom: none; 
}

.delivery-date { 
    background: #fff; 
    border-radius: 10px; 
    padding: 25px; 
    border: 1px solid #e9ecef; 
    margin-top: 20px; 
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

    display: flex; 
    align-items: center; 
    justify-content: space-between; 
    margin-bottom: 10px; 
}

.date-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.date-info i { 
    color: #2C7A7B; 
    font-size: 1rem;
}

.date-text {
    font-weight: 500;
    color: #333;
}

.badge { 
    background-color: #2C7A7B; 
    color: white; 
    padding: 4px 10px; 
    border-radius: 12px; 
    font-size: 0.8rem; 
    font-weight: 500;
}

.meal-types { 
    display: flex; 
    flex-wrap: wrap; 
    gap: 8px; 
}

.meal-tag { 
    background-color: #e9f7f7; 
    padding: 6px 12px; 
    border-radius: 15px; 
    font-size: 0.85rem; 
    color: #2C7A7B; 
    display: flex; 
    align-items: center; 
    gap: 5px; 
    font-weight: 500;
}

.meal-tag i {
    font-size: 0.75rem;
}

.no-deliveries { 
    text-align: center; 
    padding: 30px 20px; 
    color: #6c757d; 
}

.no-deliveries i { 
    font-size: 2.5rem; 
    margin-bottom: 15px; 
    display: block; 
    color: #dee2e6;
}

.no-deliveries p {
    margin: 0;
    font-style: italic;
}

        .date-scroller {
            overflow-x: auto;
            white-space: nowrap;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            margin-bottom: 15px;
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
        .badge {
            padding: 6px 12px;
            border-radius: 16px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .badge.delivered, .status-resolved { 
            background: #e8f5e9; 
            color: #2e7d32;
        }
        .badge.cancelled { 
            background: #ffebee; 
            color: #c62828;
        }
        .status-pending {
            background: #fff3e0;
            color: #e65100;
        }
        .status-resolved {
            background: #e8f5e9;
            color: #2e7d32;
        }

        @media (max-width: 992px) {
            .tracking-container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-utensils"></i>&nbsp;Tiffinly
        </div>
        <div class="user-profile">
            <div class="user-avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
            <div class="user-info">
                <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                <p><?php echo htmlspecialchars($user['email']); ?></p>
            </div>
        </div>
        <div class="sidebar-menu">
            <a href="user_dashboard.php" class="menu-item"><i class="fas fa-dashboard"></i> Dashboard</a>
            <a href="profile.php" class="menu-item"><i class="fas fa-user"></i> My Profile</a>
            <div class="menu-category">Order Management</div>
            <a href="browse_plans.php" class="menu-item"><i class="fas fa-utensils"></i> Browse Plans</a>
            <a href="compare_plans.php" class="menu-item"><i class="fas fa-exchange-alt"></i> Compare Menu</a>
            <a href="select_plan.php" class="menu-item"><i class="fas fa-check-circle"></i> Select Plan</a>
            <a href="customize_meals.php" class="menu-item"><i class="fas fa-sliders-h"></i> Customize Meals</a>
            <a href="cart.php" class="menu-item"><i class="fas fa-shopping-cart"></i> My Cart</a>
            <div class="menu-category">Delivery & Payments</div>
            <a href="delivery_preferences.php" class="menu-item"><i class="fas fa-truck"></i> Delivery Preferences</a>
            <a href="payment.php" class="menu-item"><i class="fas fa-credit-card"></i> Payment</a>
            <div class="menu-category">Order History</div>
            <a href="track_order.php" class="menu-item active"><i class="fas fa-map-marker-alt"></i> Track Order</a>
            <a href="manage_subscriptions.php" class="menu-item"><i class="fas fa-tools"></i> Manage Subscriptions</a>
            <a href="subscription_history.php" class="menu-item"><i class="fas fa-calendar-alt"></i> Subscription History</a>
            <div class="menu-category">Feedback & Support</div>
            <a href="feedback.php" class="menu-item"><i class="fas fa-comment-alt"></i> Feedback</a>
            <a href="support.php" class="menu-item"><i class="fas fa-envelope"></i> Send Inquiry</a>
            <a href="my_inquiries.php" class="menu-item"><i class="fas fa-inbox"></i> My Inquiries</a>
            <div style="margin-top: 30px;">
                <a href="../logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <div class="header">
            <div class="welcome-message">
                <h1>Track Your Order</h1>
                <p>See the status of your daily deliveries and your delivery partner's details.</p>
            </div>
        </div>

        <div class="content-card">
            <?php if ($has_active_subscription && $tracking_data): ?>
                <div class="tracking-container" style="display: flex; flex-wrap: wrap; gap: 20px;">
                    <div style="flex: 1; min-width: 300px;">
                        <div class="partner-card">
                            <h3 class="card-title"><i class="fas fa-user-shield"></i> Delivery Partner</h3>
                            <div class="partner-info">
                                <div class="info-item">
                                    <i class="fas fa-user"></i>
                                    <span><?php echo htmlspecialchars($tracking_data['partner_name']); ?></span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-phone"></i>
                                    <span><?php echo htmlspecialchars($tracking_data['partner_phone']); ?></span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-motorcycle"></i>
                                    <span><?php echo htmlspecialchars($tracking_data['vehicle_type']); ?></span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-hashtag"></i>
                                    <span><?php echo htmlspecialchars($tracking_data['vehicle_number']); ?></span>
                                </div>
                            </div>
                        </div>
                        
                      

<!-- Delivery Logs -->
<div class="past-deliveries-card">
    <h3 class="card-title"><i class="fas fa-clipboard-list"></i> Delivery Logs</h3>
    <?php
    // Fetch delivery issues for this subscription
    $issues_query = $conn->prepare("SELECT description, created_at, status
        FROM delivery_issues 
        WHERE subscription_id = ? 
        ORDER BY created_at DESC
    ");
    $issues_query->bind_param("i", $tracking_data['subscription_id']);
    $issues_query->execute();
    $issues_result = $issues_query->get_result();
    ?>
    <div class="delivery-history">
        <?php if ($issues_result->num_rows > 0): ?>
            <?php while($issue = $issues_result->fetch_assoc()): ?>
    <div class="delivery-day">
        <div class="delivery-date">
            <div class="date-info">
                <i class="far fa-calendar-alt"></i> 
                <span class="date-text"><?php echo date('M d, Y, h:i A', strtotime($issue['created_at'])); ?></span>
            </div>
            <p style="margin: 8px 0 0 0; color: #555; flex: 1;"><?php echo htmlspecialchars($issue['description']); ?></p>
            <div style="margin-top: 8px; display: flex; justify-content: flex-end;">
                <span class="badge <?php echo $issue['status'] === 'resolved' ? 'status-resolved' : 'status-pending'; ?>">
                    <i class="fas fa-<?php echo $issue['status'] === 'resolved' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars(ucfirst($issue['status'])); ?>
                </span>
            </div>
        </div>
    </div>
<?php endwhile; ?>
        <?php else: ?>
            <div class="no-deliveries"><i class="far fa-check-circle"></i><p>No delivery issues have been logged for this order.</p></div>
        <?php endif; ?>
    </div>
</div>
 <!-- Past Deliveries Summary -->
<div class="past-deliveries-card">
    <h3 class="card-title"><i class="fas fa-history"></i> Past Deliveries</h3>
    <?php if (!empty($past_deliveries_by_date)): ?>
        <div class="date-scroller">
            <ul>
                <?php foreach (array_keys($past_deliveries_by_date) as $date): ?>
                    <li>
                        <button class="date-button" data-date="<?php echo $date; ?>">
                            <?php echo date('d M', strtotime($date)); ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="details-panel" style="margin-top:12px;">
            <?php foreach ($past_deliveries_by_date as $date => $items): ?>
                <div class="delivery-day-group" data-date="<?php echo $date; ?>" style="display:none; margin-bottom: 15px; padding: 10px; border: 1px solid #f0f0f0; border-radius: 8px;">
                    <h4 class="delivery-date-header" style="margin: 0 0 10px 0; font-size: 15px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 5px;">
                        <i class="far fa-calendar-alt"></i> <?php echo date('d M Y, l', strtotime($date)); ?>
                    </h4>
                    <?php foreach ($items as $item): ?>
                        <div style="display:flex; align-items:center; gap:12px; padding:8px; border-radius:6px; margin-bottom:6px; background:#fdfdfd; font-size: 12px; line-height: 1.2;">
                            <div style="font-weight:600; color:#2C7A7B; min-width:100px;">
                            <?php echo ucfirst($item['meal_type']); ?>:
                            <?php echo htmlspecialchars($item['meal_name'] ?? ''); ?>
                        </div>
                        <div class="badge <?php echo $item['status']==='delivered' ? 'delivered' : 'cancelled'; ?>">
                            <i class="fas fa-<?php echo $item['status']==='delivered' ? 'check' : 'times'; ?>-circle"></i>
                            <?php echo ucfirst(str_replace('_',' ', $item['status'])); ?>
                        </div>
                        
                        
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <div class="no-date-selected-message" style="text-align:center; color:#888; padding: 20px 0;">
                <i class="fas fa-hand-pointer" style="font-size: 1.5rem; margin-bottom: 10px;"></i><br>
                Select a date above to view delivery details.
            </div>
        </div>
    <?php else: ?>
        <div class="no-deliveries">
            <i class="far fa-calendar-times"></i>
            <p>No past deliveries found for this subscription.</p>
        </div>
    <?php endif; ?>
</div>







                    </div>
                    <div class="delivery-details-card" style="flex: 2; min-width: 300px;">
                        <?php 
                            $all_delivered = true;
                            if (!empty($assigned_meals_today)) {
                                foreach ($assigned_meals_today as $meal) {
                                    if (!in_array(strtolower($meal['delivery_status']), ['delivered', 'completed'])) {
                                        $all_delivered = false;
                                        break;
                                    }
                                }
                            }
                            $is_today_view = !empty($assigned_meals_today) && !$all_delivered ? true : false;
                            if (!empty($assigned_meals_today) && $all_delivered) {
                                $title = "Today's Delivery: " . date('d M Y');
                            } else if ($is_today_view) {
                                $title = "Today's Delivery: " . date('d M Y');
                            } else {
                                $title = 'Upcoming Delivery (' . htmlspecialchars($next_delivery_info['date_label'] ?? '') . ')';
                            }
                            echo '<h3 class="card-title" style="font-size: 1.2rem; color: #333;"><i class="fas fa-box-open"></i> ' . $title . '</h3>';
                            if (!empty($assigned_meals_today) && $all_delivered) {
                                echo '<div style="color: green; font-weight: bold; margin: 8px 0 0 0;">All  meals delivered for today.</div>';
                            }
                            if (!empty($assigned_meals_today)) {
                                $display_meals = $assigned_meals_today;
                            } else {
                                $display_meals = $upcoming_meals ?? [];
                            }
                            
                            // Total expected meals and current day index for the subscription window
                            $total_days = count_total_delivery_days($tracking_data['start_date'] ?? null, $tracking_data['end_date'] ?? null, $tracking_data['schedule'] ?? 'daily');
                            if ($total_days > 0) {
                                $current_day = count_eligible_days_until($tracking_data['start_date'] ?? null, $tracking_data['end_date'] ?? null, $tracking_data['schedule'] ?? 'daily');
                                $expected_meals = $total_days * 3; // 3 meals per day
                                $pending_meals = $expected_meals - $delivered_meals_count - $cancelled_meals_count;
                                
                                echo '<div style="font-size: 13px; color: #2C3E50; margin-bottom: 15px; display: flex; flex-wrap: wrap; gap: 15px; align-items: center; background: #f8f9fa; padding: 8px; border-radius: 6px;">';
                                echo '  <div style="color:#2C7A7B;"><i class="far fa-calendar-check"></i> Day <strong>' . $current_day . ' of ' . $total_days . '</strong></div>';
                                echo '  <div style="color:#27ae60;"><i class="fas fa-check-circle"></i> Delivered: <strong>' . $delivered_meals_count . '</strong></div>';
                                echo '  <div style="color:#e74c3c;"><i class="fas fa-times-circle"></i> Cancelled: <strong>' . $cancelled_meals_count . '</strong></div>';
                                echo '  <div style="color:#3498db;"><i class="fas fa-hourglass-half"></i> Pending: <strong>' . max(0, $pending_meals) . '</strong></div>';
                                echo '  <div style="color:#2C3E50;"><i class="fas fa-list-ol"></i> Total Paid: <strong>' . $expected_meals . ' meals</strong></div>';
                                echo '</div>';
                            }
                        ?>
                        <?php if (empty($display_meals)): ?>
                            <div class="meal-item">
                                <div>No meals to display for the selected day yet.</div>
                            </div>
                        <?php endif; ?>
                        <?php foreach ($display_meals as $meal): $assignment_id = $meal['assignment_id'] ?? 0; ?>
                            <div class="meal-item">
                                <div class="meal-header">
                                    <span class="meal-type"><?php echo htmlspecialchars($meal['meal_type']); ?></span>
                                    <span class="delivery-time"><i class="far fa-clock"></i> <?php echo htmlspecialchars($meal['time_slot']); ?></span>
                                </div>
                                <div class="meal-contents">
                                    <strong>Menu:</strong>
                                    <ul>
                                        <li>
                                            <i class="fas fa-utensil-spoon"></i> 
                                            <?php echo htmlspecialchars($meal['meal_name'] ?? 'Meal name not found.'); ?>
                                        </li>
                                    </ul>
                                    <div style="margin-top:8px;color:#555;">
                                        <strong><i class="fas fa-map-marker-alt"></i> Address:</strong>
                                        <?php
                                            $parts = [];
                                            if (!empty($meal['line1'])) $parts[] = $meal['line1'];
                                            if (!empty($meal['line2'])) $parts[] = $meal['line2'];
                                            $cityPin = trim(($meal['city'] ?? '') . (isset($meal['pincode']) ? ' - ' . $meal['pincode'] : ''));
                                            if ($cityPin !== '-') $parts[] = $cityPin;
                                            echo htmlspecialchars(implode(', ', $parts));
                                        ?>
                                    </div>
                                </div>
                                <?php 
                                    $status = $meal['delivery_status'] ?? 'pending';
                                    $status_class = str_replace('_', '-', $status);
                                    $is_cancellable = ($status === 'pending');
                                    $status_label = '';
                                    if ($status === 'out_for_delivery') {
                                        $status_label = '<span style="color:#f39c12"><i class="fas fa-truck"></i> Out for Delivery</span>';
                                    } elseif ($status === 'delivered') {
                                        $status_label = '<span style="color:#27ae60"><i class="fas fa-check-circle"></i> Delivered</span>';
                                    } elseif ($status === 'cancelled') {
                                        $status_label = '<span style="color:#e74c3c"><i class="fas fa-times-circle"></i> Cancelled</span>';
                                    } else {
                                        $status_label = '<span style="color:#3498db"><i class="fas fa-hourglass-half"></i> Pending</span>';
                                    }
                                ?>
                                <div class="delivery-status status-<?php echo $status_class; ?>" id="status-<?php echo $assignment_id; ?>">
                                    Status: <?php echo $status_label; ?>
                                </div>
                                <?php if ($is_cancellable): ?>
                                <div style="text-align:right; margin-top: 10px;">
                                    <button class="cancel-btn" data-assignment-id="<?php echo $assignment_id; ?>">
                                        <i class="fas fa-times-circle"></i> Cancel Meal
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif ($has_active_subscription): ?>
                <div class="empty-state">
                    <i class="fas fa-hourglass-half"></i>
                    <h3>Awaiting Partner Assignment</h3>
                    <p>Your order is confirmed! We are currently assigning a delivery partner to your subscription.</p>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search-dollar"></i>
                    <h3>No Active Orders</h3>
                    <p>You do not have any active subscriptions to track. Please subscribe to a plan to get started.</p>
                    <a href="browse_plans.php" class="btn-primary">Browse Meal Plans</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <footer style="text-align: center; padding: 20px; margin-top: 40px; color: #777; font-size: 14px; border-top: 1px solid #eee;">
            <p>&copy; <?php echo date('Y'); ?> Tiffinly. All rights reserved.</p>
        </footer>
    </div>
</div>

<!-- Include JavaScript Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Refresh the page every 5 minutes to update delivery status
    setTimeout(function() {
        location.reload();
    }, 5 * 60 * 1000);

    document.querySelectorAll('.cancel-btn').forEach(button => {
        button.addEventListener('click', function() {
            const assignmentId = this.getAttribute('data-assignment-id');
            if (!confirm('Are you sure you want to cancel this meal delivery? This cannot be undone.')) {
                return;
            }

            fetch('../ajax/cancel_meal.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `assignment_id=${assignmentId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const statusDiv = document.getElementById(`status-${assignmentId}`);
                    if (statusDiv) {
                        statusDiv.querySelector('span').textContent = 'Cancelled';
                        statusDiv.className = 'delivery-status status-cancelled';
                    }
                    this.style.display = 'none'; // Hide the button
                    alert('Meal has been cancelled successfully.');
                } else {
                    alert('Failed to cancel meal: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        });
    });

    // Past deliveries date scroller logic
    const dateButtons = document.querySelectorAll('.past-deliveries-card .date-button');
    const deliveryDayGroups = document.querySelectorAll('.past-deliveries-card .delivery-day-group');
    const noDateSelectedMessage = document.querySelector('.past-deliveries-card .no-date-selected-message');

    dateButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const selectedDate = this.getAttribute('data-date');

            // Update button styles
            dateButtons.forEach(otherBtn => otherBtn.classList.remove('selected'));
            this.classList.add('selected');

            // Hide all day groups and the initial message
            if (noDateSelectedMessage) {
                noDateSelectedMessage.style.display = 'none';
            }
            deliveryDayGroups.forEach(group => group.style.display = 'none');

            // Show the selected day group
            const selectedGroup = document.querySelector(`.past-deliveries-card .delivery-day-group[data-date="${selectedDate}"]`);
            if (selectedGroup) {
                selectedGroup.style.display = 'block';
            }
        });
    });
})
</script>

</body>
</html>
<?php
$conn->close();
?>