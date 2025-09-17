<?php
session_start();
include('../config/db_connect.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// --- Helper functions ---
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

// Edit/view mode: allow loading plan by subscription_id if posted or in GET
$edit_subscription_id = null;
if (isset($_POST['edit_subscription_id'])) {
    $edit_subscription_id = intval($_POST['edit_subscription_id']);
} elseif (isset($_GET['edit_subscription_id'])) {
    $edit_subscription_id = intval($_GET['edit_subscription_id']);
}

// Active subscription check (except for editing current)
$has_active_subscription = false;
$active_check_sql = "SELECT subscription_id FROM subscriptions WHERE user_id = ? AND status = 'active' LIMIT 1";
$active_check_stmt = $conn->prepare($active_check_sql);
$active_check_stmt->bind_param("i", $user_id);
$active_check_stmt->execute();
$active_check_stmt->store_result();
if ($active_check_stmt->num_rows > 0) {
    $has_active_subscription = true;
}
$active_check_stmt->close();

if ($edit_subscription_id) {
    // Fetch subscription and meal selections from DB
    $sql = "SELECT s.*, mp.plan_name, mp.plan_type, mp.base_price FROM subscriptions s JOIN meal_plans mp ON s.plan_id = mp.plan_id WHERE s.subscription_id = ? AND s.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $edit_subscription_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $subscription = $result->fetch_assoc();
    if ($subscription) {
        $plan_selection = [
            'plan_id' => $subscription['plan_id'],
            'plan_name' => $subscription['plan_name'],
            'plan_type' => $subscription['plan_type'],
            'option_type' => $subscription['dietary_preference'] ?? '',
            'schedule' => $subscription['schedule'],
            'start_date' => $subscription['start_date'],
            'end_date' => $subscription['end_date'],
            'duration_weeks' => round((strtotime($subscription['end_date'])-strtotime($subscription['start_date']))/604800),
            'delivery_time' => $subscription['delivery_time'],
            'final_price' => $subscription['total_price'],
        ];
        // Fetch meal selections if premium
        $premium_meal_selection = [];
        if ($subscription['plan_type'] === 'premium') {
            $meal_sql = "SELECT day_of_week, meal_type, meal_name FROM subscription_meals WHERE subscription_id = ?";
            $meal_stmt = $conn->prepare($meal_sql);
            $meal_stmt->bind_param("i", $edit_subscription_id);
            $meal_stmt->execute();
            $meal_result = $meal_stmt->get_result();
            while ($row = $meal_result->fetch_assoc()) {
                $premium_meal_selection[$row['day_of_week']][$row['meal_type']] = $row['meal_name'];
            }
        }
        $_SESSION['plan_selection'] = $plan_selection;
        if (!empty($premium_meal_selection)) {
            $_SESSION['premium_meal_selection'] = $premium_meal_selection;
        }
        $show_customization = true;
    } else {
        $show_customization = false;
    }
} elseif ($has_active_subscription) {
    $show_customization = false;
    $error_message = "You already have an active subscription. Please cancel it before customizing a new plan.";
} elseif (!isset($_SESSION['plan_selection'])) {
    $show_customization = false;
} else {
    $show_customization = true;
}

$plan_selection = isset($_SESSION['plan_selection']) ? $_SESSION['plan_selection'] : null;

$plan_type = $plan_selection ? $plan_selection['plan_type'] : null;

// Fetch user data for sidebar
$user_sql = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// Handle form submission to save edited meals
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_edited_meals'])) {
    if ($edit_subscription_id && isset($_POST['premium_meal_selection'])) {
        $premium_meal_selection = $_POST['premium_meal_selection'];

        $conn->begin_transaction();
        try {
            // 1. Delete old meal selections
            $delete_sql = "DELETE FROM subscription_meals WHERE subscription_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $edit_subscription_id);
            $delete_stmt->execute();

            // 2. Insert new meal selections
            $insert_meal_sql = "INSERT INTO subscription_meals (subscription_id, day_of_week, meal_type, meal_name) VALUES (?, ?, ?, ?)";
            $meal_stmt = $conn->prepare($insert_meal_sql);
            foreach ($premium_meal_selection as $day => $meals_by_time) {
                foreach ($meals_by_time as $meal_type => $meal_name) {
                    if (!empty($meal_name)) { // Only insert if a meal was selected
                        $meal_stmt->bind_param("isss", $edit_subscription_id, $day, $meal_type, $meal_name);
                        $meal_stmt->execute();
                    }
                }
            }
            $conn->commit();
            $_SESSION['message'] = 'Your meal plan has been updated successfully!';
            header('Location: manage_subscriptions.php');
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error: Could not update meal selections. Please try again. " . $e->getMessage();
        }
    }
}

