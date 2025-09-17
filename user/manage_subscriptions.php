<?php
session_start();
include('../config/db_connect.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error_message = '';

// Helpers to compute duration and expected deliveries
function ms_norm_sched($s) {
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
function ms_count_days($start_date, $end_date, $schedule) {
    if (empty($start_date) || empty($end_date)) return 0;
    try { $start = new DateTime($start_date); $end = new DateTime($end_date); } catch (Exception $e) { return 0; }
    if ($end < $start) return 0;
    $norm = ms_norm_sched($schedule);
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

// Handle Cancel Subscription Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_subscription'])) {
    $subscription_id_to_cancel = $_POST['subscription_id'];

    // Change status to 'cancelled' instead of deleting the record
    $update_sql = "UPDATE subscriptions SET status = 'cancelled' WHERE subscription_id = ? AND user_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ii", $subscription_id_to_cancel, $user_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        // Remove delivery partner assignments for this subscription
        $delete_assignments_sql = "DELETE FROM delivery_assignments WHERE subscription_id = ?";
        $del_stmt = $conn->prepare($delete_assignments_sql);
        $del_stmt->bind_param("i", $subscription_id_to_cancel);
        $del_stmt->execute();
        $del_stmt->close();

        $message = "Your subscription has been successfully canceled.";
        // IMPORTANT: Clear session data to prevent other pages from using the canceled plan
        // Clear cart session data as well
        unset($_SESSION['plan_selection']);
        unset($_SESSION['premium_meal_selection']);
        unset($_SESSION['cart']);
        unset($_SESSION['whatsapp_notification_link']); // Clear WhatsApp notification link

        // Redirect to payment page to show a clean state
        header('Location: payment.php?status=cancelled');
        exit();
    } else {
        $error_message = "Could not cancel the subscription. It might have been already canceled or an error occurred.";
    }
    $stmt->close();
}

// Fetch user's latest non-canceled subscription

// Fetch all non-cancelled subscriptions for the user
$subs_sql = "SELECT s.*, mp.plan_name, mp.plan_type FROM subscriptions s JOIN meal_plans mp ON s.plan_id = mp.plan_id WHERE s.user_id = ? AND s.status != 'cancelled' ORDER BY s.created_at DESC";
$subs_stmt = $conn->prepare($subs_sql);
$subs_stmt->bind_param("i", $user_id);
$subs_stmt->execute();
$subs_result = $subs_stmt->get_result();
$subscriptions = [];
while ($sub = $subs_result->fetch_assoc()) {
    $sub_id = $sub['subscription_id'];
    $end_date = $sub['end_date'];
    $today = date('Y-m-d');
    $count_sql = "SELECT COUNT(*) as total, SUM(CASE WHEN status IN ('delivered','completed') THEN 1 ELSE 0 END) as delivered FROM delivery_assignments WHERE subscription_id = ?";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("i", $sub_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result()->fetch_assoc();
    $count_stmt->close();
    if ($count_result && $count_result['total'] > 0 && $count_result['delivered'] == $count_result['total']) {
        // Mark as completed if not already
        if (strtolower($sub['status']) !== 'completed') {
            $update_sql = "UPDATE subscriptions SET status = 'completed' WHERE subscription_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $sub_id);
            $update_stmt->execute();
            $update_stmt->close();
            $sub['status'] = 'completed';
        }
    }
    $subscriptions[] = $sub;
}
$subs_stmt->close();

// Use the latest subscription for details display
$subscription = !empty($subscriptions) ? $subscriptions[0] : null;

// Fetch user data for sidebar
$user_query = $conn->prepare("SELECT name, email FROM users WHERE user_id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user = $user_result->fetch_assoc();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subscription - Tiffinly</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="user_css/user_dashboard_style.css">
    <style>
        .main-content { padding: 30px; }
        .content-box { background-color: white; border-radius: 12px; padding: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .details-grid { display: grid; grid-template-columns: 200px 1fr; gap: 15px; margin-top: 20px; }
        .details-grid dt { font-weight: 600; color: #555; }
        .details-grid dd { margin: 0; color: #333; }
        .plan-name { font-size: 1.5em; font-weight: 600; color: #1D5F60; }
        .no-subscription { text-align: center; padding: 50px; }
        .button-group { display: flex; justify-content: flex-start; gap: 15px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background-color: #d4edda; color: #155724; }
        .alert-error { background-color: #f8d7da; color: #721c24; }

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
        .cancel-btn-style {
            background-color: #e74c3c;
            color: white;
        }
        .cancel-btn-style:hover {
            background-color: #c0392b;
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
                <a href="payment.php" class="menu-item">
                    <i class="fas fa-credit-card"></i> Payment
                </a>
                
                <div class="menu-category">Order History</div>
                <a href="track_order.php" class="menu-item">
                    <i class="fas fa-map-marker-alt"></i> Track Order
                </a>
                <a href="manage_subscriptions.php" class="menu-item active">
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
                    <h1>Manage Subscription</h1>
                    <p class="subtitle">View, customize, or cancel your active subscription.</p>
                </div>
            </div>
            <div class="content-box">
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>                <?php if (isset($_GET['paid']) && $_GET['paid'] == 1): ?>
                    <div class="alert alert-success">Your payment was successful and your subscription is now active!</div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <?php if ($subscription): ?>
                    <div class="plan-name"><?php echo htmlspecialchars($subscription['plan_name']); ?></div>
                    <dl class="details-grid">
                        <dt>Subscription ID:</dt>
                        <dd><?php echo htmlspecialchars($subscription['subscription_id']); ?></dd>

                        <dt>Plan Type:</dt>
                        <dd><?php echo ucfirst(htmlspecialchars($subscription['plan_type'])); ?></dd>
                        
                        <dt>Status:</dt>
                        <dd>
                            <?php if (strtolower($subscription['status']) === 'completed'): ?>
                                <span class="badge badge-completed" style="background:#27ae60 !important; color:#fff !important;">
                                    Completed
                                </span>
                            <?php else: ?>
                                <span class="badge badge-<?php echo strtolower($subscription['status']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($subscription['status'])); ?>
                                </span>
                            <?php endif; ?>
                        </dd>

                        <dt>Subscription Period:</dt>
                        <dd><?php echo date("M d, Y", strtotime($subscription['start_date'])); ?> - <?php echo date("M d, Y", strtotime($subscription['end_date'])); ?></dd>
                        
                        <dt>Schedule:</dt>
                        <dd><?php echo htmlspecialchars($subscription['schedule']); ?></dd>
                        <?php $ms_days = ms_count_days($subscription['start_date'] ?? null, $subscription['end_date'] ?? null, $subscription['schedule'] ?? 'daily'); ?>
                        <dt>Duration:</dt>
                        <dd><?php echo (int)$ms_days; ?> days</dd>
                        
                        <dt>Expected Deliveries:</dt>
                        <dd><?php echo number_format($ms_days * 3); ?> meals</dd>
                        
                        <dt>Price:</dt>
                        <dd>₹<?php echo number_format($subscription['total_price'], 2); ?></dd>
                    </dl>

                    <div class="button-group">
                        <?php if ($subscription['payment_status'] !== 'paid'): ?>
                            <p>Your subscription is not activated yet. Please complete the payment.</p>
                            <a href="payment.php" class="unified-btn confirm-btn-style">Proceed to Payment</a>
                        <?php else: ?>
                            <?php if ($subscription['status'] === 'active'): ?>
                                <form method="POST" action="manage_subscriptions.php" onsubmit="return confirm('Are you sure you want to permanently cancel your subscription? This action cannot be undone.');">
                                    <input type="hidden" name="subscription_id" value="<?php echo $subscription['subscription_id']; ?>">
                                    <button type="submit" name="cancel_subscription" class="unified-btn cancel-btn-style">
                                        <i class="fas fa-times-circle"></i> Cancel Subscription
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="no-subscription">
                        <i class="fas fa-info-circle" style="font-size: 48px; color: #1D5F60; margin-bottom: 20px;"></i>
                        <h2>No Active Subscription</h2>
                        <p>You do not currently have an active meal subscription.</p>
                        <a href="browse_plans.php" class="unified-btn confirm-btn-style" style="margin-top: 20px;">Browse Plans</a>
                    </div>
                <?php endif; ?>
            </div>
            <footer style="text-align: center; padding: 20px; margin-top: 40px; color: #777; font-size: 14px; border-top: 1px solid #eee;">
                <p>&copy; 2025 Tiffinly. All rights reserved.</p>
            </footer>
        </div>
    </div>
</body>
</html>