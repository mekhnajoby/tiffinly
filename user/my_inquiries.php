<?php
session_start();
// Check if user is logged in, otherwise redirect to login page
if(!isset($_SESSION['user_id'])) {
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

// Get user's inquiries with responses
$inquiries_query = $db->prepare("SELECT * FROM inquiries WHERE user_id = ? ORDER BY created_at DESC");
$inquiries_query->bind_param("i", $user_id);
$inquiries_query->execute();
$inquiries_result = $inquiries_query->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiffinly - My Inquiries</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="user_css/user_dashboard_style.css">
    <style>
        :root {
            --primary-color: #1D5F60;
            --secondary-color: #F39C12;
            --dark-color: #333;
            --light-color: #f8f9fa;
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .inquiries-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .inquiries-section {
            margin-bottom: 30px;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
        }
        
        .section-title {
            font-size: 24px;
            margin-bottom: 25px;
            color: var(--dark-color);
            position: relative;
            padding-bottom: 12px;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 4px;
            background: var(--primary-color);
            border-radius: 2px;
        }
        
        .inquiries-list {
            margin-top: 30px;
        }
        
        .inquiry-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .inquiry-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .inquiry-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .inquiry-type {
            font-weight: 600;
            color: var(--primary-color);
            text-transform: capitalize;
        }
        
        .inquiry-date {
            color: #666;
            font-size: 14px;
        }
        
        .inquiry-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-responded {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-closed {
            background-color: #d6d8d9;
            color: #1b1e21;
        }
        
        .inquiry-message {
            margin-bottom: 15px;
            color: var(--dark-color);
            line-height: 1.6;
        }
        
        .inquiry-response {
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
            margin-top: 15px;
        }
        
        .response-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 8px;
            display: block;
        }
        
        .no-inquiries {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .no-inquiries i {
            font-size: 50px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .no-inquiries p {
            font-size: 18px;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 16px;
            border: none;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: #1a4f50;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .inquiries-container {
                padding: 15px;
            }
            
            .inquiries-section {
                padding: 20px;
            }
            
            .inquiry-header {
                flex-direction: column;
                gap: 10px;
            }
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
                <a href="my_inquiries.php" class="menu-item active">
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
                    <h1>My Inquiries</h1><br><br>
                    <p>Track the status of your support inquiries and view responses</p>
                </div>
            </div>

            <div class="inquiries-container">
                <section class="inquiries-section">
                    <h2 class="section-title">Your Support Inquiries</h2>
                    
                    <?php if($inquiries_result->num_rows > 0): ?>
                        <div class="inquiries-list">
                            <?php while($inquiry = $inquiries_result->fetch_assoc()): ?>
                                <div class="inquiry-card">
                                    <div class="inquiry-header">
                                        <div>
                                            <span class="inquiry-type"><?php echo htmlspecialchars($inquiry['inquiry_type']); ?></span>
                                            <span class="inquiry-status status-<?php echo htmlspecialchars($inquiry['status']); ?>">
                                                <?php echo htmlspecialchars($inquiry['status']); ?>
                                            </span>
                                        </div>
                                        <div class="inquiry-date">
                                            <?php echo date('M j, Y \a\t g:i a', strtotime($inquiry['created_at'])); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="inquiry-message">
                                        <?php echo nl2br(htmlspecialchars($inquiry['message'])); ?>
                                    </div>
                                    
                                    <?php if(!empty($inquiry['response'])): ?>
                                        <div class="inquiry-response">
                                            <span class="response-label">
                                                <i class="fas fa-reply"></i> Admin Response
                                            </span>
                                            <?php echo nl2br(htmlspecialchars($inquiry['response'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="inquiry-response" style="background-color: #fff3cd; border-left-color: #ffc107;">
                                            <span class="response-label">
                                                <i class="fas fa-clock"></i> Status
                                            </span>
                                            Your inquiry is being reviewed by our support team. We'll respond as soon as possible.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-inquiries">
                            <i class="fas fa-inbox"></i>
                            <p>You haven't submitted any inquiries yet.</p>
                            <a href="support.php" class="btn btn-primary" style="margin-top: 20px;">
                                <i class="fas fa-plus"></i> Submit New Inquiry
                            </a>
                        </div>
                    <?php endif; ?>
                </section>
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
        // Set minimum date for date inputs to today
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.querySelectorAll('input[type="date"]').forEach(input => {
                if(!input.value) {
                    input.value = today;
                }
                input.min = today;
            });
        });
    </script>
</body>
</html>