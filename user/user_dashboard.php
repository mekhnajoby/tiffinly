<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$db = new mysqli('localhost', 'root', '', 'tiffinly');

// Check connection
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Get user data
$user_id = $_SESSION['user_id'];
$user_query = $db->prepare("SELECT name, email, phone FROM users WHERE user_id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user = $user_result->fetch_assoc();

// Helpers: schedule normalization and day counting for brief generation
function dash_norm_sched($s) {
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
function dash_count_total_days($start_date, $end_date, $schedule) {
    if (empty($start_date) || empty($end_date)) return 0;
    try { $start = new DateTime($start_date); $end = new DateTime($end_date); } catch (Exception $e) { return 0; }
    if ($end < $start) return 0;
    $norm = dash_norm_sched($schedule);
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
function dash_count_day_index_for($start_date, $end_date, $schedule, $reference_date) {
    if (empty($start_date) || empty($end_date) || empty($reference_date)) return 0;
    try { $start = new DateTime($start_date); $end = new DateTime($end_date); $ref = new DateTime($reference_date); } catch (Exception $e) { return 0; }
    if ($end < $start) return 0;
    if ($ref < $start) return 0;
    if ($ref > $end) $ref = $end;
    $norm = dash_norm_sched($schedule);
    $count = 0; $cur = clone $start;
    while ($cur <= $ref) {
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

// Function to get the weekly menu with user's selected meals
function get_weekly_menu($db, $plan_id, $dietary_pref, $subscription_id = null) {
    $menu = [];
    $days = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'];
    foreach ($days as $day) { $menu[$day] = ['Breakfast' => [], 'Lunch' => [], 'Dinner' => []]; }
    
    // If we have a subscription_id, fetch user's selected meals first
    if ($subscription_id) {
        $sql = "
            SELECT 
                sm.day_of_week,
                sm.meal_type,
                sm.meal_name
            FROM subscription_meals sm
            WHERE sm.subscription_id = ?
            ORDER BY 
                FIELD(sm.day_of_week, 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'),
                FIELD(sm.meal_type, 'Breakfast', 'Lunch', 'Dinner')";
        
        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $subscription_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $day = strtoupper($row['day_of_week']);
                $mealType = $row['meal_type'];
                if (isset($menu[$day][$mealType])) {
                    $menu[$day][$mealType][] = $row['meal_name'];
                }
            }
            $stmt->close();
            
            // If we found user's selected meals, return them
            $hasUserMeals = false;
            foreach ($menu as $day => $meals) {
                foreach ($meals as $mealType => $items) {
                    if (!empty($items)) {
                        $hasUserMeals = true;
                        break 2;
                    }
                }
            }
            if ($hasUserMeals) {
                return $menu;
            }
        }
    }
    
    // Fallback to plan meals if no user selection found
    $sql = "
        SELECT 
            pm.day_of_week, 
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


    // Normalize dietary preference for comparison
    $pref = strtolower(trim((string)$dietary_pref));
    if (in_array($pref, ['nonveg', 'non-veg', 'non_veg'])) {
        $pref = 'non-veg';
    } elseif (in_array($pref, ['veg', 'vegetarian'])) {
        $pref = 'veg';
    }
    if ($pref !== '' && $pref !== 'all' && $pref === 'veg') {
        $sql .= " AND LOWER(mc.option_type) = 'veg'";
    }

    $sql .= " ORDER BY 
        FIELD(pm.day_of_week, 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'), 
        FIELD(pm.meal_type, 'Breakfast', 'Lunch', 'Dinner')";

    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $plan_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Group meals by day, meal type, and option_type
    $all_meals = [];
    while ($row = $result->fetch_assoc()) {
        $d = $row['day_of_week'];
        $mt = $row['meal_type'];
        $opt = strtolower($row['option_type'] ?? '');
        $mealName = $row['meal_name'];
        $all_meals[$d][$mt][$opt][] = $mealName;
    }

    // Now, for each day and meal type, pick what to show based on preference
    foreach ($days as $d) {
        foreach (['Breakfast', 'Lunch', 'Dinner'] as $mt) {
            if (!isset($all_meals[$d][$mt])) continue;
            if ($pref === 'veg') {
                // Only veg
                if (!empty($all_meals[$d][$mt]['veg'])) {
                    $menu[$d][$mt] = $all_meals[$d][$mt]['veg'];
                }
            } else if ($pref === 'non-veg') {
                // Prefer non-veg, fallback to veg
                if (!empty($all_meals[$d][$mt]['non-veg'])) {
                    $menu[$d][$mt] = $all_meals[$d][$mt]['non-veg'];
                } elseif (!empty($all_meals[$d][$mt]['non_veg'])) {
                    $menu[$d][$mt] = $all_meals[$d][$mt]['non_veg'];
                } elseif (!empty($all_meals[$d][$mt]['nonveg'])) {
                    $menu[$d][$mt] = $all_meals[$d][$mt]['nonveg'];
                } elseif (!empty($all_meals[$d][$mt]['veg'])) {
                    $menu[$d][$mt] = $all_meals[$d][$mt]['veg'];
                }
            } else {
                // All or empty preference: show all
                $menu[$d][$mt] = [];
                foreach ($all_meals[$d][$mt] as $meals) {
                    $menu[$d][$mt] = array_merge($menu[$d][$mt], $meals);
                }
            }
        }
    }
    $stmt->close();
    
    return $menu;
}

// Get active subscription, delivery, and address info in one query
$subscription_query = $db->prepare("
    SELECT 
        s.subscription_id, 
        s.schedule, 
        s.total_price, 
        s.start_date, 
        s.end_date,
        s.dietary_preference,
        s.plan_id,
        mp.plan_name,
        a.line1, a.line2, a.city, a.state, a.pincode, a.landmark, a.address_type,
        da.partner_id,
        pu.name AS partner_name,
        pu.phone AS partner_phone
    FROM subscriptions s
    JOIN meal_plans mp ON s.plan_id = mp.plan_id
    LEFT JOIN delivery_preferences dp ON s.user_id = dp.user_id
    LEFT JOIN addresses a ON dp.address_id = a.address_id
    LEFT JOIN delivery_assignments da ON s.subscription_id = da.subscription_id
    LEFT JOIN users pu ON da.partner_id = pu.user_id AND pu.role = 'delivery'
    WHERE s.user_id = ? AND s.status = 'active' AND s.payment_status = 'paid'
    LIMIT 1
");
$subscription_query->bind_param("i", $user_id);
$subscription_query->execute();
$subscription_result = $subscription_query->get_result();
$has_subscription = $subscription_result->num_rows > 0;
$subscription = $has_subscription ? $subscription_result->fetch_assoc() : null;
// Weekly menu logic for Basic and Premium plans
$weekly_menu = [];
$plan_type = strtolower(trim($subscription['plan_name'] ?? ''));
if ($has_subscription) {
    if ($plan_type === 'basic plan') {
        // For Basic Plan, always show plan meals (no custom selection)
        $weekly_menu = get_weekly_menu($db, $subscription['plan_id'], $subscription['dietary_preference']);
        $menu_section_title = 'Weekly Menu (Basic Plan)';
    } elseif ($plan_type === 'premium plan') {
        // For Premium Plan, try user meals first, fallback to plan meals
        $weekly_menu = get_weekly_menu($db, $subscription['plan_id'], $subscription['dietary_preference'], $subscription['subscription_id']);
        $menu_section_title = 'Weekly Menu (Premium Plan)';
    } else {
        // Fallback for unknown plan types
        $weekly_menu = get_weekly_menu($db, $subscription['plan_id'], $subscription['dietary_preference'], $subscription['subscription_id']);
        $menu_section_title = 'Weekly Menu';
    }
}

// Fetch Home and Work addresses for the user (to display both if available)
$home_address = null;
$work_address = null;
$addr_stmt = $db->prepare("SELECT address_type, line1, line2, city, state, pincode, landmark FROM addresses WHERE user_id = ? AND address_type IN ('home','work')");
if ($addr_stmt) {
    $addr_stmt->bind_param("i", $user_id);
    $addr_stmt->execute();
    $addr_res = $addr_stmt->get_result();
    while ($row = $addr_res->fetch_assoc()) {
        if (strtolower($row['address_type']) === 'home') { $home_address = $row; }
        if (strtolower($row['address_type']) === 'work') { $work_address = $row; }
    }
    $addr_stmt->close();
}

// Get the most recent delivered meal based on the sequence (breakfast → lunch → dinner)
$latest_order = null;
if ($has_subscription) {
    // First, get the most recent delivery date
    $date_query = $db->prepare("
        SELECT DISTINCT da.delivery_date
        FROM delivery_assignments da
        JOIN subscriptions s ON da.subscription_id = s.subscription_id
        WHERE s.user_id = ? AND da.delivery_date <= CURDATE()
        ORDER BY da.delivery_date DESC
        LIMIT 1
    ");
    $date_query->bind_param("i", $user_id);
    $date_query->execute();
    $date_result = $date_query->get_result();
    
    if ($date_result->num_rows > 0) {
        $latest_date = $date_result->fetch_assoc()['delivery_date'];
        
        // Now get the most recently delivered meal for that date based on sequence (breakfast → lunch → dinner)
        // Only consider delivered meals (status = 'delivered')
        $orders_query = $db->prepare("
            SELECT da.meal_type,
                da.delivery_date,
                da.status as delivery_status,
                s.plan_id,
                mp.plan_name,
                s.subscription_id AS s_subscription_id,
                s.schedule AS s_schedule,
                s.start_date AS s_start_date,
                s.end_date AS s_end_date
            FROM delivery_assignments da
            JOIN subscriptions s ON da.subscription_id = s.subscription_id
            JOIN meal_plans mp ON s.plan_id = mp.plan_id
            WHERE s.user_id = ? 
              AND da.delivery_date = ?
              AND da.status = 'delivered'
            ORDER BY FIELD(da.meal_type, 'Breakfast', 'Lunch', 'Dinner') DESC
            LIMIT 1
        ");
        $orders_query->bind_param("is", $user_id, $latest_date);
        $orders_query->execute();
        $orders_result = $orders_query->get_result();
        if ($orders_result->num_rows > 0) {
            $latest_order = $orders_result->fetch_assoc();
        }
    }
}
$has_orders = !empty($latest_order);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiffinly - User Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="user_css/user_dashboard_style.css?v=<?php echo time(); ?>">
    <style>
        .address-type-badge {
            background-color: #e0e7ff;
            color: #4f46e5;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 12px;
            margin-left: 15px;
            vertical-align: middle;
        }
        .info-card-header {
            display: flex;
            align-items: center;
        }
        .info-card-header .info-card-title {
            margin: 0;
        }
        .menu-table-card {
            grid-column: 1 / -1; /* Full width */
            margin-top: 20px;
        }
        /* Layout for plan + delivery status side-by-side, address below */
        .subscription-info-stack {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .subscription-info-stack .address-card {
            grid-column: 1 / -1; /* full width */
        }
        /* Address grid for home/work side-by-side */
        .address-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .address-box { background: transparent; }
        /* Home badge color override */
        .address-type-badge.address-home { color: #2e7d32; }
        @media (max-width: 900px) {
            .subscription-info-stack { grid-template-columns: 1fr; }
            .address-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 900px) {
            .subscription-info-stack { grid-template-columns: 1fr; }
        }
        .menu-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .menu-table th, .menu-table td {
            border: 1px solid #e9ecef;
            padding: 12px;
            text-align: left;
            font-size: 0.9rem;
            vertical-align: top;
        }
        .menu-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #343a40;
        }
        .menu-table td {
            color: #495057;
            line-height: 1.5;
        }
        .menu-table tbody tr:nth-child(odd) {
            background-color: #fdfdfd;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
                <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-utensils"></i>&nbsp Tiffinly  
            </div>

            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div class="user-info">
                    <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
            </div>

            <div class="sidebar-menu">
                <a href="user_dashboard.php" class="menu-item active">
                    <i class="fas fa-dashboard"></i> Dashboard
                </a>

                <a href="profile.php" class="menu-item">
                    <i class="fas fa-user"></i> My Profile
                </a>

                <div class="menu-category">Order Management</div>
                <a href="browse_plans.php" class="menu-item">
                    <i class="fas fa-utensils"></i> Browse Plans
                </a>
                <a href="compare_plans.php" class="menu-item">
                    <i class="fas fa-exchange-alt"></i> Compare Menu
                </a>
                <a href="select_plan.php" class="menu-item">
                    <i class="fas fa-check-circle"></i> Select Plan
                </a>
                <a href="customize_meals.php" class="menu-item">
                    <i class="fas fa-sliders-h"></i> Customize Meals
                </a>
               
                <a href="cart.php" class="menu-item">
                    <i class="fas fa-shopping-cart"></i> My Cart
                </a>
                
                <div class="menu-category">Delivery & Payments</div>
                <a href="delivery_preferences.php" class="menu-item">
                    <i class="fas fa-truck"></i> Delivery Preferences
                </a>
                <a href="payment.php" class="menu-item">
                    <i class="fas fa-credit-card"></i> Payment
                </a>
                
                <div class="menu-category">Order History</div>
                <a href="track_order.php" class="menu-item">
                    <i class="fas fa-map-marker-alt"></i> Track Order
                </a>
                <a href="manage_subscriptions.php" class="menu-item">
                    <i class="fas fa-tools"></i> Manage Subscriptions
                </a>
                <a href="subscription_history.php" class="menu-item">
                    <i class="fas fa-calendar-alt"></i> Subscription History
                </a>
                
                <div class="menu-category">Feedback & Support</div>
                <a href="feedback.php" class="menu-item">
                    <i class="fas fa-comment-alt"></i> Feedback
                </a>
                <a href="support.php" class="menu-item">
                    <i class="fas fa-envelope"></i> Send Inquiry
                </a>
                <a href="my_inquiries.php" class="menu-item">
                    <i class="fas fa-inbox"></i> My Inquiries
                </a>
                
                <div style="margin-top: 30px;">
                    <a href="logout.php" class="menu-item">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Main Content Area -->
        <div class="main-content">
            <div class="header">
                <div class="welcome-message">
                    <h1>Welcome, <?php echo htmlspecialchars($user['name']); ?>!</h1>
                    <p><?php echo $has_subscription ? "Here's what's happening with your meal subscriptions" : "Get started with your first meal subscription"; ?></p>
                </div>
            </div>
            
           <!-- Current Subscription Section -->
<div class="dashboard-section">
    <div class="section-header">
        <h2 class="section-title">Current Subscription</h2>
        <?php if($has_subscription): ?>
            <a href="manage_subscriptions.php" class="view-all">Manage Subscription</a>
        <?php endif; ?>
    </div>
    <div class="content-card">
        <?php if($has_subscription): 
            // Calculate duration
            $start_date = new DateTime($subscription['start_date']);
            $end_date = new DateTime($subscription['end_date']);
            $duration_interval = $start_date->diff($end_date);
            $duration_weeks = floor($duration_interval->days / 7);

            // Business rule: For 4-week durations, end date is start_date + 25 days (inclusive => 1st to 26th) for display
            $display_end_date = clone $end_date;
            if ($duration_weeks === 4) {
                $display_end_date = clone $start_date;
                $display_end_date->modify('+25 days');
            }
            // Compute delivery duration in days based on schedule within [start_date, display_end_date] inclusive
            $schedule_lc = strtolower(trim($subscription['schedule'] ?? ''));
            // Allowed weekdays by schedule: ISO-8601 1=Mon ... 7=Sun
            if ($schedule_lc === 'weekdays') {
                $allowed = [1,2,3,4,5];
            } elseif ($schedule_lc === 'extended') {
                $allowed = [1,2,3,4,5,6];
            } elseif ($schedule_lc === 'full week' || $schedule_lc === 'full_week' || $schedule_lc === 'fullweek') {
                $allowed = [1,2,3,4,5,6,7];
            } elseif ($schedule_lc === 'weekends') {
                $allowed = [6,7];
            } else {
                $allowed = [1,2,3,4,5,6,7];
            }

            $duration_days_display = 0;
            $iter = clone $start_date;
            $end_inclusive = clone $display_end_date;
            while ($iter <= $end_inclusive) {
                $dow = (int)$iter->format('N');
                if (in_array($dow, $allowed, true)) { $duration_days_display++; }
                $iter->modify('+1 day');
            }
            $expected_deliveries = $duration_days_display * 3; // 3 meals per day

            // Format plan name
            $plan_display_name = htmlspecialchars($subscription['plan_name']);
            if (strtolower($subscription['plan_name']) == 'basic plan' && !empty($subscription['dietary_preference'])) {
                $plan_display_name .= ' (' . htmlspecialchars(ucfirst($subscription['dietary_preference'])) . ')';
            }
        ?>
            <div class="subscription-info-stack">
                <!-- Plan Info Card -->
                <div class="info-card">
                    <div class="info-card-header">
                        <i class="fas fa-utensils"></i>
                        <h3 class="info-card-title">Plan Details</h3>
                    </div>
                    <div class="info-card-content">
                        <strong>Subscription ID:</strong> #<?php echo $subscription['subscription_id']; ?><br>
                        <strong>Plan Type:</strong> <?php echo $plan_display_name; ?><br>
                        <strong>Schedule:</strong> <?php echo htmlspecialchars($subscription['schedule']); ?><br>
                        <strong>Start Date:</strong> <?php echo date("M j, Y", strtotime($subscription['start_date'])); ?><br>
                        <strong>End Date:</strong> <?php echo $display_end_date->format('M j, Y'); ?><br>
                        <strong>Duration:</strong> <?php echo $duration_days_display; ?> days<br>
                        <strong>Expected Deliveries:</strong> <?php echo number_format($expected_deliveries); ?> meals<br>
                        <strong>Total Price:</strong> ₹<?php echo number_format($subscription['total_price'], 2); ?><br>
                    </div>
                </div>

               


              
                <!-- Delivery Status Card (Right) -->
                <div class="info-card">
                    <div class="info-card-header">
                        <i class="fas fa-truck"></i>
                        <h3 class="info-card-title">Delivery Status</h3>
                    </div>
                    <div class="info-card-content">
                        <div class="delivery-status-badge">
                            <?php if(!empty($subscription['partner_id'])): ?>
                                <span class="status-badge status-assigned">
                                    <i class="fas fa-check-circle"></i> Assigned to Partner
                                </span>
                                <div style="margin-top:8px; font-size: 0.95rem; color:#374151;">
                                    <i class="fas fa-user"></i>
                                    <strong>Partner:</strong>
                                    <?php echo htmlspecialchars($subscription['partner_name'] ?? 'N/A'); ?><br>
                                    <?php if(!empty($subscription['partner_phone'])): ?>
                                        <i class="fas fa-phone"></i>
                                        <?php echo htmlspecialchars($subscription['partner_phone']); ?>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="status-badge status-pending">
                                    <i class="fas fa-hourglass-half"></i> Awaiting Partner
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Address Card (Full Width below) -->
                <div class="info-card address-card">
                    <div class="info-card-header">
                        <i class="fas fa-map-marker-alt"></i>
                        <h3 class="info-card-title">Delivery Address</h3>
                    </div>
                    <div class="info-card-content address-content">
                        <?php if($home_address || $work_address): ?>
                            <div class="address-grid">
                                <?php if($home_address): ?>
                                    <div class="address-box">
                                        <span class="address-type-badge address-home">Home</span>
                                        <p><?php echo htmlspecialchars($home_address['line1']); ?></p>
                                        <?php if(!empty($home_address['line2'])): ?><p><?php echo htmlspecialchars($home_address['line2']); ?></p><?php endif; ?>
                                        <p><?php echo htmlspecialchars($home_address['city'] . ', ' . $home_address['state'] . ' - ' . $home_address['pincode']); ?></p>
                                        <?php if(!empty($home_address['landmark'])): ?><p><?php echo htmlspecialchars($home_address['landmark']); ?></p><?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if($work_address): ?>
                                    <div class="address-box">
                                        <span class="address-type-badge">Work</span>
                                        <p><?php echo htmlspecialchars($work_address['line1']); ?></p>
                                        <?php if(!empty($work_address['line2'])): ?><p><?php echo htmlspecialchars($work_address['line2']); ?></p><?php endif; ?>
                                        <p><?php echo htmlspecialchars($work_address['city'] . ', ' . $work_address['state'] . ' - ' . $work_address['pincode']); ?></p>
                                        <?php if(!empty($work_address['landmark'])): ?><p><?php echo htmlspecialchars($work_address['landmark']); ?></p><?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
<br>                        <?php else: ?>
                            <p>No saved Home or Work address.</p>
                            <a href="profile.php#address" class="edit-link">
                                <i class="fas fa-plus-circle"></i> Add Delivery Address
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Weekly Menu Table -->
            <div class="info-card menu-table-card">
                <div class="info-card-header">
                    <i class="fas fa-calendar-day"></i>
                    <h3 class="info-card-title"><?php echo htmlspecialchars($menu_section_title ?? 'Weekly Menu'); ?></h3>
                </div>
                <div class="info-card-content">
                    <table class="menu-table">
                        <thead>
                            <tr>
                                <th>Meal</th>
                                <?php 
                                // Determine which days to display based on the schedule
                                $schedule = strtolower(trim($subscription['schedule']));
                                if ($schedule === 'weekdays') {
                                    $days_to_show = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY'];
                                } elseif ($schedule === 'extended') {
                                    $days_to_show = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];
                                } elseif ($schedule === 'full week' || $schedule === 'full_week' || $schedule === 'fullweek') {
                                    $days_to_show = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'];
                                } elseif ($schedule === 'weekends') {
                                    $days_to_show = ['SATURDAY', 'SUNDAY'];
                                } else { // default to all days
                                    $days_to_show = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'];
                                }

                                foreach ($days_to_show as $day) {
                                    echo '<th>' . ucfirst(strtolower($day)) . '</th>';
                                }
                                ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $meal_types = ['Breakfast', 'Lunch', 'Dinner'];
                            foreach ($meal_types as $meal_type): ?>
                                <tr>
                                    <td><strong><?php echo $meal_type; ?></strong></td>
                                    <?php foreach ($days_to_show as $day): ?>
                                        <td>
                                            <?php 
                                            if (!empty($weekly_menu[$day][$meal_type])) {
                                                echo implode('<br>', array_map('htmlspecialchars', $weekly_menu[$day][$meal_type]));
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-utensils"></i>
                <h3>No Active Subscription</h3>
                <p>You don't have an active meal subscription yet.</p>
                <a href="browse_plans.php" class="btn btn-primary pulse-animation">
                    <i class="fas fa-search"></i> Browse Plans
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
            
            <!-- Recent Orders -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h2 class="section-title">Recent Deliveries</h2>
                    <?php if($has_orders): ?>
                        <a href="track_order.php#past-deliveries" class="view-all">View All</a>
                    <?php endif; ?>
                </div>
                <div class="content-card">
                    <?php if(isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                        <div style="background:#fffbe6;border:1px solid #facc15;padding:10px;margin-bottom:10px;border-radius:8px;font-size:12px;color:#374151;">
                            <strong>Debug: Top fetched deliveries (max 5)</strong>
                            <div style="overflow:auto;margin-top:6px;">
                                <table style="width:100%;border-collapse:collapse;">
                                    <thead>
                                        <tr>
                                            <th style="border:1px solid #e5e7eb;padding:4px;">ID</th>
                                            <th style="border:1px solid #e5e7eb;padding:4px;">Sub</th>
                                            <th style="border:1px solid #e5e7eb;padding:4px;">Date</th>
                                            <th style="border:1px solid #e5e7eb;padding:4px;">Time</th>
                                            <th style="border:1px solid #e5e7eb;padding:4px;">Meal Type</th>
                                            <th style="border:1px solid #e5e7eb;padding:4px;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(!empty($orders_all)): foreach($orders_all as $r): ?>
                                            <tr>
                                                <td style="border:1px solid #e5e7eb;padding:4px;">#<?php echo (int)($r['delivery_id']??0); ?></td>
                                                <td style="border:1px solid #e5e7eb;padding:4px;">#<?php echo (int)($r['s_subscription_id']??0); ?></td>
                                                <td style="border:1px solid #e5e7eb;padding:4px;"><?php echo htmlspecialchars($r['delivery_date']??''); ?></td>
                                                <td style="border:1px solid #e5e7eb;padding:4px;"><?php echo htmlspecialchars($r['delivery_time']??''); ?></td>
                                                <td style="border:1px solid #e5e7eb;padding:4px;"><?php echo htmlspecialchars(trim((string)($r['meal_type']??''))); ?></td>
                                                <td style="border:1px solid #e5e7eb;padding:4px;">
                                                    <?php 
                                                    $status = !empty($r['delivery_status']) ? $r['delivery_status'] : $r['status'];
                                                    echo htmlspecialchars($status); 
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; else: ?>
                                            <tr><td colspan="6" style="border:1px solid #e5e7eb;padding:4px;">No rows</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if($has_orders): ?>
                        <div class="order-item">
                            <div class="order-icon">
                                <i class="fas fa-utensils"></i>
                            </div>
                            <div class="order-details">
                                <h4><?php echo htmlspecialchars($latest_order['plan_name']); ?> Delivery</h4>
                                <div class="order-meta" style="font-size:12px;color:#637381;">
                                    Sub #<?php echo (int)($latest_order['s_subscription_id'] ?? 0); ?>
                                    &nbsp;•&nbsp; Plan: <?php echo htmlspecialchars($latest_order['plan_name']); ?>
                                    &nbsp;•&nbsp; Schedule: <?php echo htmlspecialchars(ucfirst(strtolower($latest_order['s_schedule'] ?? 'daily'))); ?>
                                </div>
                                <?php 
                                    $t_days = dash_count_total_days($latest_order['s_start_date'] ?? null, $latest_order['s_end_date'] ?? null, $latest_order['s_schedule'] ?? 'daily');
                                    $d_idx = dash_count_day_index_for($latest_order['s_start_date'] ?? null, $latest_order['s_end_date'] ?? null, $latest_order['s_schedule'] ?? 'daily', $latest_order['delivery_date'] ?? null);
                                ?>
                                <p>
                                    <?php echo date('F j, Y', strtotime($latest_order['delivery_date'])); ?>
                                    <?php if($t_days > 0 && $d_idx > 0): ?>
                                        &nbsp;•&nbsp; Day <?php echo (int)$d_idx; ?> of <?php echo (int)$t_days; ?>
                                    <?php endif; ?>
                                    &nbsp;•&nbsp; <?php echo ucfirst($latest_order['meal_type']); ?> delivered
                                </p>
                            </div>
                            <div class="order-status status-delivered">
                                Delivered
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <h3>No Recent Deliveries</h3>
                            <p>No recent deliveries received.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
             <!-- Footer -->
            <footer style="
                text-align: center;
                padding: 20px;
                margin-top: 40px;
                color: #777;
                font-size: 14px;
                border-top: 1px solid #eee;
                animation: fadeIn 0.8s ease-out;
            ">
                <p>&copy; 2025 Tiffinly. All rights reserved.</p>
            </footer>
        </div>
    </div>

    <script>
        // Add some interactive animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effect to buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Add ripple effect to buttons on click
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    // Create ripple element
                    const ripple = document.createElement('span');
                    ripple.className = 'ripple';
                    this.appendChild(ripple);
                    
                    // Remove ripple after animation
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
            
            // Animate cards when they come into view
            const animateOnScroll = function() {
                const cards = document.querySelectorAll('.content-card, .stat-card');
                cards.forEach(card => {
                    const cardPosition = card.getBoundingClientRect().top;
                    const screenPosition = window.innerHeight / 1.3;
                    
                    if(cardPosition < screenPosition) {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }
                });
            };
            
            // Initial check
            animateOnScroll();
            
            // Check on scroll
            window.addEventListener('scroll', animateOnScroll);
        });
    </script>
</body>
</html>
<?php
$db->close();
?>