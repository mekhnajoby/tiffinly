<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
// Check if admin is logged in, otherwise redirect to login page
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Database connection
$db = new mysqli('localhost', 'root', '', 'tiffinly');

// Check connection
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Get admin data from session
// Get admin data from session
$admin_name = $_SESSION['name'] ?? 'Admin';
$admin_email = $_SESSION['email'] ?? 'admin@tiffinly.in';
$user = [
    'name' => $admin_name,
    'email' => $admin_email
];

// --- Data Fetching for Dashboard Stats ---

// Total Users
$users_result = $db->query("SELECT COUNT(*) as total_users FROM users WHERE role = 'user'");
$total_users = $users_result->fetch_assoc()['total_users'];

// Total Active Subscriptions
$subs_result = $db->query("SELECT COUNT(*) as total_subscriptions FROM subscriptions WHERE status = 'active'");
$total_subscriptions = $subs_result->fetch_assoc()['total_subscriptions'];

// Total Delivery Partners
$partners_result = $db->query("SELECT COUNT(*) as total_partners FROM users WHERE role = 'delivery'");
$total_partners = $partners_result->fetch_assoc()['total_partners'];

// Total Revenue (from successful customer payments)
$revenue_result = $db->query("SELECT COALESCE(SUM(amount),0) as total_revenue FROM payments WHERE payment_status = 'success'");
$total_revenue = $revenue_result->fetch_assoc()['total_revenue'];

// Total Partner Payouts
$payouts_result = $db->query("SELECT COALESCE(SUM(amount),0) as total_payouts FROM partner_payments WHERE payment_status = 'success'");
$total_payouts = $payouts_result ? ($payouts_result->fetch_assoc()['total_payouts'] ?? 0) : 0;

// Net Revenue
$net_revenue = (float)$total_revenue - (float)$total_payouts;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiffinly - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
        padding: 60px;
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
        background-color:  #F9F9F9;
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
        margin-right: 15px;
        flex-shrink: 0;
        color:  #F39C12;
    }

    .stat-info {
        flex: 1;
    }
    
    .stat-info h3 {
        font-size: 24px;
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
        padding: 12px;
        margin-top:280px;
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
            padding: 15px;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            font-size: 20px;
        }
        
        .stat-info h3 {
            font-size: 22px;
        }
        
        .header h1 {
            font-size: 24px;
        }
    }
</style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="sidebar-header">
            <i class="fas fa-utensils"></i>&nbsp  Tiffinly
            </div>

            <div class="admin-profile">
                <div class="admin-avatar">
                    <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                </div>
                <div class="admin-info">
                    <h4><?php echo htmlspecialchars($admin_name); ?></h4>
                    <p><?php echo htmlspecialchars($admin_email); ?></p>
                </div>
            </div>

            <div class="sidebar-menu">
                <div class="menu-category">Dashboard & Overview</div>
                <a href="admin_dashboard.php" class="menu-item active">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>

                <div class="menu-category">Meal Management</div>
                <a href="manage_menu.php" class="menu-item">
                    <i class="fas fa-utensils"></i> Manage Menu
                </a>
                <a href="manage_plans.php" class="menu-item">
                    <i class="fas fa-box"></i> Manage Plans
                </a>
                <a href="manage_popular_meals.php" class="menu-item">
                    <i class="fas fa-star"></i> Manage Popular Meals
                </a>

                <div class="menu-category">User & Subscriptions</div>
                <a href="manage_users.php" class="menu-item">
                    <i class="fas fa-users"></i> Users Data
                </a>
                <a href="manage_subscriptions.php" class="menu-item">
                    <i class="fas fa-calendar-check"></i> Subscriptions Data
                </a>

                <div class="menu-category">Delivery & Partner Management</div>
                <a href="manage_partners.php" class="menu-item">
                    <i class="fas fa-hands-helping"></i> Manage Partners
                </a>
                <a href="manage_delivery.php" class="menu-item">
                    <i class="fas fa-truck"></i>  Delivery Data
                </a>
                <a href="pending_delivery.php" class="menu-item">
                    <i class="fas fa-clock"></i> Pending Delivery
                </a>
                
                <div class="menu-category">Inquiry & Feedback Management</div>
                <a href="manage_inquiries.php" class="menu-item">
                    <i class="fas fa-users"></i> Manage Inquiries
                </a>
                <a href="view_feedback.php" class="menu-item">
                    <i class="fas fa-comment-alt"></i> View Feedback
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
                <h1>Admin Dashboard</h1>
                <p>Overview of Tiffinly's operations and statistics.</p>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_users; ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon subscriptions">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_subscriptions; ?></h3>
                        <p>Active Subscriptions</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon partners">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_partners; ?></h3>
                        <p>Total delivery partners</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon revenue">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($total_revenue, 2); ?></h3>
                        <p>Total Revenue</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon revenue">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format((float)$total_payouts, 2); ?></h3>
                        <p>Partner Payouts</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon revenue">
                        <i class="fas fa-balance-scale"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format((float)$net_revenue, 2); ?></h3>
                        <p>Net Revenue</p>
                    </div>
                </div>
            </div>
            
            <footer>
                <p>&copy; 2025 Tiffinly. All rights reserved.</p>
            </footer>
        </div>
    </div>
</body>
</html>
<?php
$db->close();
?>
