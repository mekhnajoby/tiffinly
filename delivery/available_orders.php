<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'delivery') {
    header("Location: ../login.php");
    exit();
}

$db = new mysqli('localhost', 'root', '', 'tiffinly');
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

$user_id = $_SESSION['user_id'];
// Get delivery partner details for sidebar
$user_query = $db->prepare("SELECT name, email FROM users WHERE user_id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user = $user_result->fetch_assoc();

// Helper function to normalize schedule names
function normalize_schedule($schedule) {
    $s = strtolower(trim((string)$schedule));
    $map = [
        'daily' => 'daily', 'everyday' => 'daily', 'all' => 'daily', 'all days' => 'daily', 'fullweek' => 'daily', 'full week' => 'daily', 'extended' => 'daily',
        'weekday' => 'weekdays', 'weekdays' => 'weekdays', 'mon-fri' => 'weekdays', 'mon to fri' => 'weekdays',
        'weekend' => 'weekends', 'weekends' => 'weekends', 'sat-sun' => 'weekends', 'sat & sun' => 'weekends'
    ];
    return $map[$s] ?? 'daily';
}

// Handle Accept/Reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subscription_id = intval($_POST['subscription_id']);
    $action = $_POST['action'];
    $meal_types = isset($_POST['meal_types']) ? explode(',', $_POST['meal_types']) : [];
    if ($action === 'accept') {
        $db->begin_transaction();
        try {
            $already_assigned = false;
            foreach ($meal_types as $meal_type) {
                $check = $db->prepare("SELECT * FROM delivery_assignments WHERE subscription_id = ? AND meal_type = ? FOR UPDATE");
                $check->bind_param("is", $subscription_id, $meal_type);
                $check->execute();
                $result = $check->get_result();
                if ($result->num_rows > 0) {
                    $already_assigned = true;
                    break;
                }
            }

            if (!$already_assigned) {
                // Fetch subscription details to generate all delivery assignments, including plan_id and dietary_preference
                $sub_stmt = $db->prepare("SELECT plan_id, start_date, end_date, schedule, dietary_preference FROM subscriptions WHERE subscription_id = ?");
                $sub_stmt->bind_param("i", $subscription_id);
                $sub_stmt->execute();
                $sub_details = $sub_stmt->get_result()->fetch_assoc();
                $sub_stmt->close();

                if (!$sub_details) {
                    throw new Exception("Subscription details not found.");
                }

                $start_date = new DateTime($sub_details['start_date']);
                $end_date = new DateTime($sub_details['end_date']);
                $schedule = normalize_schedule($sub_details['schedule']);
                $plan_id = $sub_details['plan_id'];
                $diet_pref = strtolower($sub_details['dietary_preference']);
                $today = new DateTime('today');

                // Prepare statement to insert assignments with meal_id
                $assign_stmt = $db->prepare("INSERT INTO delivery_assignments (subscription_id, partner_id, meal_type, meal_id, delivery_date, status, assigned_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");

                $cursor = clone $start_date;
                while ($cursor <= $end_date) {
                    // Only create assignments for today and future dates
                    if ($cursor >= $today) {
                        $dayOfWeek = (int)$cursor->format('N'); // 1=Mon, 7=Sun
                        $is_eligible = false;
                        switch ($schedule) {
                            case 'daily': $is_eligible = true; break;
                            case 'weekdays': $is_eligible = ($dayOfWeek <= 5); break;
                            case 'weekends': $is_eligible = ($dayOfWeek >= 6); break;
                        }

                        if ($is_eligible) {
                            $delivery_date_str = $cursor->format('Y-m-d');
                            foreach ($meal_types as $meal_type) {
                                // Find the meal_id for this specific day and meal_type
                                $day_of_week = strtoupper($cursor->format('l'));
                                $meal_id_to_assign = null;

                                $meal_sql = "
                                    SELECT pm.meal_id, mc.option_type
                                    FROM plan_meals pm
                                    JOIN meals m ON pm.meal_id = m.meal_id
                                    JOIN meal_categories mc ON m.category_id = mc.category_id
                                    WHERE pm.plan_id = ? AND pm.day_of_week = ? AND pm.meal_type = ? AND m.is_active = 1
                                ";
                                $meal_stmt = $db->prepare($meal_sql);
                                $meal_stmt->bind_param("iss", $plan_id, $day_of_week, $meal_type);
                                $meal_stmt->execute();
                                $meals_res = $meal_stmt->get_result();
                                $potential_meals = [];
                                while($row = $meals_res->fetch_assoc()) { $potential_meals[] = $row; }
                                $meal_stmt->close();

                                if (!empty($potential_meals)) {
                                    if ($diet_pref === 'non-veg' || $diet_pref === 'nonveg') {
                                        foreach ($potential_meals as $meal) {
                                            if (strtolower($meal['option_type']) !== 'veg') {
                                                $meal_id_to_assign = $meal['meal_id'];
                                                break;
                                            }
                                        }
                                    }
                                    if ($meal_id_to_assign === null) {
                                        $meal_id_to_assign = $potential_meals[0]['meal_id'];
                                    }
                                }

                                if (!$meal_id_to_assign) {
                                    throw new Exception("Could not find a valid meal for {$meal_type} on {$delivery_date_str}.");
                                }

                                $assign_stmt->bind_param("iisis", $subscription_id, $user_id, $meal_type, $meal_id_to_assign, $delivery_date_str);
                                if (!$assign_stmt->execute()) {
                                    throw new Exception("Failed to assign order for date " . $delivery_date_str);
                                }
                            }
                        }
                    }
                    $cursor->modify('+1 day');
                }
                $db->commit();
                $success_message = "Order accepted successfully! All deliveries have been scheduled.";
            } else {
                $db->rollback();
                $error_message = "Order has just been accepted by another partner.";
            }
        } catch (Exception $e) {
            $db->rollback();
            $error_message = "An error occurred: " . $e->getMessage();
        }
    } elseif ($action === 'reject') {
        $success_message = "Order rejected.";
    }
}

