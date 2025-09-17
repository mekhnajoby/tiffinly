<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'delivery') {
    header("Location: ../login.php");
    exit();
}

// Set user data from session
$user = [
    'name' => $_SESSION['name'] ?? 'Delivery Partner',
    'email' => $_SESSION['email'] ?? 'partner@example.com'
];

// Set page title
$page_title = "Dashboard";

// Database connection
$db = new mysqli('localhost', 'root', '', 'tiffinly');

// Check connection
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

$partner_id = $_SESSION['user_id'];

// Normalize schedule values to one of: daily, weekdays, weekends
function normalize_schedule($schedule) {
    $s = strtolower(trim((string)$schedule));
    $map = [
        'daily' => 'daily', 'everyday' => 'daily', 'all' => 'daily', 'all days' => 'daily', 'fullweek' => 'daily', 'full week' => 'daily', 'extended' => 'daily',
        'weekday' => 'weekdays', 'weekdays' => 'weekdays', 'mon-fri' => 'weekdays', 'mon to fri' => 'weekdays',
        'weekend' => 'weekends', 'weekends' => 'weekends', 'sat-sun' => 'weekends', 'sat & sun' => 'weekends'
    ];
    return $map[$s] ?? 'daily';
}

// Count total delivery days within start/end dates according to normalized schedule
function count_total_delivery_days($start_date, $end_date, $schedule) {
    if (empty($start_date) || empty($end_date)) return 0;
    try {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
    } catch (Exception $e) {
        return 0;
    }
    if ($end < $start) return 0;
    $norm = normalize_schedule($schedule);
    $count = 0;
    $cursor = clone $start;
    while ($cursor <= $end) {
        $dow = (int)$cursor->format('N'); // 1=Mon ... 7=Sun
        $ok = ($norm === 'daily') || ($norm === 'weekdays' && $dow >= 1 && $dow <= 5) || ($norm === 'weekends' && $dow >= 6);
        if ($ok) $count++;
        $cursor->modify('+1 day');
    }
    return $count;
}

// Count eligible delivery days from start_date up to reference date (capped by end_date)
function count_eligible_days_until($start_date, $end_date, $schedule, $reference = null) {
    if (empty($start_date) || empty($end_date)) return 0;
    try {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $ref = $reference ? new DateTime($reference) : new DateTime('today');
    } catch (Exception $e) {
        return 0;
    }
    if ($end < $start) return 0;
    if ($ref < $start) return 0;
    if ($ref > $end) $ref = $end;
    $norm = normalize_schedule($schedule);
    $count = 0;
    $cursor = clone $start;
    while ($cursor <= $ref) {
        $dow = (int)$cursor->format('N');
        $ok = ($norm === 'daily') || ($norm === 'weekdays' && $dow >= 1 && $dow <= 5) || ($norm === 'weekends' && $dow >= 6);
        if ($ok) $count++;
        $cursor->modify('+1 day');
    }
    return $count;
}

