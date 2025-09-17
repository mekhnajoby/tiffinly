<?php
session_start();
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

// Fetch user's subscription history (all subscriptions)
$subs_sql = "SELECT s.*, mp.plan_name, mp.plan_type, mp.base_price FROM subscriptions s JOIN meal_plans mp ON s.plan_id = mp.plan_id WHERE s.user_id = ? ORDER BY s.subscription_id DESC";
$subs_stmt = $db->prepare($subs_sql);
$subs_stmt->bind_param("i", $user_id);
$subs_stmt->execute();
$subs_result = $subs_stmt->get_result();

// Fetch all payments for this user (for mapping)
$payments_sql = "SELECT * FROM payments WHERE user_id = ?";
$payments_stmt = $db->prepare($payments_sql);
$payments_stmt->bind_param("i", $user_id);
$payments_stmt->execute();
$payments_result = $payments_stmt->get_result();
$payments_map = [];
while ($row = $payments_result->fetch_assoc()) {
    $payments_map[$row['subscription_id']][] = $row;
}

// Group subscriptions by subscription_id
$subscriptions_grouped = [];
while ($sub = $subs_result->fetch_assoc()) {
    $subscriptions_grouped[$sub['subscription_id']][] = $sub;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiffinly - Subscription History</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="user_css/user_dashboard_style.css">
    <link rel="stylesheet" href="user_css/profile_style.css">
    <link rel="stylesheet" href="user_css/browse_plans_style.css">
    <style>
        .subscription-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 24px;
            overflow: hidden;
        }
        .card-header {
            background: #f7fafc;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
        }
        .card-header h3 {
            margin: 0;
            font-size: 18px;
            color: #2C7A7B;
        }
        .card-body {
            padding: 24px;
        }
        .card-body p {
            margin: 0 0 12px;
            color: #555;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #fff;
        }
        .badge-active { background: #2C7A7B; }
        .badge-cancelled { background: #e74c3c; }
        .badge-expired { background: #888; }
        .badge-pending { background: #f39c12; }
        .badge-success { background: #27ae60; }
        .payment-list { list-style: none; padding: 0; margin: 0; }
        .payment-list li { margin-bottom: 8px; }
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
            <a href="track_order.php" class="menu-item"><i class="fas fa-map-marker-alt"></i> Track Order</a>
            <a href="manage_subscriptions.php" class="menu-item">
                    <i class="fas fa-tools"></i> Manage Subscriptions
                </a>
            <a href="subscription_history.php" class="menu-item active"><i class="fas fa-calendar-alt"></i> Subscription History</a>
            <div class="menu-category">Feedback & Support</div>
            <a href="feedback.php" class="menu-item"><i class="fas fa-comment-alt"></i> Feedback</a>
            <a href="support.php" class="menu-item"><i class="fas fa-envelope"></i> Send Inquiry</a>
            <a href="my_inquiries.php" class="menu-item"><i class="fas fa-inbox"></i> My Inquiries</a>
            <div style="margin-top: 30px;">
                <a href="logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
    <!-- Main Content Area -->
    <div class="main-content">
        <div class="header">
            <div class="welcome-message">
                <h1>Subscription History</h1>
                <p>View all your past and current subscriptions and payments</p>
            </div>
        </div>
        <div class="history-section" style="margin-top:28px;">
            <?php if (empty($subscriptions_grouped)): ?>
                <p>No subscription history found.</p>
            <?php else: ?>
                <?php foreach ($subscriptions_grouped as $subscription_id => $subscriptions): ?>
                    <?php
                        $main_sub = $subscriptions[0];
                        $subscription_status = $main_sub['status'];
                        
                        $display_status = ucfirst($subscription_status);
                        $display_class = strtolower($subscription_status);

                        // If subscription is 'active', check payment status
                        if ($subscription_status === 'active') {
                            $is_paid = false;
                            if (!empty($payments_map[$subscription_id])) {
                                foreach ($payments_map[$subscription_id] as $payment) {
                                    // Check for a successful payment status
                                    if (in_array(strtolower($payment['payment_status']), ['completed', 'success', 'paid'])) {
                                        $is_paid = true;
                                        break;
                                    }
                                }
                            }

                            if (!$is_paid) {
                                $display_status = 'Activation Pending';
                                $display_class = 'pending';
                            }
                        }
                        // Show green badge for completed
                        if ($display_class === 'completed') {
                            $display_status = '<span class="badge badge-completed" style="background:#27ae60 !important; color:#fff !important;">Completed</span>';
                        } else {
                            $display_status = '<span class="badge badge-' . $display_class . '">' . ucfirst($display_class) . '</span>';
                        }
                    ?>
                    <div class="subscription-card">
                        <div class="card-header">
                            <h3>Subscription ID: <?php echo $subscription_id; ?></h3>
                            <?php echo $display_status; ?>
                        </div>
                        <div class="card-body">
                            <p><strong>Plan:</strong> <?php echo htmlspecialchars($main_sub['plan_name']); ?></p>
                            <p><strong>Schedule:</strong> <?php echo htmlspecialchars(ucfirst($main_sub['schedule'])); ?></p>
                            <p><strong>Period:</strong> <?php echo date('M d, Y', strtotime($main_sub['start_date'])); ?> to <?php echo date('M d, Y', strtotime($main_sub['end_date'])); ?></p>
                            
                            <h4>Payment History</h4>
                            <?php if (!empty($payments_map[$subscription_id])): ?>
                                <ul class="payment-list">
                                    <?php foreach($payments_map[$subscription_id] as $pay): ?>
                                        <li>
                                            <span class="badge badge-<?php echo strtolower($pay['payment_status']); ?>">
                                                <?php echo ucfirst($pay['payment_status']); ?>
                                            </span>
                                            <span>₹<?php echo number_format($pay['amount'], 2); ?></span>
                                            <span>(<?php echo date('d M Y', strtotime($pay['created_at'])); ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p>No payments found for this subscription.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <footer style="text-align:center;padding:20px;margin-top:40px;color:#777;font-size:14px;border-top:1px solid #eee;animation:fadeIn 0.8s ease-out;">
    <p>&copy; 2025 Tiffinly. All rights reserved.</p>
</footer>
    </div>
</div>

</body>
</html>
<?php $db->close(); ?>