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

// Handle form submission
$success_message = '';
$error_message = '';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_inquiry'])) {
    $message = trim($_POST['message']);
    $inquiry_type = $_POST['inquiry_type'];
    
    if(empty($message)) {
        $error_message = "Please enter your message";
    } else {
        // Insert inquiry into database
        $stmt = $db->prepare("INSERT INTO inquiries (user_id, message, inquiry_type, status) VALUES (?, ?, ?, 'pending')");
        $stmt->bind_param("iss", $user_id, $message, $inquiry_type);
        
        if($stmt->execute()) {
            $success_message = "Your inquiry has been submitted successfully! We'll get back to you soon.";
            // Clear the form
            $_POST['message'] = '';
            $_POST['inquiry_type'] = 'general';
        } else {
            $error_message = "There was an error submitting your inquiry. Please try again.";
        }
    }
}

// Get user's previous inquiries
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
    <title>Tiffinly - Support Center</title>
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
        
        .support-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .support-section {
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
        }
        
        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
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
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
        
        .faq-section {
            margin-top: 40px;
        }
        
        .faq-item {
            margin-bottom: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .faq-question {
            padding: 15px 20px;
            background-color: #f8f9fa;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .faq-question:hover {
            background-color: #e9ecef;
        }
        
        .faq-answer {
            padding: 15px 20px;
            display: none;
            background-color: white;
            line-height: 1.6;
        }
        
        .faq-item.active .faq-answer {
            display: block;
        }
        
        .contact-info {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin-top: 30px;
        }
        
        .contact-method {
            flex: 1;
            min-width: 250px;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow-md);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .contact-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: rgba(29, 95, 96, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--primary-color);
        }
        
        .contact-details h4 {
            margin-bottom: 5px;
            color: var(--dark-color);
        }
        
        .contact-details p {
            color: #666;
            margin: 0;
        }
        
        @media (max-width: 768px) {
            .support-container {
                padding: 15px;
            }
            
            .support-section {
                padding: 20px;
            }
            
            .contact-method {
                min-width: 100%;
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
                    <i class="fas fa-history"></i> Subscription History
                </a>
                <div class="menu-category">Feedback & Support</div>
                <a href="feedback.php" class="menu-item">
                    <i class="fas fa-comment-alt"></i> Feedback
                </a>
                <a href="support.php" class="menu-item active">
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
                    <h1>Support Center</h1><br><br>
                    <p>We're here to help with any questions or issues</p>
                </div>
            </div>

            <div class="support-container">
                <?php if($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if($error_message): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <section class="support-section">
                    <h2 class="section-title">Submit a New Inquiry</h2>
                    <form method="POST" action="support.php">
                        <div class="form-group">
                            <label for="inquiry_type">Inquiry Type</label>
                            <select class="form-control" id="inquiry_type" name="inquiry_type" required>
                                <option value="general" <?php echo (isset($_POST['inquiry_type']) && $_POST['inquiry_type'] == 'general' ? 'selected' : ''); ?>>General Question</option>
                                <option value="technical" <?php echo (isset($_POST['inquiry_type']) && $_POST['inquiry_type'] == 'technical' ? 'selected' : ''); ?>>Technical Issue</option>
                                <option value="billing" <?php echo (isset($_POST['inquiry_type']) && $_POST['inquiry_type'] == 'billing' ? 'selected' : ''); ?>>Billing/Payment</option>
                                <option value="delivery" <?php echo (isset($_POST['inquiry_type']) && $_POST['inquiry_type'] == 'delivery' ? 'selected' : ''); ?>>Delivery Issue</option>
                                <option value="meal" <?php echo (isset($_POST['inquiry_type']) && $_POST['inquiry_type'] == 'meal' ? 'selected' : ''); ?>>Meal Quality</option>
                                <option value="other" <?php echo (isset($_POST['inquiry_type']) && $_POST['inquiry_type'] == 'other' ? 'selected' : ''); ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Your Message *</label>
                            <textarea class="form-control" id="message" name="message" required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                        </div>
                        
                        <button type="submit" name="submit_inquiry" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Inquiry
                        </button>
                    </form>
                </section>
                
                <section class="support-section">
                    <h2 class="section-title">Contact Information</h2>
                    <div class="contact-info">
                        <div class="contact-method">
                            <div class="contact-icon">
                                <i class="fas fa-phone-alt"></i>
                            </div>
                            <div class="contact-details">
                                <h4>Phone Support</h4>
                                <p>+91 8901234567</p>
                                <p>Mon-Fri, 9am-6pm</p>
                            </div>
                        </div>
                        
                        <div class="contact-method">
                            <div class="contact-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="contact-details">
                                <h4>Email Support</h4>
                                <p>support@tiffinly.com</p>
                                <p>Response within 24 hours</p>
                            </div>
                        </div>
                        
                       
                    </div>
                </section>
                
                <section class="support-section faq-section">
                    <h2 class="section-title">Frequently Asked Questions</h2>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>How do I change my delivery address?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>You can update your delivery address by going to your Profile page and editing your address information. Make sure to save the changes, and they will be applied to your next delivery.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>What should I do if I receive the wrong meal?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>If you receive an incorrect meal, please contact our support team immediately with details of your order and the issue. We'll arrange for a replacement or credit as appropriate.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>How can I cancel my subscription?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>You can manage your subscription from the Subscription page in your account. There you'll find options to cancel your plan. Please note that there is no refund for cancellations.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>What are your delivery hours?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>We deliver fresh meals three times a day: Breakfast, Lunch, and Dinner. You can choose your preferred time slot for delivery. Our delivery partners ensure your meals reach you hot and on time.
                                    </div>.</p>
                        </div>
                    </div>
                    
                    
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
        // FAQ toggle functionality
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                const faqItem = question.parentElement;
                faqItem.classList.toggle('active');
                
                // Close other open FAQs
                document.querySelectorAll('.faq-item').forEach(item => {
                    if(item !== faqItem && item.classList.contains('active')) {
                        item.classList.remove('active');
                    }
                });
            });
        });
        
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