// Get available deliveries (active subscriptions not yet assigned)
$stmt = $db->prepare("
    SELECT COUNT(s.subscription_id) 
    FROM subscriptions s
    LEFT JOIN delivery_assignments da ON s.subscription_id = da.subscription_id
    WHERE s.status = 'active' AND da.assignment_id IS NULL
");
$stmt->execute();
$available_deliveries = $stmt->get_result()->fetch_row()[0];

// Get completed deliveries
$stmt = $db->prepare("SELECT COUNT(*) FROM delivery_assignments WHERE partner_id = ? AND status = 'delivered'");
$stmt->bind_param("i", $partner_id);
$stmt->execute();
$completed_deliveries = $stmt->get_result()->fetch_row()[0];

// Get all active deliveries for this partner
$stmt = $db->prepare("
    SELECT 
        s.subscription_id,
        s.schedule,
        s.start_date,
        s.end_date,
        u.name as user_name,
        u.phone,
        mp.plan_name,
        da.assignment_id,
        da.meal_type,
        da.delivery_date,
        da.status as delivery_status,
        dp.time_slot,
        a.line1, a.line2, a.city, a.state, a.pincode, a.landmark, a.address_type
    FROM delivery_assignments da
    JOIN subscriptions s ON da.subscription_id = s.subscription_id
    JOIN users u ON s.user_id = u.user_id
    JOIN meal_plans mp ON s.plan_id = mp.plan_id
    LEFT JOIN delivery_preferences dp ON s.user_id = dp.user_id AND LOWER(da.meal_type) = LOWER(dp.meal_type)
    LEFT JOIN addresses a ON dp.address_id = a.address_id
    WHERE da.partner_id = ?
      AND da.status IN ('pending', 'out_for_delivery')
      AND UPPER(TRIM(s.status)) IN ('ACTIVE','ACTIVATED')
      AND UPPER(TRIM(s.payment_status)) IN ('PAID','COMPLETED','SUCCESS')
    ORDER BY s.subscription_id, da.delivery_date, FIELD(da.meal_type, 'Breakfast', 'Lunch', 'Dinner')
");

$stmt->bind_param("i", $partner_id);
$stmt->execute();
$result = $stmt->get_result();

// Group deliveries by subscription for display
$grouped_deliveries = [];
while ($row = $result->fetch_assoc()) {
    $sub_id = $row['subscription_id'];
    if (!isset($grouped_deliveries[$sub_id])) {
        $grouped_deliveries[$sub_id] = [
            'subscription_id' => $sub_id,
            'user_name' => $row['user_name'],
            'phone' => $row['phone'],
            'plan_name' => $row['plan_name'],
            'schedule' => $row['schedule'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'meals' => []
        ];
    }
    $grouped_deliveries[$sub_id]['meals'][] = [
        'assignment_id' => $row['assignment_id'],
        'meal_type' => $row['meal_type'],
        'delivery_date' => $row['delivery_date'],
        'status' => $row['delivery_status'],
        'time_slot' => $row['time_slot'],
        'address' => [
            'line1' => $row['line1'],
            'line2' => $row['line2'],
            'city' => $row['city'],
            'state' => $row['state'],
            'pincode' => $row['pincode'],
            'landmark' => $row['landmark'],
            'address_type' => $row['address_type']
        ]
    ];
}

$stmt->close();

// Find the next upcoming delivery for each subscription
$today = date('Y-m-d');
$deliveries_to_display = [];
foreach ($grouped_deliveries as $sub_id => $delivery) {
    $next_delivery_date_for_sub = null;

    // Find the earliest delivery date for this specific subscription
    foreach ($delivery['meals'] as $meal) {
        $delivery_date = $meal['delivery_date'];
        if ($next_delivery_date_for_sub === null || $delivery_date < $next_delivery_date_for_sub) {
            $next_delivery_date_for_sub = $delivery_date;
        }
    }

    if ($next_delivery_date_for_sub !== null) {
        // Filter meals to only show those for the next delivery date
        $filtered_meals = array_filter($delivery['meals'], function($meal) use ($next_delivery_date_for_sub) {
            return $meal['delivery_date'] === $next_delivery_date_for_sub;
        });

        if (!empty($filtered_meals)) {
            $delivery['meals'] = array_values($filtered_meals);
            $delivery['display_date'] = $next_delivery_date_for_sub;
            $deliveries_to_display[$sub_id] = $delivery;
        }
    }
}
$grouped_deliveries = $deliveries_to_display;
// Fetch completed delivery counts for all relevant subscriptions
$subscription_ids = array_keys($grouped_deliveries);
$delivery_counts = [];
if (!empty($subscription_ids)) {
    $ids_string = implode(',', $subscription_ids);
    $count_query = $db->query("
        SELECT 
            subscription_id, 
            SUM(CASE WHEN status IN ('delivered', 'completed') THEN 1 ELSE 0 END) as delivered_count,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count
        FROM delivery_assignments
        WHERE subscription_id IN ($ids_string)
        GROUP BY subscription_id
    ");
    if ($count_query) {
        while ($row = $count_query->fetch_assoc()) {
            $delivery_counts[$row['subscription_id']] = [
                'delivered' => (int)$row['delivered_count'],
                'cancelled' => (int)$row['cancelled_count']
            ];
        }
    }

    // New query to get the TOTAL number of assignments for this partner and subscription
    $total_assignments_query = $db->query("
        SELECT subscription_id, COUNT(assignment_id) as total_assignments
        FROM delivery_assignments
        WHERE partner_id = {$partner_id} AND subscription_id IN ($ids_string)
        GROUP BY subscription_id
    ");
    if ($total_assignments_query) {
        while ($row = $total_assignments_query->fetch_assoc()) {
            $delivery_counts[$row['subscription_id']]['total'] = (int)$row['total_assignments'];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Tiffinly</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
    .address-type-badge {
        background-color: #e3f2fd;
        color: #1976d2;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: 500;
        text-transform: capitalize;
        display: inline-block;
    }
    .address-type-badge.home {
        background-color: #e8f5e9;
        color: #2e7d32;
    }
    .address-type-badge.work {
        background-color: #e3f2fd;
        color: #1565c0;
    }
    
    /* Schedule badge styles */
    .weekday-badge {
        background-color: #e3f2fd;
        color: #1565c0;
        border-left: 3px solid #1565c0;
    }
    
    .fullweek-badge {
        background-color: #e8f5e9;
        color: #2e7d32;
        border-left: 3px solid #2e7d32;
    }
    
    .extended-badge {
        background-color: #fff3e0;
        color: #e65100;
        border-left: 3px solid #e65100;
    }
    
    .default-badge {
        background-color: #f5f5f5;
        color: #616161;
        border-left: 3px solid #9e9e9e;
    }
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
                <a href="partner_dashboard.php" class="menu-item active">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>

                <a href="partner_profile.php" class="menu-item">
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
                <h1><?php echo $page_title; ?></h1>
                <p>Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</p>
            </div>

            <!-- Stats Cards -->
            <div class="dashboard-section">
                <div class="stats-cards">
                    <div class="stat-card">
                        <div class="stat-icon partners"><i class="fas fa-box-open"></i></div>
                        <div class="stat-info">
                            <h3><?php echo $available_deliveries; ?></h3>
                            <p>Available Deliveries</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon subscriptions"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-info">
                            <h3><?php echo $completed_deliveries; ?></h3>
                            <p>Completed Deliveries</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Deliveries Overview -->
            <div class="dashboard-section">
                <h2 class="section-title">Upcoming Deliveries</h2>
                <?php if (!empty($grouped_deliveries)): ?>
                    <?php foreach ($grouped_deliveries as $sub_id => $delivery): ?>
                        <?php
                            $pending_count = count($delivery['meals']);
                            $display_date_obj = new DateTime($delivery['display_date']);
                            $is_today_delivery = ($delivery['display_date'] === $today);
                        ?>
                        <div class="content-card" style="margin-bottom: 20px;">
                            <div class="delivery-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                                <div>
                                    <h3 style="margin: 0 0 5px 0; color: var(--primary-color);">Order #<?php echo $sub_id; ?></h3>
                                    <p style="margin: 0; color: #666;">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($delivery['user_name']); ?> 
                                        <span style="margin: 0 10px;">•</span>
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($delivery['phone']); ?>
                                    </p>

                                    <?php 
                                        // Corrected logic for delivery progress
                                        $total_days = count_total_delivery_days($delivery['start_date'] ?? null, $delivery['end_date'] ?? null, $delivery['schedule'] ?? 'daily');
                                        $expected_meals = $delivery_counts[$sub_id]['total'] ?? 0;
                                        if ($total_days > 0) {
                                            $current_day = count_eligible_days_until($delivery['start_date'] ?? null, $delivery['end_date'] ?? null, $delivery['schedule'] ?? 'daily');
                                            $delivered_count = $delivery_counts[$sub_id]['delivered'] ?? 0;
                                            $cancelled_count = $delivery_counts[$sub_id]['cancelled'] ?? 0;
                                            $pending_meals = $expected_meals - $delivered_count - $cancelled_count;

                                            echo '<div style="font-size: 12px; color: #2C3E50; margin-top: 6px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; background: #f8f9fa; padding: 5px 8px; border-radius: 6px;">';
                                            echo '  <div style="color:#2C7A7B;"><i class="far fa-calendar-check"></i> Day <strong>' . $current_day . ' of ' . $total_days . '</strong></div>';
                                            echo '  <div style="color:#27ae60;"><i class="fas fa-check-circle"></i> Delivered: <strong>' . $delivered_count . '</strong></div>';
                                            echo '  <div style="color:#e74c3c;"><i class="fas fa-times-circle"></i> Cancelled: <strong>' . $cancelled_count . '</strong></div>';
                                            echo '  <div style="color:#3498db;"><i class="fas fa-hourglass-half"></i> Pending: <strong>' . max(0, $pending_meals) . '</strong></div>';
                                            echo '</div>';
                                        }
                                    ?>
                                    <?php if ($is_today_delivery): ?>
                                        <div style="font-size: 14px; color: #27ae60; font-weight: 600;"><i class="fas fa-star"></i> Today's Delivery: <?php echo $display_date_obj->format('d M Y'); ?></div>
                                    <?php else: ?>
                                        <div style="font-size: 14px; color: #f39c12; font-weight: 600;"><i class="fas fa-arrow-right"></i> Upcoming Delivery: <?php echo $display_date_obj->format('d M Y'); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 5px;">
                                    <div style="display: flex; gap: 8px; align-items: center;">
                                        <span style="font-size: 0.9em; color: #666;">
                                            <i class="fas fa-utensils"></i> <?php echo $pending_count; ?> meal<?php echo $pending_count !== 1 ? 's' : ''; ?> pending
                                        </span>
                                        <?php 
                                        // Determine status class and text
                                        $status_class = 'status-pending';
                                        $status_text = 'Pending';
                                        $status_icon = 'clock';
                                        
                                        if (isset($delivery['delivery_status'])) {
                                            switch(strtolower($delivery['delivery_status'])) {
                                                case 'delivered':
                                                    $status_class = 'status-delivered';
                                                    $status_text = 'Delivered';
                                                    $status_icon = 'check-circle';
                                                    break;
                                                case 'out_for_delivery':
                                                    $status_class = 'status-out-for-delivery';
                                                    $status_text = 'Out for Delivery';
                                                    $status_icon = 'motorcycle';
                                                    break;
                                                case 'cancelled':
                                                    $status_class = 'status-cancelled';
                                                    $status_text = 'Cancelled';
                                                    $status_icon = 'times-circle';
                                                    break;
                                                default:
                                                    $status_icon = 'clock';
                                            }
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>" style="padding: 4px 12px; border-radius: 15px; font-size: 0.85em; font-weight: 500; display: inline-flex; align-items: center; gap: 5px;">
                                            <i class="fas fa-<?php echo $status_icon; ?>"></i>
                                            <?php echo $status_text; ?>
                                        </span>
                                    </div>
                                    <div style="font-size: 0.85em; color: #666; text-align: right;">
                                        <i class="far fa-calendar-alt"></i> 
                                        <?php 
                                        $start_date = new DateTime($delivery['start_date']);
                                        $end_date = new DateTime($delivery['end_date']);
                                        echo $start_date->format('M j') . ' - ' . $end_date->format('M j, Y');
                                        ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="details-sub-<?php echo $sub_id; ?>" class="meals-container" style="margin-top: 15px; display: none;">
                                <?php foreach ($delivery['meals'] as $meal): ?>
                                    <div class="meal-item" style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px dashed #eee;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                            <div class="meal-type" style="font-weight: 600; color: var(--dark-color);">
                                                <?php echo htmlspecialchars($meal['meal_type'] ?? 'N/A'); ?>
                                                <span class="address-type-badge <?php echo strtolower($meal['address']['address_type'] ?? 'home'); ?>" style="margin-left: 10px;">
                                                    <?php echo ucfirst($meal['address']['address_type'] ?? 'Home'); ?>
                                                </span>
                                            </div>
                                            <div class="meal-time" style="color: #666;">
                                                <i class="far fa-clock"></i> <?php echo htmlspecialchars($meal['time_slot'] ?? 'N/A'); ?>
                                            </div>
                                        </div>
                                        <div class="address" style="background: #f8f9fa; padding: 10px; border-radius: 6px; font-size: 0.9em;">
                                            <div style="color: #555; margin-bottom: 5px;">
                                                <i class="fas fa-map-marker-alt" style="color: #e74c3c; margin-right: 5px;"></i>
                                                <strong>Delivery Address:</strong>
                                            </div>
                                            <div style="line-height: 1.5;">
                                                <?php 
                                                echo htmlspecialchars($meal['address']['line1'] ?? 'Address not set');
                                                if (!empty($meal['address']['line2'])) {
                                                    echo ', ' . htmlspecialchars($meal['address']['line2'] ?? '');
                                                }
                                                echo '<br>' . htmlspecialchars(($meal['address']['city'] ?? '') . ', ' . ($meal['address']['state'] ?? '') . ' - ' . ($meal['address']['pincode'] ?? ''));
                                                if (!empty($meal['address']['landmark'])) {
                                                    echo '<br>Landmark: ' . htmlspecialchars($meal['address']['landmark'] ?? '');
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="delivery-actions" style="margin-top: 15px; display: flex; justify-content: flex-end; gap: 10px;">
                                <a href="#" class="btn toggle-details" data-target="#details-sub-<?php echo $sub_id; ?>" style="background: #f8f9fa; color: #333; text-decoration: none; padding: 8px 15px; border-radius: 4px; font-size: 0.9em;">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="content-card">
                        <p style="text-align: center; color: #666; padding: 20px 0;">
                            <i class="fas fa-check-circle" style="font-size: 2em; display: block; margin-bottom: 10px; opacity: 0.5; color: #27ae60;"></i>
                            All caught up! No upcoming deliveries.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
            <footer>
                <p>&copy; <?php echo date('Y'); ?> Tiffinly. All rights reserved.</p>
            </footer>
        </div>
    </div>


    <script>
    function updateDeliveryStatus(deliveryId, status) {
        // Show loading indicator
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner"></span> Processing';
        btn.disabled = true;
        
        $.post('../ajax/update_delivery_status.php', {
            delivery_id: deliveryId,
            status: status
        }, function(response) {
            if (response.success) {
                // Refresh the page to show updated status
                location.reload();
            } else {
                alert(response.message || 'Failed to update delivery status');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }).fail(function() {
            alert('Network error occurred');
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }

    function acceptDelivery(deliveryId) {
        if (confirm('Are you sure you want to accept this delivery?')) {
            updateDeliveryStatus(deliveryId, 'accepted');
        }
    }

    function showDeliveryDetails(deliveryId) {
        // Implement modal or page navigation to show delivery details
        window.location.href = 'delivery_details.php?id=' + deliveryId;
    }

    // Auto-refresh every 60 seconds
    setInterval(function() {
        $.get('../ajax/check_delivery_updates.php', function(data) {
            if (data.needsRefresh) {
                location.reload();
            }
        });
    }, 60000);

    // Toggle details in Current Active Deliveries
    $(document).on('click', '.toggle-details', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var target = $btn.data('target');
        var $details = $(target);
        if ($details.length === 0) return;
        var willOpen = !$details.is(':visible');
        $details.slideToggle(200, function() {
            if (willOpen) {
                $btn.html('<i class="fas fa-eye-slash"></i> Hide Details');
            } else {
                $btn.html('<i class="fas fa-eye"></i> View Details');
            }
        });
    });
    </script>
</body>
</html>