// Handle form submission to finalize subscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_subscription'])) {
    $plan_id = $plan_selection['plan_id'];
    $start_date = $plan_selection['start_date'];
    $end_date = $plan_selection['end_date'];
    $schedule = $plan_selection['schedule'];
    $dietary_preference = isset($_POST['dietary_preference']) ? $_POST['dietary_preference'] : (isset($plan_selection['option_type']) ? $plan_selection['option_type'] : null);

    // Handle premium meal selection if premium plan
    if ($plan_type === 'premium' && isset($_POST['premium_meal_selection'])) {
        $premium_meal_selection = $_POST['premium_meal_selection'];
        // Optionally validate structure: 1 meal per day/time, all days/times present, etc.
        $_SESSION['premium_meal_selection'] = $premium_meal_selection;
    }

    // Get all required fields from user/session/plan_selection
    $delivery_time = isset($plan_selection['delivery_time']) ? $plan_selection['delivery_time'] : '08:00:00';

    // --- NEW PRICE CALCULATION LOGIC ---
    // Set base price per day
    $base_price = ($plan_type === 'premium') ? 320 : 250;
    $number_of_delivery_days = count_days_for_range($start_date, $end_date, $schedule);
    // Set multiplier based on schedule
    $multiplier = 1.00;
    $sched = strtolower(trim($schedule));
    if ($sched === 'extended week' || $sched === 'extended') {
        $multiplier = 1.20;
    } elseif ($sched === 'full week' || $sched === 'fullweek') {
        $multiplier = 1.75;
    } // else weekdays/default is 1.00
    $final_price = round($base_price * $number_of_delivery_days * $multiplier, 2);
    $plan_selection['final_price'] = $final_price;
    $_SESSION['plan_selection']['final_price'] = $final_price;
    $total_price = $final_price;
    $payment_status = isset($plan_selection['payment_status']) ? $plan_selection['payment_status'] : 'unpaid';
    $status = 'active';
    
        // Check for existing pending subscription for same user
        $check_sql = "SELECT subscription_id, plan_id FROM subscriptions WHERE user_id = ? AND status = 'pending' LIMIT 1";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $check_stmt->store_result();
        if ($check_stmt->num_rows > 0) {
            $check_stmt->bind_result($subscription_id, $old_plan_id);
            $check_stmt->fetch();
            
            // Update existing pending subscription details
            $update_sql = "UPDATE subscriptions SET plan_id = ?, dietary_preference = ?, start_date = ?, end_date = ?, schedule = ?, delivery_time = ?, total_price = ? WHERE subscription_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("isssssdi", $plan_id, $dietary_preference, $start_date, $end_date, $schedule, $delivery_time, $total_price, $subscription_id);
            $update_stmt->execute();

            // If plan changed from premium to basic, clear meals
            $new_plan_type = $plan_selection['plan_type'];
            $old_plan_query = $conn->prepare("SELECT plan_type FROM meal_plans WHERE plan_id = ?");
            $old_plan_query->bind_param("i", $old_plan_id);
            $old_plan_query->execute();
            $old_plan_result = $old_plan_query->get_result();
            $old_plan_type = $old_plan_result->fetch_assoc()['plan_type'];

            if ($old_plan_type === 'premium' && $new_plan_type === 'basic') {
                $delete_meals_sql = "DELETE FROM subscription_meals WHERE subscription_id = ?";
                $delete_meals_stmt = $conn->prepare($delete_meals_sql);
                $delete_meals_stmt->bind_param("i", $subscription_id);
                $delete_meals_stmt->execute();
            }

        } else {
            // Insert new pending subscription
            $insert_sql = "INSERT INTO subscriptions (user_id, plan_id, dietary_preference, start_date, end_date, schedule, delivery_time, total_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("iisssssd", $user_id, $plan_id, $dietary_preference, $start_date, $end_date, $schedule, $delivery_time, $total_price);
            if (!$stmt->execute()) {
                $error_message = "Error: Could not add subscription to cart. Please try again.";
                $subscription_id = null;
            } else {
                $subscription_id = $stmt->insert_id;
            }
        }
        $_SESSION['plan_selection']['subscription_id'] = $subscription_id;
        // If premium, update meal selections in subscription_meals
        if ($plan_type === 'premium' && isset($premium_meal_selection) && is_array($premium_meal_selection) && $subscription_id) {
            $conn->begin_transaction();
            try {
                // Remove old meal selections for this subscription (if any)
                $delete_sql = "DELETE FROM subscription_meals WHERE subscription_id = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("i", $subscription_id);
                $delete_stmt->execute();
                // Insert new meal selections
                $insert_meal_sql = "INSERT INTO subscription_meals (subscription_id, day_of_week, meal_type, meal_name) VALUES (?, ?, ?, ?)";
                $meal_stmt = $conn->prepare($insert_meal_sql);
                foreach ($premium_meal_selection as $day => $meals_by_time) {
                    foreach ($meals_by_time as $meal_type => $meal_name) {
                        $meal_stmt->bind_param("isss", $subscription_id, $day, $meal_type, $meal_name);
                        $meal_stmt->execute();
                    }
                }
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Error: Could not save meal selections. Please try again.";
            }
        }
        $_SESSION['message'] = 'Subscription added to cart!';
        header('Location: cart.php');
        exit();
}

