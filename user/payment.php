<?php
session_start();

$subscription_cancelled = false;
if (isset($_GET['status']) && $_GET['status'] === 'cancelled') {
    unset($_SESSION['plan_selection']);
    unset($_SESSION['premium_meal_selection']);
    unset($_SESSION['whatsapp_notification_link']); // Clear WhatsApp link on cancellation
    $subscription_cancelled = true;
}

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$db = new mysqli('localhost', 'root', '', 'tiffinly');
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

$user_id = $_SESSION['user_id'];
$user_query = $db->prepare("SELECT name, email, phone FROM users WHERE user_id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user = $user_result->fetch_assoc();

$plan_selection = isset($_SESSION['plan_selection']) ? $_SESSION['plan_selection'] : null;
$subscription = null;

if (!$subscription_cancelled) {
    if (empty($plan_selection) || empty($plan_selection['subscription_id'])) {
        $sql = "SELECT s.*, mp.plan_name, mp.plan_type, mp.base_price FROM subscriptions s JOIN meal_plans mp ON s.plan_id = mp.plan_id WHERE s.user_id = ? AND (s.status = 'pending' OR s.status = 'active') ORDER BY s.subscription_id DESC LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $subscription = $result->fetch_assoc();
        if ($subscription) {
            $plan_selection = [
                'plan_id' => $subscription['plan_id'],
                'plan_name' => $subscription['plan_name'],
                'plan_type' => $subscription['plan_type'],
                'option_type' => $subscription['dietary_preference'] ?? '',
                'schedule' => $subscription['schedule'] ?? '',
                'start_date' => $subscription['start_date'] ?? '',
                'end_date' => $subscription['end_date'] ?? '',
                'delivery_time' => $subscription['delivery_time'] ?? '',
                'final_price' => isset($subscription['total_price']) ? $subscription['total_price'] : (isset($subscription['base_price']) ? $subscription['base_price'] : 0),
                'subscription_id' => $subscription['subscription_id']
            ];
            $_SESSION['plan_selection'] = $plan_selection;
        }
    }

    if (empty($plan_selection['plan_name']) || $plan_selection['plan_name'] === 'N/A') {
        if (!empty($plan_selection['plan_id'])) {
            $plan_stmt = $db->prepare("SELECT plan_name FROM meal_plans WHERE plan_id = ? LIMIT 1");
            $plan_stmt->bind_param("i", $plan_selection['plan_id']);
            $plan_stmt->execute();
            $plan_result = $plan_stmt->get_result();
            if ($plan_row = $plan_result->fetch_assoc()) {
                $plan_selection['plan_name'] = $plan_row['plan_name'];
            }
        }
    }
}

$plan_name = $plan_selection['plan_name'] ?? 'N/A';
$plan_price = isset($plan_selection['final_price']) ? $plan_selection['final_price'] : (isset($plan_selection['total_price']) ? $plan_selection['total_price'] : (isset($plan_selection['base_price']) ? $plan_selection['base_price'] : 0));

$pref_query = $db->prepare("SELECT meal_type, address_id, time_slot FROM delivery_preferences WHERE user_id = ?");
$pref_query->bind_param("i", $user_id);
$pref_query->execute();
$pref_result = $pref_query->get_result();
$preferences = [];
while ($row = $pref_result->fetch_assoc()) {
    $preferences[$row['meal_type']] = [
        'address_id' => $row['address_id'],
        'time_slot' => $row['time_slot']
    ];
}

$addresses = [];
if (!empty($preferences)) {
    $address_ids = array_column($preferences, 'address_id');
    $address_ids = array_filter($address_ids);
    if ($address_ids) {
        $in = implode(',', array_fill(0, count($address_ids), '?'));
        $types = str_repeat('i', count($address_ids));
        $address_stmt = $db->prepare("SELECT * FROM addresses WHERE address_id IN ($in)");
        $address_stmt->bind_param($types, ...$address_ids);
        $address_stmt->execute();
        $address_result = $address_stmt->get_result();
        while ($row = $address_result->fetch_assoc()) {
            $addresses[$row['address_id']] = $row;
        }
    }
}

$subscription_id = $plan_selection['subscription_id'] ?? null;
$payment_status = 'pending';

if ($subscription_id) {
    $status_stmt = $db->prepare("SELECT payment_status FROM subscriptions WHERE subscription_id = ? AND user_id = ? LIMIT 1");
    $status_stmt->bind_param("ii", $subscription_id, $user_id);
    $status_stmt->execute();
    $status_result = $status_stmt->get_result();
    if ($row = $status_result->fetch_assoc()) {
        $payment_status = $row['payment_status'];
    } else {
        $subscription_id = null;
        unset($_SESSION['plan_selection']);
    }
    $status_stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_now']) && $subscription_id && !empty($_POST['razorpay_payment_id'])) {
    $payment_method = 'Razorpay';
    $amount = $plan_price;
    $transaction_ref = $_POST['razorpay_payment_id'];
    // Insert payment record
    $insert_stmt = $db->prepare("INSERT INTO payments (user_id, subscription_id, amount, payment_method, payment_status, transaction_ref) VALUES (?, ?, ?, ?, 'success', ?)");
    $insert_stmt->bind_param("iidss", $user_id, $subscription_id, $amount, $payment_method, $transaction_ref);
    if (!$insert_stmt->execute()) {
        die('Payment insert failed: ' . $insert_stmt->error);
    }
    // Update existing pending subscription to active/paid
    $update_stmt = $db->prepare("UPDATE subscriptions SET payment_status = 'paid', status = 'active' WHERE subscription_id = ?");
    $update_stmt->bind_param("i", $subscription_id);
    $update_stmt->execute();
    // Clean up session
    unset($_SESSION['plan_selection']);
    unset($_SESSION['premium_meal_selection']);

    // Auto-generate delivery records for the subscription period
    $sub_details_stmt = $db->prepare("SELECT start_date, end_date, schedule, delivery_time FROM subscriptions WHERE subscription_id = ?");
    $sub_details_stmt->bind_param("i", $subscription_id);
    $sub_details_stmt->execute();
    $sub_result = $sub_details_stmt->get_result();
    $subscription_details = $sub_result->fetch_assoc();
    $sub_details_stmt->close();

    if ($subscription_details) {
        $start_date = new DateTime($subscription_details['start_date']);
        $end_date = new DateTime($subscription_details['end_date']);
        $schedule = $subscription_details['schedule'];
        

        // Loop through each day of the subscription period
        $interval = new DateInterval('P1D');
        $date_range = new DatePeriod($start_date, $interval, $end_date->modify('+1 day'));

        $insert_delivery_stmt = $db->prepare("INSERT INTO deliveries (subscription_id, delivery_date, status, delivery_time) VALUES (?, ?, 'scheduled', ?)");

        foreach ($date_range as $date) {
            $day_of_week = $date->format('N'); // 1 (Monday) to 7 (Sunday)

            $should_deliver = false;
            switch ($schedule) {
                case 'Weekdays':
                    if ($day_of_week <= 5) $should_deliver = true;
                    break;
                case 'Extended':
                    if ($day_of_week <= 6) $should_deliver = true;
                    break;
                case 'Full Week':
                    $should_deliver = true;
                    break;
            }

            if ($should_deliver) {
                $delivery_date_str = $date->format('Y-m-d');
                $insert_delivery_stmt->bind_param("iss", $subscription_id, $delivery_date_str, $delivery_time);
                $insert_delivery_stmt->execute();
            }
        }
        $insert_delivery_stmt->close();
    }

    // Generate WhatsApp notification link
    if (!empty($user['phone'])) {
        require_once('../config/whatsapp_notification.php');
        
        // Prepare subscription details for the notification
        // Calculate duration in days
        $start = new DateTime($subscription_details['start_date']);
        $end = new DateTime($subscription_details['end_date']);
        $schedule = strtolower($subscription_details['schedule']);
        $duration_days = 0;
        $interval = new DateInterval('P1D');
        $date_range = new DatePeriod($start, $interval, $end->modify('+1 day'));
        foreach ($date_range as $date) {
            $dow = (int)$date->format('N');
            $ok = (
                $schedule === 'full week' ||
                $schedule === 'fullweek' ||
                $schedule === 'daily' ||
                ($schedule === 'weekdays' && $dow <= 5) ||
                ($schedule === 'extended' && $dow <= 6)
            );
            if ($ok) $duration_days++;
        }
        $subscriptionDetails = [
            'plan_name' => $plan_selection['plan_name'],
            'start_date' => $subscription_details['start_date'],
            'end_date' => $subscription_details['end_date'],
            'schedule' => $subscription_details['schedule'],
            'amount' => number_format($plan_price, 2),
        ];
        
        // Generate the WhatsApp link using sendSubscriptionConfirmation which includes duration
        $whatsappLink = sendSubscriptionConfirmation($user['phone'], $subscriptionDetails);
        
        // Store the link in session to display on the success page
        $_SESSION['whatsapp_notification_link'] = $whatsappLink;
    }
    
    $payment_status = 'success';
    unset($_SESSION['plan_selection']);
    unset($_SESSION['premium_meal_selection']);
    // Keep WhatsApp notification link in session for the success page
    header('Location: payment.php?paid=1');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiffinly - Payment</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="user_css/user_dashboard_style.css">
    <link rel="stylesheet" href="user_css/profile_style.css">
    <link rel="stylesheet" href="user_css/compare_plans_style.css">
    <style>
        .payment-container {
            max-width: 900px;
            margin: 30px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            padding: 32px 40px 32px 40px;
        }
        .section-title {
            font-size: 24px;
            color: var(--dark-color);
            margin-bottom: 18px;
        }
        .amount-box {
            background: #f5f6fa;
            border-radius: 8px;
            padding: 18px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 22px;
            color: #1D5F60;
        }
        .details-table {
            width: 100%;
            margin-bottom: 24px;
            border-collapse: collapse;
        }
        .details-table th, .details-table td {
            padding: 10px 14px;
            text-align: left;
        }
        .details-table th {
            background: #f9f9f9;
            color: #1D5F60;
            font-weight: 600;
        }
        .details-table tr {
            border-bottom: 1px solid #eee;
        }
        .payment-methods {
            display: flex;
            gap: 28px;
            margin-bottom: 32px;
            flex-wrap: wrap;
        }
        .method-box {
            flex: 1 1 220px;
            background: #f9f9f9;
            border-radius: 8px;
            padding: 18px 20px;
            box-shadow: 0 2px 8px rgba(44,122,123,0.04);
            margin-bottom: 12px;
        }
        .method-box label {
            font-weight: 500;
            color: #1D5F60;
            margin-left: 8px;
        }
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 18px;
        }
        .success-message {
            background: #e8f9e5;
            color: #27ae60;
            padding: 14px 18px;
            border-radius: 8px;
            font-size: 18px;
            margin-bottom: 16px;
            text-align: center;
        }
        .cancellation-message {
            background: #fff3cd;
            color: #856404;
            padding: 14px 18px;
            border-radius: 8px;
            font-size: 18px;
            margin-bottom: 16px;
            text-align: center;
        }
        .error-message {
            margin-top:40px;
            font-size:18px;
            color:#e74c3c;
            background:#fff3f3;
            padding:20px;
            border-radius:8px;
        }
        @media (max-width: 900px) {
            .payment-container { padding: 18px 6vw; }
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
        <div class="user-avatar">
            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
        </div>
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
                <a href="payment.php" class="menu-item active">
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
                    <h1>Payment</h1>
                    <p class="subtitle">Complete your payment to activate your meal plan</p>
                </div>
            </div>
        
            <div class="payment-container">
                <h2 class="section-title"><i class="fas fa-credit-card"></i> Payment</h2>

                <?php if ($subscription_cancelled): ?>
                    <div class="cancellation-message">
                        <i class="fas fa-info-circle"></i> Your subscription has been successfully cancelled.
                    </div>
                    <div style="text-align: center; margin-bottom: 20px;">
                        <a href="subscription_history.php" class="back-btn" style="display:inline-flex;align-items:center;gap:10px;min-width:190px;justify-content:center;font-size:18px;">
                            <i class="fas fa-history"></i> View Payment History
                        </a>
                    </div>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i> No active subscription found. Please <a href="browse_plans.php" style="color:#1d5f60;text-decoration:underline;">select a meal plan</a> first.
                    </div>
                <?php elseif ($subscription_id && ($payment_status === 'paid' || isset($_GET['paid']))): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> Payment successful! Your subscription is now active.<br>Thank you for choosing Tiffinly.<br>
                        <span style="font-size:16px;color:#219150;font-weight:600;">Status: <span style="color:#fff;background:#27ae60;padding:2px 10px;border-radius:8px;">PAID</span></span>
                        
                        <?php
                        // Generate WhatsApp notification link for paid subscriptions (persistent across page refreshes)
                        $whatsappLink = null;
                        if (!empty($user['phone'])) {
                            // Check if we have a WhatsApp link in session, or generate a new one
                            if (isset($_SESSION['whatsapp_notification_link'])) {
                                $whatsappLink = $_SESSION['whatsapp_notification_link'];
                            } else {
                                // Regenerate the WhatsApp link from subscription data
                                $sub_details_stmt = $db->prepare("SELECT start_date, end_date, schedule FROM subscriptions WHERE subscription_id = ?");
                                $sub_details_stmt->bind_param("i", $subscription_id);
                                $sub_details_stmt->execute();
                                $sub_result = $sub_details_stmt->get_result();
                                $subscription_details = $sub_result->fetch_assoc();
                                $sub_details_stmt->close();
                                
                                if ($subscription_details) {
                                    require_once('../config/whatsapp_notification.php');
                                    
                                    // Prepare subscription details for the notification
                                    $subscriptionDetails = [
                                        'plan_name' => $plan_name,
                                        'start_date' => $subscription_details['start_date'],
                                        'end_date' => $subscription_details['end_date'],
                                        'schedule' => $subscription_details['schedule'],
                                        'amount' => number_format($plan_price, 2)
                                    ];
                                    
                                    // Generate the WhatsApp link using sendSubscriptionConfirmation which includes duration
                                    $whatsappLink = sendSubscriptionConfirmation($user['phone'], $subscriptionDetails);
                                    
                                    // Store the link in session for future page loads
                                    $_SESSION['whatsapp_notification_link'] = $whatsappLink;
                                }
                            }
                        }
                        
                        if ($whatsappLink): ?>
                            <div style="margin: 25px 0; padding: 15px; background: #e8f5e9; border-radius: 8px; text-align: center;">
                                <p style="margin: 0 0 15px 0; font-size: 16px; color: #2e7d32;">
                                    <i class="fas fa-mobile-alt" style="margin-right: 8px;"></i>
                                    Click below to receive order confirmation on WhatsApp:
                                </p>
                                <a href="<?php echo htmlspecialchars($whatsappLink); ?>"
                                   target="_blank"
                                   style="display: inline-flex; align-items: center; gap: 10px;
                                          background: #25D366; color: white; padding: 12px 24px;
                                          border-radius: 50px; text-decoration: none; font-weight: 500;
                                          box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                                    <i class="fab fa-whatsapp" style="font-size: 24px;"></i>
                                    Get Order Details on WhatsApp
                                </a>
                                <p style="margin: 15px 0 0 0; font-size: 14px; color: #666;">
                                    <i class="fas fa-info-circle"></i> This will open WhatsApp with a pre-filled message
                                </p>
                            </div>
                        <?php endif; ?>
                        
                        <div style="display: flex; gap: 18px; margin-top: 32px; justify-content: center; flex-wrap: wrap;">
                            <a href="track_order.php" class="back-btn" style="display:inline-flex;align-items:center;gap:10px;min-width:190px;justify-content:center;font-size:18px;">
                                <i class="fas fa-shipping-fast"></i> Track Order
                            </a>
                            <a href="user_dashboard.php" class="back-btn" style="display:inline-flex;align-items:center;gap:10px;min-width:190px;justify-content:center;font-size:18px;">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </div>
                    </div>
                <?php elseif ($subscription_id): ?>
                    <div class="amount-box">
                        <span>Plan: <?php echo htmlspecialchars($plan_name); ?>&nbsp;(<?php
                            $schedule = '';
                            if (!empty($plan_selection['schedule'])) {
                                $schedule = $plan_selection['schedule'];
                            } elseif (!empty($subscription['schedule'])) {
                                $schedule = $subscription['schedule'];
                            }
                            if ($schedule === 'weekdays') echo 'Weekdays (Mon-Fri)';
                            elseif ($schedule === 'extended') echo 'Extended (Mon-Sat)';
                            elseif ($schedule === 'fullweek') echo 'Full Week (Mon-Sun)';
                            else echo ucfirst($schedule ?: 'Not set');
                        ?>)</span>
                        <span>Amount: ₹<?php echo number_format($plan_price, 2); ?></span>
                    </div>
                    
                    <h3 class="section-title" style="font-size:20px; margin-top:30px;">Delivery Details</h3>
                    <table class="details-table">
                        <thead>
                            <tr>
                                <th>Meal</th>
                                <th>Delivery Address</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach(['breakfast', 'lunch', 'dinner'] as $meal): ?>
                                <tr>
                                    <td style="text-transform:capitalize;"><?php echo $meal; ?></td>
                                    <td>
                                        <?php 
                                        $addr_id = $preferences[$meal]['address_id'] ?? null;
                                        if ($addr_id && isset($addresses[$addr_id])) {
                                            $a = $addresses[$addr_id];
                                            echo htmlspecialchars($a['line1'] . ', ' . ($a['line2'] ? $a['line2'] . ', ' : '') . $a['city'] . ', ' . $a['state'] . ' - ' . $a['pincode']);
                                        } else {
                                            echo '<span style="color:#aaa;">Not set</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($preferences[$meal]['time_slot'] ?? 'Not set'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <form method="POST" action="" id="paymentForm">
                        <h3 class="section-title" style="font-size:20px; margin-top:30px;">Payment Here</h3>
                        <div class="payment-methods">
                            <div class="method-box">
                                <div style="display:flex; justify-content:center; align-items:center; margin-top:40px;">
                                  <button type="button" id="razorpayRealBtn" style="background:#1a4f50;color:#fff;border:none;padding:14px 38px;border-radius:8px;font-size:18px;cursor:pointer;display:inline-flex;align-items:center;gap:12px;min-width:190px; box-shadow:0 2px 8px #e3e3e3;">
                                    <img src='https://img.icons8.com/ios-filled/50/ffffff/money-transfer.png' alt='Pay' style='height:26px;width:26px;vertical-align:middle;'>Pay with Razorpay
                                  </button>
                                </div>
                                <div id="razorpayResult" style="margin-top:14px;color:#2C7A7B;font-size:16px;"></div>
                                <div class="refund-note" style="background:#fff3cd;color:#856404;padding:14px 20px;border-radius:7px;border:1px solid #ffeeba;margin-bottom:22px;font-size:16px;display:flex;align-items:center;gap:10px;"><i class="fas fa-exclamation-circle" style="font-size:19px;"></i> <span><strong>Note:</strong> No refund policy available after cancellation of subscription.</span></div>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i> No active subscription found. Please <a href="browse_plans.php" style="color:#1d5f60;text-decoration:underline;">select a meal plan</a> first.
                    </div>
                <?php endif; ?>
            </div>
            <footer style="text-align:center;padding:20px;margin-top:40px;color:#777;font-size:14px;border-top:1px solid #eee;animation:fadeIn 0.8s ease-out;">
                <p>&copy; 2025 Tiffinly. All rights reserved.</p>
            </footer>
        </div>
    </div>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        var planAmount = <?php echo json_encode(number_format($plan_price, 2, '.', '')); ?>;
        if (document.getElementById('razorpayRealBtn')) {
            document.getElementById('razorpayRealBtn').onclick = function(e) {
                var options = {
                    "key": "rzp_test_1DP5mmOlF5G5ag",
                    "amount": Math.round(parseFloat(planAmount) * 100),
                    "currency": "INR",
                    "name": "Tiffinly",
                    "description": "Meal Plan Payment",
                    "image": "https://cdn.razorpay.com/static/assets/razorpay-glyph.svg",
                    "handler": function (response){
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '';
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'razorpay_payment_id';
                        input.value = response.razorpay_payment_id;
                        form.appendChild(input);
                        var payNow = document.createElement('input');
                        payNow.type = 'hidden';
                        payNow.name = 'pay_now';
                        payNow.value = '1';
                        form.appendChild(payNow);
                        document.body.appendChild(form);
                        form.submit();
                    },
                    "modal": {
                        "ondismiss": function(){
                            document.getElementById('razorpayResult').innerHTML = '<span style="color:#a00;">Payment cancelled.</span>';
                        }
                    },
                    "theme": { "color": "#4ECDC4" }
                };
                var rzp = new Razorpay(options);
                rzp.open();
                e.preventDefault();
            };
        }
    </script>
</body>
</html>
<?php $db->close(); ?>