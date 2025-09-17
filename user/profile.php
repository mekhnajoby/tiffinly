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

// Get user addresses
$address_query = $db->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC");
$address_query->bind_param("i", $user_id);
$address_query->execute();
$address_result = $address_query->get_result();

// Handle form submissions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['update_profile'])) {
        // Handle profile update
        $name = $_POST['name'];
        $phone = $_POST['phone'];
        
        $update_query = $db->prepare("UPDATE users SET name = ?, phone = ? WHERE user_id = ?");
        $update_query->bind_param("ssi", $name, $phone, $user_id);
        $update_query->execute();
        
        // Refresh user data
        $user['name'] = $name;
        $user['phone'] = $phone;
        
        $success_message = "Profile updated successfully!";
    }
    
    if(isset($_POST['update_password'])) {
        // Handle password update
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        $verify_query = $db->prepare("SELECT password FROM users WHERE user_id = ?");
        $verify_query->bind_param("i", $user_id);
        $verify_query->execute();
        $verify_result = $verify_query->get_result();
        $db_password = $verify_result->fetch_assoc()['password'];
        
        if(password_verify($current_password, $db_password)) {
            if($new_password === $confirm_password) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $update_query->bind_param("si", $hashed_password, $user_id);
                $update_query->execute();
                
                $success_message = "Password updated successfully!";
            } else {
                $error_message = "New passwords do not match!";
            }
        } else {
            $error_message = "Current password is incorrect!";
        }
    }
    
    if(isset($_POST['add_address'])) {
    $address_type = $_POST['address_type'];
    $line1 = trim($_POST['line1']);
    $line2 = trim($_POST['line2']);
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $pincode = trim($_POST['pincode']);
    $landmark = trim($_POST['landmark']);

    // Check if this user already has an address of this type
    $check_query = $db->prepare("SELECT address_id FROM addresses WHERE user_id = ? AND address_type = ?");
    $check_query->bind_param("is", $user_id, $address_type);
    $check_query->execute();
    $check_result = $check_query->get_result();

    if($check_result->num_rows > 0) {
        $error_message = "You already have a $address_type address. Please delete it before adding a new one.";
    } else {
        $insert_query = $db->prepare("INSERT INTO addresses (user_id, address_type, line1, line2, city, state, pincode, landmark) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $insert_query->bind_param("isssssss", $user_id, $address_type, $line1, $line2, $city, $state, $pincode, $landmark);
        if ($insert_query->execute()) {
            $success_message = ucfirst($address_type) . " address added successfully!";
        } else {
            $error_message = "Failed to add address. Please try again.";
        }
    }

    // Refresh addresses
    $address_query->execute();
    $address_result = $address_query->get_result();
}

    
    if(isset($_POST['set_default_address'])) {
        // Handle setting default address
        $address_id = $_POST['address_id'];
        
        // Remove default from other addresses
        $db->query("UPDATE addresses SET is_default = 0 WHERE user_id = $user_id");
        
        // Set new default
        $update_query = $db->prepare("UPDATE addresses SET is_default = 1 WHERE address_id = ? AND user_id = ?");
        $update_query->bind_param("ii", $address_id, $user_id);
        $update_query->execute();
        
        // Refresh addresses
        $address_query->execute();
        $address_result = $address_query->get_result();
        
        $success_message = "Default address updated successfully!";
    }
    
    if(isset($_POST['delete_address'])) {
        // Handle address deletion
        $address_id = $_POST['address_id'];
        
        $delete_query = $db->prepare("DELETE FROM addresses WHERE address_id = ? AND user_id = ?");
        $delete_query->bind_param("ii", $address_id, $user_id);
        $delete_query->execute();
        
        // Refresh addresses
        $address_query->execute();
        $address_result = $address_query->get_result();
        
        $success_message = "Address deleted successfully!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiffinly - My Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
     <link rel="stylesheet" href="user_css/user_dashboard_style.css">
    <link rel="stylesheet" href="user_css/profile_style.css">
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

                <a href="profile.php" class="menu-item active">
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
            <button class="menu-toggle" style="display: none;">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="header">
                <div class="welcome-message">
                    <h1>My Profile</h1>
                    <p>Manage your personal information and delivery addresses</p>
                </div>
            </div>
            
            <?php if(isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

    <!-- Profile Information Section -->
    <div class="profile-section">
        <h2 class="section-title">Personal Information</h2>
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-info">
                    <h3><?php echo htmlspecialchars($user['name']); ?></h3>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
            </div>
            
            <hr style="margin: 25px 0; border: 0; border-top: 1px solid #eee;">
            
            <form method="POST">
                
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <div class="input-group">
                        <input type="text" id="name" name="name" class="form-control" 
                               value="<?php echo htmlspecialchars($user['name']); ?>" 
                               placeholder="Enter your full name" required>
                        <i class="fas fa-pencil-alt edit-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" class="form-control" 
                           value="<?php echo htmlspecialchars($user['email']); ?>" 
                           placeholder="Your email address" readonly><br>
                    <small class="text-muted">Contact support to change your email</small>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <div class="input-group">
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($user['phone']); ?>" 
                               placeholder="Enter your phone number" required>
                        <i class="fas fa-pencil-alt edit-icon"></i>
                    </div>
                </div>
                
                <button type="submit" name="update_profile" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>
    </div>
    
    <!-- Change Password Section -->
    <div class="profile-section">
        <h2 class="section-title">Change Password</h2>
        <div class="profile-card">
            <form method="POST">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <div class="input-group">
                        <input type="password" id="current_password" name="current_password" 
                               class="form-control" placeholder="Enter current password" required>
                        <i class="fas fa-eye password-toggle" id="toggleCurrentPassword"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <div class="input-group">
                        <input type="password" id="new_password" name="new_password" 
                               class="form-control" placeholder="Enter new password" required>
                        <i class="fas fa-eye password-toggle" id="toggleNewPassword"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="input-group">
                        <input type="password" id="confirm_password" name="confirm_password" 
                               class="form-control" placeholder="Confirm new password" required>
                        <i class="fas fa-eye password-toggle" id="toggleConfirmPassword"></i>
                    </div>
                </div>
                
                <button type="submit" name="update_password" class="btn btn-primary">
                    <i class="fas fa-key"></i> Change Password
                </button>
            </form>
        </div>
    </div>
    
    <!-- Delivery Addresses Section -->
    <div class="profile-section">
    <h2 class="section-title">Delivery Addresses</h2>

    <?php if ($address_result->num_rows > 0): ?>
        <?php
            $home_address = null;
            $work_address = null;
            while ($address = $address_result->fetch_assoc()) {
                if ($address['address_type'] === 'home') {
                    $home_address = $address;
                } elseif ($address['address_type'] === 'work') {
                    $work_address = $address;
                }
            }
        ?>

        <?php if ($home_address): ?>
            <div class="address-card default">
                <h4>
                    Home Address <span class="badge">Default</span>
                </h4>
                <p><strong><?php echo htmlspecialchars($home_address['line1']); ?></strong></p>
                <?php if (!empty($home_address['line2'])): ?>
                    <p><?php echo htmlspecialchars($home_address['line2']); ?></p>
                <?php endif; ?>
                <p><?php echo htmlspecialchars($home_address['city'] . ', ' . $home_address['state'] . ' - ' . $home_address['pincode']); ?></p>
                <?php if (!empty($home_address['landmark'])): ?>
                    <p>Landmark: <?php echo htmlspecialchars($home_address['landmark']); ?></p>
                <?php endif; ?>

                <div class="address-actions">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="address_id" value="<?php echo $home_address['address_id']; ?>">
                        <button type="submit" name="delete_address" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete your Home address?');">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($work_address): ?>
            <div class="address-card">
                <h4>Work Address</h4>
                <p><strong><?php echo htmlspecialchars($work_address['line1']); ?></strong></p>
                <?php if (!empty($work_address['line2'])): ?>
                    <p><?php echo htmlspecialchars($work_address['line2']); ?></p>
                <?php endif; ?>
                <p><?php echo htmlspecialchars($work_address['city'] . ', ' . $work_address['state'] . ' - ' . $work_address['pincode']); ?></p>
                <?php if (!empty($work_address['landmark'])): ?>
                    <p>Landmark: <?php echo htmlspecialchars($work_address['landmark']); ?></p>
                <?php endif; ?>

                <div class="address-actions">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="address_id" value="<?php echo $work_address['address_id']; ?>">
                        <button type="submit" name="delete_address" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete your Work address?');">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="profile-card">
            <p>No delivery addresses found. Please add a Home and Work address.</p>
        </div>
    <?php endif; ?>
</div>

        
        <div class="profile-card">
            <h3>Add New Address</h3>
<form method="POST">
    <div class="form-group">
        <label>Address Type</label>
        <select name="address_type" class="form-control" required>
            <option value="">Select Type</option>
            <option value="home">Home</option>
            <option value="work">Work</option>
        </select>
    </div>

    <div class="form-group">
        <label for="line1">Address Line 1</label>
        <input type="text" id="line1" name="line1" class="form-control" required>
    </div>

    <div class="form-group">
        <label for="line2">Address Line 2</label>
        <input type="text" id="line2" name="line2" class="form-control">
    </div>

    <div class="form-group">
        <label for="city">City</label>
        <input type="text" id="city" name="city" class="form-control" required>
    </div>

    <div class="form-group">
        <label for="state">State</label>
        <input type="text" id="state" name="state" class="form-control" required>
    </div>

    <div class="form-group">
        <label for="pincode">Pincode</label>
        <input type="text" id="pincode" name="pincode" class="form-control" required>
    </div>

    <div class="form-group">
        <label for="landmark">Landmark</label>
        <input type="text" id="landmark" name="landmark" class="form-control">
    </div>

    <button type="submit" name="add_address" class="btn btn-primary">
        <i class="fas fa-plus"></i> Add Address
    </button>
</form>

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

    <script>
        // Password visibility toggle
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle current password visibility
            const toggleCurrentPassword = document.getElementById('toggleCurrentPassword');
            const currentPassword = document.getElementById('current_password');
            
            toggleCurrentPassword.addEventListener('click', function() {
                if (currentPassword.type === 'password') {
                    currentPassword.type = 'text';
                    this.classList.replace('fa-eye', 'fa-eye-slash');
                } else {
                    currentPassword.type = 'password';
                    this.classList.replace('fa-eye-slash', 'fa-eye');
                }
            });
            
            // Toggle new password visibility
            const toggleNewPassword = document.getElementById('toggleNewPassword');
            const newPassword = document.getElementById('new_password');
            
            toggleNewPassword.addEventListener('click', function() {
                if (newPassword.type === 'password') {
                    newPassword.type = 'text';
                    this.classList.replace('fa-eye', 'fa-eye-slash');
                } else {
                    newPassword.type = 'password';
                    this.classList.replace('fa-eye-slash', 'fa-eye');
                }
            });
            
            // Toggle confirm password visibility
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const confirmPassword = document.getElementById('confirm_password');
            
            toggleConfirmPassword.addEventListener('click', function() {
                if (confirmPassword.type === 'password') {
                    confirmPassword.type = 'text';
                    this.classList.replace('fa-eye', 'fa-eye-slash');
                } else {
                    confirmPassword.type = 'password';
                    this.classList.replace('fa-eye-slash', 'fa-eye');
                }
            });
            
            // Focus on input when edit icon is clicked
            document.querySelectorAll('.edit-icon').forEach(icon => {
                icon.addEventListener('click', function() {
                    const input = this.parentElement.querySelector('input');
                    input.focus();
                });
            });
        });
         // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.querySelector('.menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            
            // Show menu toggle on mobile
            if(window.innerWidth <= 992) {
                menuToggle.style.display = 'block';
                
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(e) {
                if(window.innerWidth <= 992 && !sidebar.contains(e.target) && e.target !== menuToggle) {
                    sidebar.classList.remove('active');
                }
            });
            
            // Responsive adjustments
            window.addEventListener('resize', function() {
                if(window.innerWidth > 992) {
                    sidebar.classList.remove('active');
                    menuToggle.style.display = 'none';
                } else {
                    menuToggle.style.display = 'block';
                }
            });
        });
    </script>
</body>
</html>
<?php
$db->close();
?>