// Fetch meals for the selected plan to display
$meals_sql = "SELECT m.meal_name, mc.category_name
              FROM meals m
              JOIN plan_meals pm ON m.meal_id = pm.meal_id
              JOIN meal_categories mc ON m.category_id = mc.category_id
              WHERE pm.plan_id = ? AND m.is_active = 1 AND pm.meal_id > 0
              ORDER BY mc.category_name, m.meal_name";
$stmt = $conn->prepare($meals_sql);
if ($plan_selection) {
    $stmt->bind_param("i", $plan_selection['plan_id']);
    $stmt->execute();
    $meals_result = $stmt->get_result();
    $meals = [];
    while ($row = $meals_result->fetch_assoc()) {
        if (!empty($row['meal_name'])) {
            $meals[$row['category_name']][] = $row;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="user_css/customize_meals_style.css?v=5">
    

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customize Your Plan - Tiffinly</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="user_css/user_dashboard_style.css">
    <style>
        .main-content { padding: 30px; }
        .content-box { background-color: white; border-radius: 12px; padding: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        h1, h2, h3 { color: #1D5F60; }
        .premium-access { border-left: 5px solid #ffc107; padding: 20px; background-color: #fffbeb; margin-bottom: 25px; }
        .basic-notice { border-left: 5px solid #17a2b8; padding: 20px; background-color: #e8f7fa; margin-bottom: 25px; }
        .meal-customization-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; }
        .day-column h3 { border-bottom: 2px solid #1D5F60; padding-bottom: 10px; margin-bottom: 15px; }
        .meal-card { background: #f9f9f9; border-radius: 8px; padding: 15px; margin-bottom: 15px; }
        .meal-card strong { color: #333; }
        .meal-card .actions { margin-top: 10px; }
        .swap-btn { background: none; border: 1px solid #1D5F60; color: #1D5F60; padding: 5px 10px; border-radius: 5px; cursor: pointer; transition: all 0.3s ease; }
        .swap-btn:hover { background: #1D5F60; color: white; }

        .plan-details-summary {
            width: 95%;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            position: relative;
            padding-bottom: 70px; /* Space for the button/price */
            margin-bottom: 20px;
        }
        .plan-details-summary h3 {
            margin-top: 0;
        }
        .plan-details-summary ul {
            padding-left: 20px;
            margin-bottom: 0;
        }
        .price-calculation-area {
            position: absolute;
            bottom: 15px;
            right: 20px;
            text-align: right;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .button-container {
            text-align: center;
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        .unified-btn {
            display: inline-block;
            min-width: 220px;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 17px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            margin: 5px;
        }
        
        .confirm-btn-style {
            background-color: #2C7A7B;
            color: white;
        }
        .confirm-btn-style:hover {
            background-color: #1D5F60;
        }
        .back-btn-style {
            background-color: #6c757d;
            color: white;
        }
        .back-btn-style:hover {
            background-color: #5a6268;
        }
        #final-price-container {
            font-size: 1.2em;
            font-weight: bold;
            color: #1D5F60;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-utensils"></i>&nbsp Tiffinly
            </div>
            <div class="user-profile">
                <div class="user-avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
                <div class="user-info">
                    <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
            </div>
            <div class="sidebar-menu">
                <a href="user_dashboard.php" class="menu-item">
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
                <a href="customize_meals.php" class="menu-item active">
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
        <div class="main-content">
            <div class="header">
                <div class="welcome-message">
                    <h1>Customize Your Meals</h1>
                    <p class="subtitle">Personalize your weekly menu and finalize your subscription</p>
                </div>
            </div>
            <div class="container">
            <?php if (!$show_customization): ?>
    <?php if ($has_active_subscription && !$edit_subscription_id): ?>
        <div class="content-box" style="text-align:center; padding:60px 40px; background: linear-gradient(120deg,#fffbe6 60%,#e0f7fa 100%); box-shadow: 0 6px 24px rgba(44,122,123,0.06); border: 2px solid #ffe082;">
            <i class="fas fa-exclamation-triangle" style="font-size:54px;color:#f39c12;"></i>
            <h2 style="margin-top:25px; color:#1D5F60; font-weight:700;">Active Subscription Detected</h2>
            <p style="font-size:20px; color:#444; margin:18px 0 28px;">You already have an active subscription. Please cancel it before customizing a new plan.</p>
        </div>
    <?php else: ?>
        <div class="content-box" style="text-align:center; padding:60px 40px; background: linear-gradient(120deg,#fffbe6 60%,#e0f7fa 100%); box-shadow: 0 6px 24px rgba(44,122,123,0.06); border: 2px solid #ffe082;">
            <i class="fas fa-exclamation-triangle" style="font-size:54px;color:#f39c12;"></i>
            <h2 style="margin-top:25px; color:#1D5F60; font-weight:700;">Please Select a Plan First</h2>
            <p style="font-size:20px; color:#444; margin:18px 0 28px;">You must select a meal plan before customizing your menu.<br>Click below to choose your plan and unlock premium customization features!</p>
            <a href="select_plan.php" class="unified-btn confirm-btn-style">
                <i class="fas fa-arrow-right"></i> Go to Plan Selection
            </a>
        </div>
    <?php endif; ?>
<?php else: ?>
            <div class="content-box">
                <h1>Finalize Your Subscription</h1>
                <p>Review your plan details below and confirm your subscription.</p>
                <hr style="margin: 20px 0;">

                <?php if ($plan_type === 'premium'): ?>
                    <div class="premium-access">
                        <h2><i class="fas fa-crown"></i> Premium Meal Customization</h2>
                        <p>As a premium member, you can customize your meals for the upcoming week. Select one meal for each day and time slot. You can change your selection at any time before confirming.</p>
                    </div>
                    <h3>Your Menu for the First Week</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="dietary_preference" value="<?php echo htmlspecialchars($plan_selection['option_type']); ?>">
                        <div class="meal-customization-grid">
                            <?php 
                            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                            $times = ['Breakfast', 'Lunch', 'Dinner'];
                            // Fetch all meals for each slot from plan_meals
                            $plan_meals = [];
                            $plan_id = $plan_selection['plan_id'];
                            $sql = "SELECT pm.day_of_week, pm.meal_type, m.meal_name
                                   FROM plan_meals pm
                                   JOIN meals m ON pm.meal_id = m.meal_id
                                   WHERE pm.plan_id = ? AND m.is_active = 1 AND pm.meal_id > 0
                                   ORDER BY FIELD(pm.day_of_week, 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'),
                                           FIELD(pm.meal_type, 'Breakfast', 'Lunch', 'Dinner')";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("i", $plan_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            while ($row = $result->fetch_assoc()) {
                                if (!empty($row['meal_name'])) {
                                    $plan_meals[$row['day_of_week']][$row['meal_type']][] = $row['meal_name'];
                                }
                            }
                            $stmt->close();
                            // For JS initialization
                            $initialPremiumSelection = isset($_SESSION['premium_meal_selection']) ? $_SESSION['premium_meal_selection'] : [];
                            ?>
                            <script>
                                window.initialPremiumSelection = <?php echo json_encode($initialPremiumSelection); ?>;
                            </script>
                            <?php
// Determine visible days based on schedule
$schedule = strtolower($plan_selection['schedule']);
$visible_days = $days;
if ($schedule === 'weekdays') {
    $visible_days = array_slice($days, 0, 5); // Mon-Fri
} elseif ($schedule === 'extended') {
    $visible_days = array_slice($days, 0, 6); // Mon-Sat
}
?>
<table class="premium-meal-table">
    <thead>
        <tr>
            <th style="text-align:left;">Day</th>
            <?php foreach ($times as $time): ?>
                <th><?php echo $time; ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($visible_days as $day): ?>
        <tr>
            <td class="day-label"><?php echo $day; ?></td>
            <?php foreach ($times as $time): ?>
                <td>
                    <?php $dropdown_id = 'dropdown_' . $day . '_' . $time; ?>
                    <select class="premium-meal-dropdown" name="premium_meal_selection[<?php echo $day; ?>][<?php echo $time; ?>]" id="<?php echo $dropdown_id; ?>">
                        <option value="">Select a meal</option>
                        <?php if (!empty($plan_meals[strtoupper($day)][$time])): ?>
                            <?php foreach ($plan_meals[strtoupper($day)][$time] as $meal): ?>
                                <option value="<?php echo htmlspecialchars($meal); ?>"
                                    <?php echo (isset($initialPremiumSelection[$day][$time]) && $initialPremiumSelection[$day][$time] === $meal) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($meal); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
                        </div>

                        <div class="price-calculation-area" style="justify-content: flex-end; position: static; padding: 20px 0;">
                            <button type="button" id="calculate-price-btn" class="unified-btn confirm-btn-style small-btn">
                                <i class="fas fa-calculator"></i> Calculate Price
                            </button>
                            <div id="final-price-container" style="display:none;">
                                <span id="final-price-value" class="final-price-value">₹0</span>
                            </div>
                        </div>

                        <div class="button-container">
                            <a href="select_plan.php" class="unified-btn back-btn-style">
                                <i class="fas fa-arrow-left"></i> Back to Select Plan
                            </a>
                            <button type="submit" name="confirm_subscription" class="unified-btn confirm-btn-style">
                                <i class="fas fa-check-circle"></i> Add to Cart
                            </button>
                        </div>
                    </form>
                    <script src="premium_meal_selection.js"></script>
                <?php elseif ($plan_type === 'basic'): ?>
                    <div class="basic-notice">
                        <h2><i class="fas fa-info-circle"></i> Basic Plan Confirmation</h2>
                        <p>Meal customization is a premium feature. With the Basic Plan, you will receive our standard curated menu based on your dietary preference.</p>
                    </div>

                    <div class="plan-details-summary">
                        <h3>Your Plan Details</h3>
                        <ul>
                            <li><strong>Plan:</strong> Basic (<?php echo htmlspecialchars(ucfirst($plan_selection['option_type'])); ?>)</li>
                            <li><strong>Schedule:</strong> <?php echo htmlspecialchars(ucfirst($plan_selection['schedule'])); ?></li>
                            <li><strong>Start Date:</strong> <?php echo date('F j, Y', strtotime($plan_selection['start_date'])); ?></li>
                            <li><strong>End Date:</strong> <?php echo date('F j, Y', strtotime($plan_selection['end_date'])); ?></li>
                            <li><strong>Duration:</strong> <?php echo count_days_for_range($plan_selection['start_date'], $plan_selection['end_date'], $plan_selection['schedule']); ?> days</li>
                        </ul>
                        <div class="price-calculation-area">
                            <button type="button" id="calculate-price-btn" class="unified-btn confirm-btn-style small-btn">
                                <i class="fas fa-calculator"></i> Calculate Price
                            </button>
                            <div id="final-price-container" style="display:none;">
                                <span id="final-price-value" class="final-price-value">₹0</span>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="">
                            <input type="hidden" name="dietary_preference" value="<?php echo htmlspecialchars($plan_selection['option_type']); ?>">
                        <div class="button-container">
                            <a href="select_plan.php" class="unified-btn back-btn-style">
                                <i class="fas fa-arrow-left"></i> Back to Select Plan
                            </a>
                            <button type="submit" name="confirm_subscription" class="unified-btn confirm-btn-style">
                                <i class="fas fa-check-circle"></i> Add to Cart
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

            <?php endif; ?>
                <?php if (isset($error_message)): ?>
                    <p class="error"><?php echo $error_message; ?></p>
                <?php endif; ?>
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
    </div>
    
<script>
// Price logic (date-range based, 28-day billing month)
// New price logic: base price × duration × multiplier
const BASE_PRICES = { basic: 250, premium: 320 };
const MULTIPLIERS = {
    'weekdays': 1.00,
    'extended week': 1.20,
    'extended': 1.20,
    'full week': 1.75,
    'fullweek': 1.75
};
// Get selection from PHP session
const planSelection = <?php echo json_encode($plan_selection); ?>;
function calculateFinalPrice() {
    if (!planSelection) return 0;
    const planType = planSelection.plan_type;
    const schedule = String(planSelection.schedule || '').toLowerCase().trim();
    const start = planSelection.start_date;
    const end = planSelection.end_date;
    if (!planType || !start || !end) return 0;
    const basePrice = BASE_PRICES[planType] || 0;
    const multiplier = MULTIPLIERS[schedule] || 1.00;
    const s = new Date(start);
    const e = new Date(end);
    if (isNaN(s) || isNaN(e) || e < s) return 0;
    // Calculate duration in days (inclusive)
    let days = 0;
    let cur = new Date(s);
    while (cur <= e) {
        const dow = cur.getDay(); // 0=Sun, 1=Mon, ..., 6=Sat
        let ok = false;
        if (multiplier === 1.00) {
            // Weekdays: Mon-Fri
            ok = dow >= 1 && dow <= 5;
        } else if (multiplier === 1.20) {
            // Extended: Mon-Sat
            ok = dow >= 1 && dow <= 6;
        } else if (multiplier === 1.75) {
            // Full week: Mon-Sun
            ok = true;
        }
        if (ok) days++;
        cur.setDate(cur.getDate() + 1);
    }
    const price = Math.round(basePrice * days * multiplier * 100) / 100;
    return price;
}
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('calculate-price-btn');
    const priceContainer = document.getElementById('final-price-container');
    const priceValue = document.getElementById('final-price-value');
    if (btn && priceContainer && priceValue) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const price = calculateFinalPrice();
            priceValue.textContent = `₹${price.toLocaleString()}`;
            priceContainer.style.display = 'block';
        });
    }
});
</script>
</body>
</html>
