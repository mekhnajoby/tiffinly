<?php
session_start();
include('../config/db_connect.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

// Fetch user details for the sidebar
$user_query = $conn->prepare("SELECT name, email FROM users WHERE user_id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user = $user_result->fetch_assoc();
$user_query->close();

// Handle remove from cart action
if (isset($_GET['action']) && $_GET['action'] === 'remove' && isset($_GET['subscription_id'])) {
    $subscription_id = $_GET['subscription_id'];
    
    // Begin transaction for safe deletion
    $conn->begin_transaction();
    try {
        // First, verify the subscription belongs to the user and get its status
        $verify_sql = "SELECT status, payment_status FROM subscriptions WHERE subscription_id = ? AND user_id = ?";
        $verify_stmt = $conn->prepare($verify_sql);
        $verify_stmt->bind_param("ii", $subscription_id, $user_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows === 0) {
            throw new Exception("Subscription not found or doesn't belong to user");
        }
        
        $subscription_data = $verify_result->fetch_assoc();
        $verify_stmt->close();
        
        // Only allow removal of unpaid subscriptions or pending subscriptions
        if ($subscription_data['payment_status'] === 'paid' && $subscription_data['status'] === 'active') {
            throw new Exception("Cannot remove paid active subscriptions. Please cancel through manage subscriptions.");
        }

        // Delete related delivery assignments first
        $delete_assignments_sql = "DELETE FROM delivery_assignments WHERE subscription_id = ?";
        $delete_assignments_stmt = $conn->prepare($delete_assignments_sql);
        $delete_assignments_stmt->bind_param("i", $subscription_id);
        if (!$delete_assignments_stmt->execute()) {
            throw new Exception("Error deleting delivery assignments: " . $delete_assignments_stmt->error);
        }
        $delete_assignments_stmt->close();

        // Delete related deliveries
        $delete_deliveries_sql = "DELETE FROM deliveries WHERE subscription_id = ?";
        $delete_deliveries_stmt = $conn->prepare($delete_deliveries_sql);
        $delete_deliveries_stmt->bind_param("i", $subscription_id);
        if (!$delete_deliveries_stmt->execute()) {
            throw new Exception("Error deleting deliveries: " . $delete_deliveries_stmt->error);
        }
        $delete_deliveries_stmt->close();

        // Delete from subscription_meals
        $delete_meals_sql = "DELETE FROM subscription_meals WHERE subscription_id = ?";
        $delete_meals_stmt = $conn->prepare($delete_meals_sql);
        $delete_meals_stmt->bind_param("i", $subscription_id);
        if (!$delete_meals_stmt->execute()) {
            throw new Exception("Error deleting from subscription_meals: " . $delete_meals_stmt->error);
        }
        $delete_meals_stmt->close();

        // Finally, delete from subscriptions
        $delete_sub_sql = "DELETE FROM subscriptions WHERE subscription_id = ? AND user_id = ?";
        $delete_sub_stmt = $conn->prepare($delete_sub_sql);
        $delete_sub_stmt->bind_param("ii", $subscription_id, $user_id);
        if (!$delete_sub_stmt->execute()) {
            throw new Exception("Error deleting from subscriptions: " . $delete_sub_stmt->error);
        }
        
        if ($delete_sub_stmt->affected_rows === 0) {
            throw new Exception("No subscription was deleted - it may have already been removed");
        }
        $delete_sub_stmt->close();

        $conn->commit();

        // Clear session data
        unset($_SESSION['plan_selection']);
        unset($_SESSION['premium_meal_selection']);
        
        // Redirect with a success message
        header('Location: cart.php?message=removed');
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Cart removal error: " . $e->getMessage());
        // Redirect with an error message
        header('Location: cart.php?message=error&details=' . urlencode($e->getMessage()));
        exit();
    }
}

// Display messages from GET parameters
if (isset($_GET['message'])) {
    if ($_GET['message'] === 'removed') {
        $message = 'Plan removed from your cart successfully.';
    } elseif ($_GET['message'] === 'error') {
        $error_details = isset($_GET['details']) ? htmlspecialchars($_GET['details']) : 'Unknown error occurred';
        $message = 'Could not remove the plan: ' . $error_details . '. Please try again.';
    }
}


// Fetch subscription data from the database
$subscription = null;
$sql = "SELECT s.*, mp.plan_name, mp.plan_type FROM subscriptions s JOIN meal_plans mp ON s.plan_id = mp.plan_id WHERE s.user_id = ? AND (s.payment_status IS NULL OR s.payment_status != 'paid') ORDER BY s.created_at DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $subscription = $result->fetch_assoc();
}
$stmt->close();

// Reconstruct plan_selection from subscription if it exists
$plan_selection = null;
if ($subscription) {
    $plan_selection = [
        'plan_id' => $subscription['plan_id'],
        'plan_name' => $subscription['plan_name'],
        'plan_type' => $subscription['plan_type'],
        'option_type' => $subscription['dietary_preference'] ?? '',
        'schedule' => $subscription['schedule'],
        'start_date' => $subscription['start_date'],
        'end_date' => $subscription['end_date'],
        'duration_weeks' => round((strtotime($subscription['end_date']) - strtotime($subscription['start_date'])) / 604800),
        'final_price' => $subscription['total_price'],
        'subscription_id' => $subscription['subscription_id']
    ];
}

// Fetch meal selections
$meals = [];
if ($plan_selection && isset($plan_selection['subscription_id'])) {
    if ($plan_selection['plan_type'] === 'premium') {
        $meal_sql = "SELECT day_of_week, meal_type, meal_name FROM subscription_meals WHERE subscription_id = ?";
        $meal_stmt = $conn->prepare($meal_sql);
        $meal_stmt->bind_param("i", $plan_selection['subscription_id']);
        $meal_stmt->execute();
        $meal_result = $meal_stmt->get_result();
        while ($row = $meal_result->fetch_assoc()) {
            $meals[$row['day_of_week']][$row['meal_type']] = $row['meal_name'];
        }
        $meal_stmt->close();
    }
}

// For empty cart animation
function cart_empty_svg() {
    return '<svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="#1D5F60" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h2l.4 2M7 13h10l4-8H5.4"/><path d="M7 13L5.4 5M7 13l-2.3 4.6A1 1 0 0 0 6 19h12"/></svg>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart - Tiffinly</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="user_css/user_dashboard_style.css">
    <style>
        .cart-main { padding: 40px; }
        .cart-box { background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); padding: 32px; }
        .cart-empty { text-align: center; padding: 60px 30px; color: #1D5F60; }
        .cart-empty svg { margin-bottom: 18px; }
        .cart-table { width: 100%; border-collapse: collapse; margin: 18px 0; }
        .cart-table th, .cart-table td { padding: 14px 10px; border-bottom: 1px solid #e0e0e0; text-align: left; vertical-align: middle; }
        .cart-table th { background: #f7fafc; color: #1D5F60; font-weight: 600; }
        .plan-title { font-size: 1.3em; font-weight: 600; color: #1D5F60; margin-bottom: 15px; }
        .button-group { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-top: 25px; }
        .details-toggle { text-align: right; }
        #meal-details-container { display: none; margin-top: 20px; }

        /* Unified Button Styles */
        .unified-btn {
            display: inline-block;
            min-width: 180px;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 16px;
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
        .remove-btn-style {
            background-color: #e74c3c;
            color: white;
        }
        .remove-btn-style:hover {
            background-color: #c0392b;
        }
        .details-btn-style {
            background-color: #f0f0f0;
            color: #333;
            border: 1px solid #ddd;
        }
        .details-btn-style:hover {
            background-color: #e9e9e9;
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="sidebar">
        <div class="sidebar-header"><i class="fas fa-utensils"></i>&nbsp;Tiffinly</div>
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
            <a href="user_dashboard.php" class="menu-item"><i class="fas fa-dashboard"></i> Dashboard</a>
            <a href="profile.php" class="menu-item"><i class="fas fa-user"></i> My Profile</a>
            <div class="menu-category">Order Management</div>
            <a href="browse_plans.php" class="menu-item"><i class="fas fa-utensils"></i> Browse Plans</a>
            <a href="compare_plans.php" class="menu-item"><i class="fas fa-exchange-alt"></i> Compare Menu</a>
            <a href="select_plan.php" class="menu-item"><i class="fas fa-check-circle"></i> Select Plan</a>
            <a href="customize_meals.php" class="menu-item"><i class="fas fa-sliders-h"></i> Customize Meals</a>
            <a href="cart.php" class="menu-item active"><i class="fas fa-shopping-cart"></i> My Cart</a>
            <div class="menu-category">Delivery & Payments</div>
            <a href="delivery_preferences.php" class="menu-item"><i class="fas fa-truck"></i> Delivery Preferences</a>
            <a href="payment.php" class="menu-item"><i class="fas fa-credit-card"></i> Payment</a>
            <div class="menu-category">Order History</div>
            <a href="track_order.php" class="menu-item"><i class="fas fa-map-marker-alt"></i> Track Order</a>
            <a href="manage_subscriptions.php" class="menu-item">
                    <i class="fas fa-tools"></i> Manage Subscriptions
                </a>
            <a href="subscription_history.php" class="menu-item"><i class="fas fa-calendar-alt"></i> Subscription History</a>
            <div class="menu-category">Feedback & Support</div>
            <a href="feedback.php" class="menu-item"><i class="fas fa-comment-alt"></i> Feedback</a>
            <a href="support.php" class="menu-item"><i class="fas fa-envelope"></i> Send Inquiry</a>
            <a href="my_inquiries.php" class="menu-item"><i class="fas fa-inbox"></i> My Inquiries</a>
            <div style="margin-top: 30px;">
                <a href="logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
    <div class="main-content cart-main">
        <div class="header">
            <div class="welcome-message">
                <h1>My Cart</h1>
                <p class="subtitle">Review your selected meal plan and proceed to payment</p>
            </div>
        </div>
        <div class="cart-box">
            <?php if ($message): ?>
                <div class="alert" style="background:#e8f7fa;color:#1D5F60;padding:12px 20px;border-radius:6px;margin-bottom:18px;">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($plan_selection): ?>
                <div class="plan-title"><i class="fas fa-utensils"></i> <?php echo ucfirst(htmlspecialchars($plan_selection['plan_type'])); ?>&nbsp Plan </div>
                <table class="cart-table">
                    <tr><th>Start Date</th><td><?php echo date('F j, Y', strtotime($plan_selection['start_date'])); ?></td></tr>
                    <tr><th>End Date</th><td><?php echo date('F j, Y', strtotime($plan_selection['end_date'])); ?></td></tr>
                    <tr><th>Schedule</th><td><?php echo htmlspecialchars($plan_selection['schedule']); ?></td></tr>
                    <tr><th>Duration</th><td>
                    <?php
                    $start = strtotime($plan_selection['start_date']);
                    $end = strtotime($plan_selection['end_date']);
                    $schedule = strtolower($plan_selection['schedule']);
                    $duration_days = 0;
                    for ($d = $start; $d <= $end; $d += 86400) {
                        $dayOfWeek = date('N', $d); // 1 (Mon) to 7 (Sun)
                        if ($schedule === 'weekdays' && $dayOfWeek >= 1 && $dayOfWeek <= 5) {
                            $duration_days++;
                        } elseif ($schedule === 'extended' && $dayOfWeek >= 1 && $dayOfWeek <= 6) {
                            $duration_days++;
                        } elseif ($schedule === 'full week') {
                            $duration_days++;
                        }
                    }
                    echo $duration_days . ' days';
                    ?>
                    </td></tr>
                    <tr><th>Final Price</th><td>₹<?php echo number_format($plan_selection['final_price'], 2); ?></td></tr>
                </table>

                <div class="button-group">
                    <div class="details-toggle">
                        <button type="button" class="unified-btn details-btn-style" id="show-details-btn">
                            <i class="fas fa-info-circle"></i> Show Details
                        </button>
                    </div>
                    <div>
                        <a href="cart.php?action=remove&subscription_id=<?php echo $plan_selection['subscription_id']; ?>" 
                           class="unified-btn remove-btn-style" 
                           onclick="return confirm('Are you sure you want to remove this plan from your cart?');">
                           <i class="fas fa-trash"></i> Remove Plan
                        </a>
                    </div>
                </div>

                <div id="meal-details-container">
                    <h3 style="margin-top:28px;">Your Meal Schedule</h3>
                    <table class="cart-table">
                        <thead><tr><th>Day</th><th>Breakfast</th><th>Lunch</th><th>Dinner</th></tr></thead>
                        <tbody>
                        <?php
                        $schedule = strtolower($plan_selection['schedule']);
                        $all_days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
                        $visible_days = $all_days;
                        if ($schedule === 'weekdays') {
                            $visible_days = array_slice($all_days, 0, 5);
                        } elseif ($schedule === 'extended') {
                            $visible_days = array_slice($all_days, 0, 6);
                        }

                        if ($plan_selection['plan_type'] === 'premium') {
                            foreach ($visible_days as $day) {
                                echo '<tr><td>' . $day . '</td>';
                                echo '<td>' . htmlspecialchars($meals[$day]['Breakfast'] ?? '-') . '</td>';
                                echo '<td>' . htmlspecialchars($meals[$day]['Lunch'] ?? '-') . '</td>';
                                echo '<td>' . htmlspecialchars($meals[$day]['Dinner'] ?? '-') . '</td>';
                                echo '</tr>';
                            }
                        } else { // Basic Plan
                            $option_type = $plan_selection['option_type'] ?? 'veg';
                            $plan_id = $plan_selection['plan_id'];
                            $meal_sql = "SELECT pm.day_of_week, pm.meal_type, m.meal_name, mc.option_type
                                        FROM plan_meals pm
                                        JOIN meals m ON pm.meal_id = m.meal_id
                                        JOIN meal_categories mc ON m.category_id = mc.category_id
                                        WHERE pm.plan_id = ? AND m.is_active = 1 AND pm.meal_id > 0
                                        ORDER BY FIELD(pm.day_of_week, 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'),
                                                FIELD(pm.meal_type, 'Breakfast', 'Lunch', 'Dinner')";
                            $stmt = $conn->prepare($meal_sql);
                            $stmt->bind_param("i", $plan_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $basic_meals = [];
                            while ($row = $result->fetch_assoc()) {
                                if (!empty($row['meal_name'])) {
                                    $basic_meals[$row['day_of_week']][$row['meal_type']][] = ['name' => $row['meal_name'], 'type' => $row['option_type']];
                                }
                            }
                            $stmt->close();

                            foreach ($visible_days as $day) {
                                $db_day = strtoupper($day);
                                echo '<tr><td>' . $day . '</td>';
                                foreach (["Breakfast", "Lunch", "Dinner"] as $time) {
                                    $slot_meals = $basic_meals[$db_day][$time] ?? [];
                                    $meal_to_show = '-';
                                    $found_non_veg = false;
                                    if ($option_type === 'non_veg') {
                                        foreach ($slot_meals as $meal) {
                                            if ($meal['type'] === 'non_veg') {
                                                $meal_to_show = htmlspecialchars($meal['name']);
                                                $found_non_veg = true;
                                                break;
                                            }
                                        }
                                        if (!$found_non_veg) {
                                            foreach ($slot_meals as $meal) {
                                                if ($meal['type'] === 'veg') {
                                                    $meal_to_show = htmlspecialchars($meal['name']);
                                                    break;
                                                }
                                            }
                                        }
                                    } else if ($option_type === 'veg') {
                                        foreach ($slot_meals as $meal) {
                                            if ($meal['type'] === 'veg') {
                                                $meal_to_show = htmlspecialchars($meal['name']);
                                                break;
                                            }
                                        }
                                    }
                                    echo '<td>' . $meal_to_show . '</td>';
                                }
                                echo '</tr>';
                            }
                        }
                        ?>
                        </tbody>
                    </table>
                </div>

                <div class="button-group" style="justify-content: center; border-top: 1px solid #eee; padding-top: 20px; margin-top: 20px;">
                    <a href="delivery_preferences.php?subscription_id=<?php echo $plan_selection['subscription_id']; ?>" class="unified-btn confirm-btn-style">
                        <i class="fas fa-truck"></i> Set delivery preferences
                    </a>
                </div>

            <?php else: ?>
                <div class="cart-empty">
                    <?php echo cart_empty_svg(); ?>
                    <h2>Your Cart is Empty</h2>
                    <p style="font-size:18px;">No meal plan has been added to your cart yet.<br>Go to <a href="browse_plans.php" style="color:#1D5F60;font-weight:600;">Browse Plans</a> to get started!</p>
                </div>
            <?php endif; ?>
        </div>
        <footer style="text-align: center; padding: 20px; margin-top: 40px; color: #777; font-size: 14px; border-top: 1px solid #eee;">
            <p>&copy; 2025 Tiffinly. All rights reserved.</p>
        </footer>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('show-details-btn');
    var container = document.getElementById('meal-details-container');
    if(btn && container) {
        btn.addEventListener('click', function() {
            if (container.style.display === 'none') {
                container.style.display = 'block';
                this.innerHTML = '<i class="fas fa-chevron-up"></i> Hide Details';
            } else {
                container.style.display = 'none';
                this.innerHTML = '<i class="fas fa-info-circle"></i> Show Details';
            }
        });
    }
});
</script>
</body>
</html>