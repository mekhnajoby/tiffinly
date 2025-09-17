<?php
if (!isset($user) || !is_array($user)) {
    $user = [
        'name' => 'User',
        'email' => 'user@tiffinly.in'
    ];
}
?>
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