// Query available orders: paid, active, not assigned for each meal_type
$orders_query = $db->query("
    SELECT s.subscription_id, s.user_id, s.plan_id, s.start_date, s.end_date, s.schedule, u.name as user_name, u.phone, mp.plan_name, dp.meal_type, dp.time_slot, a.line1, a.line2, a.city, a.state, a.pincode, a.landmark, a.address_type
    FROM subscriptions s
    JOIN users u ON s.user_id = u.user_id
    JOIN meal_plans mp ON s.plan_id = mp.plan_id
    JOIN delivery_preferences dp ON dp.user_id = s.user_id
    JOIN addresses a ON a.address_id = dp.address_id
    WHERE s.payment_status = 'paid' AND s.status = 'active'
      AND (s.subscription_id, dp.meal_type) NOT IN (SELECT subscription_id, meal_type FROM delivery_assignments)
    ORDER BY s.subscription_id, dp.meal_type
");
$orders = [];
if ($orders_query) {
    while($row = $orders_query->fetch_assoc()) {
        $orders[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiffinly - Available Orders</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../user/user_css/profile_style.css">
    <style>
        .address-type-badge {
            background-color: #e0e7ff;
            color: #4f46e5;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 10px;
            margin-left: 8px;
            vertical-align: middle;
            display: inline-block;
        }

        .order-card { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.12); padding: 25px; margin-bottom: 25px; display: flex; flex-direction: column; }
        .order-details { margin-bottom: 15px; }
        .order-actions { display: flex; gap: 10px; }
        .btn-accept { background: #27ae60; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; transition: background 0.2s; }
        .btn-accept:hover { background: #219150; }
        .btn-reject { background: #e74c3c; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; transition: background 0.2s; }
        .btn-reject:hover { background: #c0392b; }
        .no-orders {
            text-align: center;
            padding: 60px 60px;
            margin-top: 30px;
            margin-bottom: 80px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            border: 1px solid #e9ecef;
        }
        .no-orders i {
            font-size: 4rem;
            color: var(--primary-color);
            opacity: 0.4;
            display: block;
            margin-bottom: 15px;
        }
        .no-orders-message {
            font-size: 1.2rem;
            color: var(--dark-color);
            font-weight: 500;
        }
        .header h1 { font-size: 28px; margin: 0; position: relative; display: inline-block; }
        .header h1:after { content: ''; position: absolute; bottom: -5px; left: 0; width: 50px; height: 3px; background-color: #2C7A7B; border-radius: 3px; transition: all 0.3s ease; }
        .header:hover h1:after { width: 100%; }
        footer { text-align: center; padding: 20px; margin-top: 40px; color: #777; font-size: 14px; border-top: 1px solid #eee; animation: fadeIn 0.8s ease-out; }
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
        display: flex;
        flex-direction: column;
    }

    .content-wrapper {
        flex: 1;
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
    .stat-icon.partners { background-color:#F39C12 ; }
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
    <!-- Sidebar Navigation (copied from partner_profile.php) -->
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

                <a href="partner_profile.php" class="menu-item">
                    <i class="fas fa-user"></i> My Profile
                </a>
            
                <div class="menu-category">Manage Deliveries</div>
                <a href="available_orders.php" class="menu-item active">
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
        <div class="content-wrapper">
            <div class="header">
                <div class="welcome-message">
                    <h1>Available Orders</h1>
                    <p>Accept or reject new delivery offers</p>
                </div>
            </div>
        <?php if(isset($success_message)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if(isset($error_message)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
        <?php endif; ?>
        <?php
// Group orders by subscription_id and collect meal details including address
$grouped_orders = [];
foreach ($orders as $order) {
    $sid = $order['subscription_id'];
    if (!isset($grouped_orders[$sid])) {
        $grouped_orders[$sid] = $order;
        $grouped_orders[$sid]['meals'] = [];
        $grouped_orders[$sid]['meal_details'] = [];
    }
    // Only add if not already added
    if (!array_key_exists($order['meal_type'], $grouped_orders[$sid]['meal_details'])) {
        $grouped_orders[$sid]['meals'][] = $order['meal_type'];
        $grouped_orders[$sid]['meal_details'][$order['meal_type']] = [
            'time_slot' => $order['time_slot'],
            'line1' => $order['line1'],
            'line2' => $order['line2'],
            'city' => $order['city'],
            'state' => $order['state'],
            'pincode' => $order['pincode'],
            'landmark' => $order['landmark'],
            'address_type' => $order['address_type'],
        ];
    }
}
?><br><br><br><br>
<?php if(count($grouped_orders) === 0): ?>
    <div class="no-orders">
                    <i class="fas fa-box-open"></i>
                    <div class="no-orders-message">No available orders at the moment.</div>
                </div>
<?php endif; ?>
<style>
.order-card {
    background: #f9fafb;
    border-radius: 14px;
    box-shadow: 0 2px 8px rgba(44,122,123,0.09);
    padding: 30px;
    margin-bottom: 35px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    transition: box-shadow 0.2s;
    border: 1.5px solid #e9ecef;
    position: relative;
}
.order-card:hover {
    box-shadow: 0 6px 22px rgba(44,122,123,0.13);
}
.order-header {
    display: flex;
    align-items: center;
    gap: 18px;
    margin-bottom: 10px;
}
.order-header .order-id {
    font-size: 18px;
    font-weight: 600;
    color: #2C7A7B;
    letter-spacing: 1px;
}
.order-header .user-avatar {
    width: 44px;
    height: 44px;
    background: #2C7A7B;
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    font-weight: 700;
}
.order-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px 32px;
    margin-bottom: 8px;
}
.order-section .label {
    color: #888;
    font-weight: 500;
    font-size: 14px;
}
.order-section .value {
    color: #222;
    font-size: 15px;
    font-weight: 500;
}
.order-divider {
    border-bottom: 1px solid #e3e6e8;
    margin: 10px 0 16px 0;
}
.order-meals {
    margin-bottom: 8px;
}
.meal-badge {
    background: #e6f6f7;
    color: #2C7A7B;
    border-radius: 12px;
    font-size: 13px;
    padding: 4px 14px;
    margin-right: 8px;
    margin-bottom: 4px;
    display: inline-block;
    font-weight: 600;
    letter-spacing: 0.5px;
}
.meal-time {
    font-size: 13px;
    color: #555;
    margin-right: 14px;
}
.order-actions {
    display: flex;
    gap: 16px;
    margin-top: 18px;
}
.btn-accept {
    background: linear-gradient(90deg, #27ae60 80%, #2C7A7B 100%);
    color: white;
    border: none;
    padding: 11px 30px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    box-shadow: 0 1px 6px rgba(39,174,96,0.08);
    transition: background 0.2s;
}
.btn-accept:hover {
    background: linear-gradient(90deg, #219150 80%, #24706c 100%);
}
.btn-reject {
    background: linear-gradient(90deg, #e74c3c 80%, #e67e22 100%);
    color: white;
    border: none;
    padding: 11px 30px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    box-shadow: 0 1px 6px rgba(231,76,60,0.08);
    transition: background 0.2s;
}
.btn-reject:hover {
    background: linear-gradient(90deg, #c0392b 80%, #e67e22 100%);
}
.btn-view-more {
    background: transparent;
    border: 1px solid #2C7A7B;
    color: #2C7A7B;
    padding: 8px 18px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    margin-top: 15px;
    align-self: flex-start;
}
.btn-view-more:hover {
    background: #2C7A7B;
    color: white;
}
.expandable-details {
    margin-top: 18px;
    padding-top: 18px;
    border-top: 1px dashed #e0e0e0;
    animation: fadeIn 0.4s ease;
    display: none; /* Hidden by default */
}
@media (max-width: 900px) {
    .order-section { grid-template-columns: 1fr; }
    .order-card { padding: 18px 8px; }
}
</style>
<?php foreach($grouped_orders as $order): ?>
    <div class="order-card">
        <div class="order-header">
            <div class="user-avatar">
                <i class="fas fa-box"></i>
            </div>
            <div>
                <span class="order-id">Order #<?php echo $order['subscription_id']; ?></span>
                <div style="font-size: 14px; color: #555;">
                    <i class="fas fa-calendar-day"></i> <?php echo date('d M Y', strtotime($order['start_date'])); ?> to <?php echo date('d M Y', strtotime($order['end_date'])); ?>
                </div>
            </div>
        </div>

        <div id="details-<?php echo $order['subscription_id']; ?>" class="expandable-details">
            <div class="order-section">
                <div>
                    <span class="label"><i class="fas fa-user"></i> Customer</span><br>
                    <span class="value"><?php echo htmlspecialchars($order['user_name']); ?> (<?php echo htmlspecialchars($order['phone']); ?>)</span>
                </div>
                <div>
                    <span class="label"><i class="fas fa-calendar-alt"></i> Plan & Schedule</span><br>
                    <span class="value"><?php echo htmlspecialchars($order['plan_name']); ?>, <?php echo htmlspecialchars($order['schedule']); ?></span>
                </div>
            </div>
            <div class="order-divider"></div>
            <div class="order-meals">
                <span class="label"><i class="fas fa-utensils"></i> Meals, Times & Addresses</span><br>
                <?php foreach($order['meals'] as $meal): ?>
                    <div style="margin-bottom:8px;">
                        <span class="meal-badge"><?php echo ucfirst($meal); ?></span>
                        <span class="meal-time"><i class="fas fa-clock"></i> <?php echo $order['meal_details'][$meal]['time_slot']; ?></span><br>
                        <span class="label" style="margin-left: 10px;"><i class="fas fa-map-marker-alt"></i> Address:</span>
                        <span class="address-type-badge"><?php echo ucfirst(htmlspecialchars($order['meal_details'][$meal]['address_type'])); ?></span>
                        <span class="value">
                            <?php echo htmlspecialchars($order['meal_details'][$meal]['line1']); ?>,
                            <?php echo htmlspecialchars($order['meal_details'][$meal]['line2']); ?>,
                            <?php echo htmlspecialchars($order['meal_details'][$meal]['city']); ?>,
                            <?php echo htmlspecialchars($order['meal_details'][$meal]['state']); ?> -
                            <?php echo htmlspecialchars($order['meal_details'][$meal]['pincode']); ?>
                            <?php if(!empty($order['meal_details'][$meal]['landmark'])): ?>
                                (<?php echo htmlspecialchars($order['meal_details'][$meal]['landmark']); ?>)
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="order-divider"></div>
            <form method="POST" class="order-actions">
                <input type="hidden" name="subscription_id" value="<?php echo $order['subscription_id']; ?>">
                <input type="hidden" name="meal_types" value="<?php echo htmlspecialchars(implode(',', $order['meals'])); ?>">
                <button type="submit" name="action" value="accept" class="btn-accept"><i class="fas fa-check"></i> Accept</button>
                <button type="submit" name="action" value="reject" class="btn-reject"><i class="fas fa-times"></i> Reject</button>
            </form>
        </div>
        
        <button class="btn-view-more" onclick="toggleDetails(this, <?php echo $order['subscription_id']; ?>)"><i class="fas fa-chevron-down"></i> View More</button>
    </div>
<?php endforeach; ?>
        </div>
        <footer>
            <p>&copy; <?php echo date('Y'); ?> Tiffinly. All rights reserved.</p>
        </footer>
    </div>
</div>
<?php $db->close(); ?>
<script>
function toggleDetails(button, subscriptionId) {
    var detailsDiv = document.getElementById('details-' + subscriptionId);
    
    if (detailsDiv.style.display === 'none' || detailsDiv.style.display === '') {
        detailsDiv.style.display = 'block';
        button.innerHTML = '<i class="fas fa-chevron-up"></i> View Less';
    } else {
        detailsDiv.style.display = 'none';
        button.innerHTML = '<i class="fas fa-chevron-down"></i> View More';
    }
}
</script>
</body>
</html>
