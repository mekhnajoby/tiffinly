<?php
session_start();
// Check if user is logged in, otherwise redirect to login page
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'delivery') {
    header("Location: ../login.php");
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

// Get delivery partner details
$partner_query = $db->prepare("SELECT * FROM delivery_partner_details WHERE partner_id = ?");
$partner_query->bind_param("i", $user_id);
$partner_query->execute();
$partner_result = $partner_query->get_result();
$partner_details = $partner_result->fetch_assoc();

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

    if(isset($_POST['update_vehicle'])) {
        $vehicle_type = $_POST['vehicle_type'];
        $vehicle_number = $_POST['vehicle_number'];
        $availability = $_POST['availability'];

        $update_partner_query = $db->prepare("UPDATE delivery_partner_details SET vehicle_type = ?, vehicle_number = ?, availability = ? WHERE partner_id = ?");
        $update_partner_query->bind_param("sssi", $vehicle_type, $vehicle_number, $availability, $user_id);
        $update_partner_query->execute();

        // Refresh partner data
        $partner_query->execute();
        $partner_result = $partner_query->get_result();
        $partner_details = $partner_result->fetch_assoc();

        $success_message = "Vehicle details updated successfully!";
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
    <link rel="stylesheet" href="../user/user_css/profile_style.css">
    <style>
           :root {
        --primary-color: #2C7A7B;
        --secondary-color: #F39C12;
        --accent-color: #F1C40F;
        --dark-color: #2C3E50;
        --light-color: #F9F9F9;
        --success-color: #27AE60;
        --rating-color: #FF9529;
        --shadow-sm: 0 1px 3px rgba(0,0,0,0.12);
        --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
        --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
        --transition-fast: all 0.2s ease;
        --transition-medium: all 0.3s ease;
        --transition-slow: all 0.5s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    @keyframes float {
        0% { transform: translateY(0px); }
        50% { transform: translateY(-5px); }
        100% { transform: translateY(0px); }
    }
    
    @keyframes slideInLeft {
        from { transform: translateX(-20px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideInRight {
        from { transform: translateX(20px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    body {
        font-family: 'Poppins', sans-serif;
        margin: 0;
        padding: 0;
        background-color: var(--light-color);
        color: var(--dark-color);
        overflow-x: hidden;
    }
    
    .dashboard-container {
        display: grid;
        grid-template-columns: 280px 1fr;
        min-height: 100vh;
        height: 100vh;
        overflow: hidden;
    }

    .sidebar {
        background-color: white;
        box-shadow: var(--shadow-md);
        padding: 30px 0;
        z-index: 10;
        animation: slideInLeft 0.6s ease-out;
        height: 100vh;
        overflow-y: auto;
        position: sticky;
        top: 0;
    }

    .sidebar-header {
        font-family: 'Poppins', sans-serif;
        font-weight: 700;
        font-size: 24px;
        padding: 0 25px 15px;
        border-bottom: 1px solid #f0f0f0;
        text-align: center;
        color: #2C3E50;
        animation: fadeIn 0.8s ease-out;
    }

    .admin-profile {
        display: flex;
        align-items: center;
        padding: 20px 25px;
        border-bottom: 1px solid #f0f0f0;
        margin-bottom: 15px;
    }
    
    .admin-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background-color: #F39C12;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        font-size: 20px;
        font-weight: 600;
        color: white;
    }
    
    .admin-info h4 {
        margin: 0;
        font-size: 16px;
    }
    
    .admin-info p {
        margin: 3px 0 0;
        font-size: 13px;
        opacity: 0.8;
    }

    .sidebar-menu {
        padding: 15px 0;
    }
    
    .menu-category {
        color: var(--primary-color);
        font-size: 13px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 12px 25px;
        margin-top: 15px;
        animation: fadeIn 0.6s ease-out;
    }
    
    .menu-item {
        padding: 12px 25px;
        display: flex;
        align-items: center;
        color: var(--dark-color);
        text-decoration: none;
        transition: var(--transition-medium);
        font-size: 15px;
        border-left: 3px solid transparent;
        position: relative;
        overflow: hidden;
    }
    
    .menu-item:before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(44, 122, 123, 0.1), transparent);
        transition: var(--transition-medium);
    }
    
    .menu-item:hover:before {
        left: 100%;
    }
    
    .menu-item:hover, .menu-item.active {
        background-color: #F0F7F7;
        color: var(--primary-color);
        border-left: 3px solid var(--primary-color);
        transform: translateX(5px);
    }
    
    .menu-item i {
        margin-right: 12px;
        font-size: 16px;
        width: 20px;
        text-align: center;
        transition: var(--transition-fast);
    }
    
    .menu-item:hover i {
        transform: scale(1.2);
    }

    .main-content {
        padding: 30px;
        background-color: var(--light-color);
        animation: fadeIn 0.8s ease-out;
        height: 100vh;
        overflow-y: auto;
    }
    
    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        animation: slideInRight 0.6s ease-out;
    }
    
    .header h1 {
        font-size: 28px;
        margin: 0;
        position: relative;
        display: inline-block;
    }
    
    .header h1:after {
        content: '';
        position: absolute;
        bottom: -5px;
        left: 0;
        width: 50px;
        height: 3px;
        background-color: var(--primary-color);
        border-radius: 3px;
        transition: var(--transition-medium);
    }
    
    .header:hover h1:after {
        width: 100%;
    }
    
    .header p {
        margin: 5px 0 0;
        color: #777;
        font-size: 16px;
        transition: var(--transition-medium);
    }
    
    .header:hover p {
        color: var(--dark-color);
    }

    .stats-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background-color: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: var(--shadow-sm);
        display: flex;
        align-items: center;
        transition: var(--transition-medium);
        position: relative;
        overflow: hidden;
        min-height: 100px;
    }
    
    .stat-card:after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color));
        opacity: 0;
        transition: var(--transition-medium);
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-lg);
    }
    
    .stat-card:hover:after {
        opacity: 1;
    }

    .stat-icon {
        font-size: 24px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 20px;
        flex-shrink: 0;
        color: white;
    }
    
    .stat-icon.users { background-color: #3498DB; }
    .stat-icon.subscriptions { background-color: #F39C12; }
    .stat-icon.partners { background-color: #F39C12; }
    .stat-icon.revenue { background-color: #1ABC9C; }


    .stat-info {
        flex: 1;
    }
    
    .stat-info h3 {
        font-size: 28px;
        margin: 0 0 5px 0;
        color: var(--dark-color);
        font-weight: 600;
    }
    
    .stat-card:hover .stat-info h3 {
        color: var(--primary-color);
    }
    
    .stat-info p {
        margin: 0;
        color: #777;
        font-size: 14px;
    }

    .dashboard-section {
        margin-bottom: 30px;
        animation: fadeIn 0.8s ease-out forwards;
        opacity: 0;
    }
    
    .dashboard-section:nth-child(1) {
        animation-delay: 0.2s;
    }
    
    .dashboard-section:nth-child(2) {
        animation-delay: 0.4s;
    }

    .section-title {
        font-size: 22px;
        margin-bottom: 20px;
        position: relative;
        padding-left: 15px;
    }
    
    .section-title:before {
        content: '';
        position: absolute;
        left: 0;
        top: 5px;
        height: 18px;
        width: 4px;
        background-color: var(--primary-color);
        border-radius: 2px;
        transition: var(--transition-medium);
    }
    
    .section-title:hover:before {
        height: 25px;
        top: 0;
    }

    .content-card {
        background-color: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: var(--shadow-sm);
        transition: var(--transition-medium);
        transform: translateY(0);
    }
    
    .content-card:hover {
        box-shadow: var(--shadow-lg);
        transform: translateY(-3px);
    }

    footer {
        text-align: center;
        padding: 20px;
        margin-top: 40px;
        color: #777;
        font-size: 13px;
        border-top: 1px solid #eee;
    }

    /* Responsive styles */
    @media (max-width: 1200px) {
        .stats-cards {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 992px) {
        .dashboard-container {
            grid-template-columns: 1fr;
        }
        
        .sidebar {
            display: none;
        }
    }

    @media (max-width: 768px) {
        .main-content {
            padding: 20px;
        }
        
        .stats-cards {
            grid-template-columns: 1fr;
        }
        
        .stat-card {
            padding: 20px;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            font-size: 22px;
            margin-right: 15px;
        }
        
        .stat-info h3 {
            font-size: 24px;
        }
        
        .header h1 {
            font-size: 26px;
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

            <div class="admin-profile">
                <div class="admin-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div class="admin-info">
                    <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
            </div>

            <div class="sidebar-menu">
                <a href="partner_dashboard.php" class="menu-item">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>

                <a href="partner_profile.php" class="menu-item active">
                    <i class="fas fa-user"></i> My Profile
                </a>
            
                <div class="menu-category">Manage Deliveries</div>
                <a href="available_orders.php" class="menu-item">
                    <i class="fas fa-search"></i> Available Orders
                </a>
                <a href="my_deliveries.php" class="menu-item">
                    <i class="fas fa-truck"></i> My Deliveries
                </a>
                <a href="delivery_history.php" class="menu-item">
                    <i class="fas fa-history"></i> Delivery History
                </a>

                <a href="performance_review.php" class="menu-item">
                    <i class="fas fa-chart-line"></i> Performance Review
                </a>  

                <a href="earnings.php" class="menu-item">
                    <i class="fas fa-wallet"></i> Earnings & Incentives
                </a>

                <a href="log_issues.php" class="menu-item">
                    <i class="fas fa-exclamation-triangle"></i> Log Issues
                </a>

               <div style="margin-top: 30px;">
                    <a href="../logout.php" class="menu-item">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Main Content Area -->
        <div class="main-content">
            <div class="header">
                <div class="welcome-message">
                    <h1>My Profile</h1>
                    <p>Manage your personal information and vehicle details</p>
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

    <!-- Vehicle Information Section -->
    <div class="profile-section">
        <h2 class="section-title">Vehicle Information</h2>
        <div class="profile-card">
            <form method="POST">
                <div class="form-group">
                    <label for="vehicle_type">Vehicle Type</label>
                    <div class="input-group">
                        <select id="vehicle_type" name="vehicle_type" class="form-control">
                            <option value="Scooter" <?php if($partner_details['vehicle_type'] == 'Scooter') echo 'selected'; ?>>Scooter</option>
                            <option value="Car" <?php if($partner_details['vehicle_type'] == 'Car') echo 'selected'; ?>>Car</option>
                            <option value="Bike" <?php if($partner_details['vehicle_type'] == 'Bike') echo 'selected'; ?>>Bike</option>
                        </select>
                       
                    </div>
                </div>
                <div class="form-group">
                    <label for="vehicle_number">Vehicle Number</label>
                    <div class="input-group">
                        <input type="text" id="vehicle_number" name="vehicle_number" class="form-control" 
                               value="<?php echo htmlspecialchars($partner_details['vehicle_number']); ?>" 
                               placeholder="e.g., KL-01-AB-1234" required>
                               &nbsp<i class="fas fa-pencil-alt edit-icon"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label for="license_number">License Number</label>
                    <input type="text" id="license_number" name="license_number" class="form-control" 
                           value="<?php echo htmlspecialchars($partner_details['license_number']); ?>" 
                           placeholder="Enter your driving license number" readonly>
                </div>
                <div class="form-group">
                    <label for="aadhar_number">Aadhar Number</label>
                    <input type="text" id="aadhar_number" name="aadhar_number" class="form-control" 
                           value="<?php echo htmlspecialchars($partner_details['aadhar_number']); ?>" 
                           placeholder="Enter your Aadhar number" readonly>
                </div>
                <div class="form-group">
                    <label for="availability">Availability</label>
                    <div class="input-group">
                        <select id="availability" name="availability" class="form-control">
                            <option value="Part-time" <?php if($partner_details['availability'] == 'Part-time') echo 'selected'; ?>>Part-time</option>
                            <option value="Full-time" <?php if($partner_details['availability'] == 'Full-time') echo 'selected'; ?>>Full-time</option>
                        </select>
                      
                    </div>
                </div>
                <button type="submit" name="update_vehicle" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Vehicle Details
                </button>
            </form>
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
                <p>&copy; <?php echo date('Y'); ?> Tiffinly. All rights reserved.</p>
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
                    const input = this.parentElement.querySelector('input, select');
                    input.focus();
                });
            });
        });
    </script>
</body>
</html>
<?php
$db->close();
?>