<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'delivery') {
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

function get_current_delivery_date($start_date, $end_date, $schedule) {
    $today = new DateTime();
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);

    if ($today < $start || $today > $end) {
        return null; // Not within the subscription period
    }

    $day_of_week = $today->format('N'); // 1 (Mon) to 7 (Sun)

    switch (strtolower($schedule)) {
        case 'daily':
            return $today->format('d M Y');
        case 'weekdays':
            if ($day_of_week >= 1 && $day_of_week <= 5) return $today->format('d M Y');
            break;
        case 'weekends':
            if ($day_of_week >= 6) return $today->format('d M Y');
            break;
    }
    return null; // No delivery scheduled for today
}

// Normalize schedule to one of: daily, weekdays, weekends
function normalize_schedule($schedule) {
    $s = strtolower(trim((string)$schedule));
    $map = [
        'daily' => 'daily', 'everyday' => 'daily', 'all' => 'daily', 'all days' => 'daily', 'fullweek' => 'daily', 'full week' => 'daily', 'extended' => 'daily',
        'weekday' => 'weekdays', 'weekdays' => 'weekdays', 'mon-fri' => 'weekdays', 'mon to fri' => 'weekdays',
        'weekend' => 'weekends', 'weekends' => 'weekends', 'sat-sun' => 'weekends', 'sat & sun' => 'weekends'
    ];
    return $map[$s] ?? 'daily';
}

// Count total delivery days in the subscription window based on schedule
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
        $dow = (int)$cursor->format('N');
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

// Fetch assigned deliveries
$today = date('Y-m-d');
$day_of_week_num = date('N'); // 1 for Monday, 7 for Sunday
$days_map = [
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
    7 => 'Sunday'
];
$day_of_week_str = strtoupper($days_map[$day_of_week_num]);

// Get all delivery assignments for this partner that are for today or future dates
$sql = "
    SELECT DISTINCT 
        da.assignment_id,
        s.subscription_id, s.start_date, s.end_date, s.schedule, s.plan_id, s.dietary_preference,
        u.name as user_name, u.phone, u.user_id,
        mp.plan_name,
        da.meal_type,
        da.delivery_date,
        da.status as delivery_status,
        dp.time_slot,
        a.line1, a.line2, a.city, a.state, a.pincode, a.landmark, a.address_type,
        COALESCE(sm.meal_name, m.meal_name) as meal_name
    FROM delivery_assignments da
    JOIN subscriptions s ON da.subscription_id = s.subscription_id
    JOIN users u ON s.user_id = u.user_id
    JOIN meal_plans mp ON s.plan_id = mp.plan_id    
    LEFT JOIN meals m ON da.meal_id = m.meal_id
    LEFT JOIN subscription_meals sm ON da.subscription_id = sm.subscription_id AND UPPER(DAYNAME(da.delivery_date)) = sm.day_of_week AND da.meal_type = sm.meal_type
    LEFT JOIN delivery_preferences dp ON s.user_id = dp.user_id AND LOWER(da.meal_type) = LOWER(dp.meal_type)
    LEFT JOIN addresses a ON dp.address_id = a.address_id
    WHERE da.partner_id = {$user_id} 
      AND da.delivery_date >= '{$today}'
      AND da.status IN ('pending','out_for_delivery')
      AND UPPER(TRIM(s.status)) IN ('ACTIVE','ACTIVATED')
      AND UPPER(TRIM(s.payment_status)) IN ('PAID','COMPLETED','SUCCESS')
";

// Do not filter by schedule at SQL layer; UI computes today's vs. next delivery

// Final ordering: latest assignment first, then by meal type for nice grouping
$sql .= "
    ORDER BY da.assignment_id DESC, FIELD(da.meal_type, 'Breakfast', 'Lunch', 'Dinner')
";


$deliveries_query = $db->query($sql);

// First, process all deliveries into the grouped_deliveries array
$grouped_deliveries = [];
$user_ids = [];

if ($deliveries_query) {
    while ($row = $deliveries_query->fetch_assoc()) {
        $sid = $row['subscription_id'];
        if (!isset($grouped_deliveries[$sid])) {
            $grouped_deliveries[$sid] = [
                'details' => $row,
                'meals' => []
            ];
            // Collect unique user IDs
            if (!in_array($row['user_id'], $user_ids)) {
                $user_ids[] = $row['user_id'];
            }
        }
        $grouped_deliveries[$sid]['meals'][] = $row;
    }
}

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
}

// For each subscription, find its own next delivery date and filter the meals to show only for that day.
// This prevents a subscription with a future start date from being hidden if another subscription has a delivery today.
$deliveries_to_display = [];
foreach ($grouped_deliveries as $sub_id => $data) {
    $next_delivery_date_for_sub = null;

    // Find the earliest delivery date for this specific subscription from the already fetched meals
    foreach ($data['meals'] as $meal) {
        $delivery_date = $meal['delivery_date'];
        if ($next_delivery_date_for_sub === null || $delivery_date < $next_delivery_date_for_sub) {
            $next_delivery_date_for_sub = $delivery_date;
        }
    }

    if ($next_delivery_date_for_sub !== null) {
        // Filter this subscription's meals to only show those for its next delivery date
        $filtered_meals = array_filter($data['meals'], function($meal) use ($next_delivery_date_for_sub) {
            return $meal['delivery_date'] === $next_delivery_date_for_sub;
        });

        // If there are meals for that date, prepare it for display
        if (!empty($filtered_meals)) {
            $display_data = $data;
            $display_data['meals'] = array_values($filtered_meals); // re-index
            $display_data['display_date'] = $next_delivery_date_for_sub; // store the date for the view
            $deliveries_to_display[$sub_id] = $display_data;
        }
    }
}

// Now fetch all addresses for the users with deliveries
$user_addresses = [];
if (!empty($user_ids)) {
    $addresses_query = $db->query("SELECT * FROM addresses WHERE user_id IN (" . implode(',', $user_ids) . ") ORDER BY user_id, address_type");
    while ($address = $addresses_query->fetch_assoc()) {
        $user_addresses[$address['user_id']][$address['address_type']] = $address;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Deliveries - Tiffinly</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../user/user_css/profile_style.css">
    <style>
        .address-type-badge {
            background-color: #e3f2fd;
            color: #1976d2;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
            text-transform: capitalize;
        }
        .address-type-badge.home {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        .address-type-badge.work {
            background-color: #e3f2fd;
            color: #1565c0;
        }
        .address-block {
            margin-bottom: 8px;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        .address-block:last-child {
            margin-bottom: 0;
        }
        .no-address {
            color: #6c757d;
            font-style: italic;
        }
        :root {
            --primary-color: #2C7A7B;
            --light-color: #F9F9F9;
            --dark-color: #2C3E50;
        }
        .main-content { padding: 30px; }
        .header h1 { font-size: 28px; margin-bottom: 5px; }
        .header p { color: #777; }
        .no-deliveries { text-align: center; color: #888; margin-top: 60px; font-size: 20px; }
        .delivery-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(44,122,123,0.09);
            padding: 28px 32px;
            margin-bottom: 25px;
            border: 1.5px solid #e9ecef;
        }
        .card-header {
            display: flex; align-items: center; gap: 18px; margin-bottom: 20px;
        }
        .card-header .icon { font-size: 22px; width: 44px; height: 44px; background: #2C7A7B; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .card-header .title { font-size: 18px; font-weight: 600; color: var(--primary-color); }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .info-item .label { color: #888; font-size: 14px; }
        .info-item .value { font-weight: 500; }
        .meal-details {
            margin-top: 20px;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 2px solid var(--light-gray);
        }

        .meal-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background-color: #fdfdfd;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            margin-bottom: 10px;
            gap: 15px;
        }

        .meal-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex-grow: 1;
        }

        .meal-badge {
            align-self: flex-start;
        }

        .meal-menu-items {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-color);
            font-size: 0.9rem;
        }

        .meal-menu-items i {
            color: var(--secondary-color);
        }

        .info-grid .full-width {
            grid-column: 1 / -1;
        }
        .meal-badge { background: #e6f6f7; color: #2C7A7B; border-radius: 12px; font-size: 13px; padding: 4px 14px; font-weight: 600; }
        .meal-time { font-size: 14px; color: #555; }
        .meal-address { font-size: 14px; color: #555; flex-grow: 1; }
        .meal-actions { display: flex; gap: 8px; }
        .btn-status {
            border: none; padding: 6px 14px; border-radius: 6px; font-weight: 600; font-size: 12px; cursor: pointer; transition: all 0.2s;
        }
        .btn-delivered {
            background-color: #27ae60; color: white;
        }
        .btn-delivered:hover { background-color: #219150; }
        .btn-not-delivered {
            background-color: #e74c3c; color: white;
        }
        .btn-status.btn-outfordelivery.marked {
    background-color: #ffe082 !important;
    color: #ff9800 !important;
    border: 1px solid #ff9800 !important;
}
        .btn-not-delivered:hover { background-color: #c0392b; }
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
    <div class="sidebar">
        <div class="sidebar-header"><i class="fas fa-utensils"></i>&nbsp Tiffinly</div>
        <div class="admin-profile">
            <div class="admin-avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
            <div class="admin-info">
                <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                <p><?php echo htmlspecialchars($user['email']); ?></p>
            </div>
        </div>
        <div class="sidebar-menu">
            <a href="partner_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="partner_profile.php" class="menu-item"><i class="fas fa-user"></i> My Profile</a>
            <div class="menu-category">Manage Deliveries</div>
            <a href="available_orders.php" class="menu-item"><i class="fas fa-search"></i> Available Orders</a>
            <a href="my_deliveries.php" class="menu-item active"><i class="fas fa-truck"></i> My Deliveries</a>
            <a href="delivery_history.php" class="menu-item"><i class="fas fa-history"></i> Delivery History</a>
            <a href="performance_review.php" class="menu-item"><i class="fas fa-chart-line"></i> Performance Review</a>
            <a href="earnings.php" class="menu-item"><i class="fas fa-wallet"></i> Earnings & Incentives</a>
            <a href="log_issues.php" class="menu-item"><i class="fas fa-exclamation-triangle"></i> Log Issues</a>
            <div style="margin-top: 30px;"><a href="../logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
        </div>
    </div>
    <div class="main-content">
        <div class="content-wrapper">
            <div class="header">
            <h1>My Deliveries</h1>
            <p>Your upcoming assigned deliveries. Today's tasks are active.</p>
        </div>
<br><br><br><br><br><br><br>
        <?php if (empty($deliveries_to_display)): ?>
            <div class="no-deliveries"><i class="fas fa-box-open"></i> You have no assigned deliveries yet.</div>
        <?php else: ?>
            <?php foreach ($deliveries_to_display as $sub_id => $data): ?>
                <div class="delivery-card">
                    <div class="card-header">
                        <div class="icon"><i class="fas fa-box"></i></div>
                        <div>
                            <div class="title">Order #<?php echo $sub_id; ?></div>
                            <?php 
                                $display_date_obj = new DateTime($data['display_date']);
                                $is_today_delivery = ($data['display_date'] === $today);
                                if ($is_today_delivery) {
                                    echo '<div style="font-size: 15px; color: #27ae60; font-weight: 600;"><i class="fas fa-star"></i> Today\'s Delivery: ' . $display_date_obj->format('d M Y') . '</div>';
                                } else {
                                    echo '<div style="font-size: 15px; color: #f39c12; font-weight: 600;"><i class="fas fa-arrow-right"></i> Upcoming Delivery: ' . $display_date_obj->format('d M Y') . '</div>';
                                }
                                // Expected deliveries over subscription = eligible days × 3 meals/day
                                $total_days = count_total_delivery_days($data['details']['start_date'] ?? null, $data['details']['end_date'] ?? null, $data['details']['schedule'] ?? 'daily');
                                if ($total_days > 0) {
                                    $current_day = count_eligible_days_until($data['details']['start_date'] ?? null, $data['details']['end_date'] ?? null, $data['details']['schedule'] ?? 'daily');
                                    $expected_meals = $total_days * 3;
                                    $sub_id_for_count = $data['details']['subscription_id'];
                                    $delivered_count = $delivery_counts[$sub_id_for_count]['delivered'] ?? 0;
                                    $cancelled_count = $delivery_counts[$sub_id_for_count]['cancelled'] ?? 0;
                                    $pending_meals = $expected_meals - $delivered_count - $cancelled_count;

                                    echo '<div style="font-size: 12px; color: #2C3E50; margin-top: 6px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; background: #f8f9fa; padding: 5px 8px; border-radius: 6px;">';
                                    echo '  <div style="color:#2C7A7B;"><i class="far fa-calendar-check"></i> Day <strong>' . $current_day . ' of ' . $total_days . '</strong></div>';
                                    echo '  <div style="color:#27ae60;"><i class="fas fa-check-circle"></i> Delivered: <strong>' . $delivered_count . '</strong></div>';
                                    echo '  <div style="color:#e74c3c;"><i class="fas fa-times-circle"></i> Cancelled: <strong>' . $cancelled_count . '</strong></div>';
                                    echo '  <div style="color:#3498db;"><i class="fas fa-hourglass-half"></i> Pending: <strong>' . max(0, $pending_meals) . '</strong></div>';
                                    echo '</div>';
                                }
                            ?>
                        </div>
                    </div>
                    <?php
                        // Build a compact preview summary (strict order: Breakfast, Lunch, Dinner)
                        $desired = ['Breakfast','Lunch','Dinner'];
                        $seen = [];
                        foreach ($data['meals'] as $m) {
                            $t = ucfirst(strtolower((string)$m['meal_type']));
                            if (in_array($t, $desired, true)) { $seen[$t] = true; }
                        }
                        $types = array_values(array_filter($desired, fn($t) => isset($seen[$t])));
                        $taskCount = count($data['meals']);
                    ?>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="label"><i class="fas fa-user"></i> Customer</div>
                            <div class="value"><?php echo htmlspecialchars($data['details']['user_name']); ?> (<?php echo htmlspecialchars($data['details']['phone']); ?>)</div>
                        </div>
                        <div class="info-item">
                            <div class="label"><i class="fas fa-calendar-alt"></i> Plan & Schedule</div>
                            <div class="value"><?php echo htmlspecialchars($data['details']['plan_name']); ?>, <?php echo htmlspecialchars($data['details']['schedule']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label"><i class="fas fa-eye"></i> Preview</div>
                            <div class="value">
                                <?php echo htmlspecialchars(implode(', ', $types)); ?>
                                <span class="badge" style="margin-left:8px;"><?php echo (int)$taskCount; ?> task<?php echo $taskCount==1?'':'s'; ?></span>
                            </div>
                        </div>
                    </div>

                    <div id="details-<?php echo (int)$sub_id; ?>" class="collapsible-content" style="display:none;">
                    <div class="info-grid">
                        <div class="info-item full-width">
                            <div class="label"><i class="fas fa-map-marker-alt"></i> Delivery Addresses</div>
                            <div class="value">
                                <?php 
                                $user_id = $data['details']['user_id'];
                                if (!empty($user_addresses[$user_id])) {
                                    foreach ($user_addresses[$user_id] as $type => $address) {
                                        echo '<div class="address-block">';
                                        echo '<span class="address-type-badge ' . $type . '">' . ucfirst($type) . '</span> ';
                                        echo htmlspecialchars($address['line1']);
                                        if (!empty($address['line2'])) echo ', ' . htmlspecialchars($address['line2']);
                                        echo ', ' . htmlspecialchars($address['city'] . ' - ' . $address['pincode']);
                                        echo '</div>';
                                    }
                                } else {
                                    echo '<div class="no-address">No delivery addresses found</div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <div class="meal-details">
                        <div class="section-title">
                            <?php if ($is_today_delivery): ?>
                                Today's Menu & Tasks
                            <?php else: ?>
                                Menu & Tasks for <?php echo $display_date_obj->format('d M Y'); ?> (Preview)
                            <?php endif; ?>
                        </div>
                        <?php
                            // Sort assigned meals by desired order: Breakfast -> Lunch -> Dinner
                            $orderMap = ['Breakfast' => 1, 'Lunch' => 2, 'Dinner' => 3];
                            $sortedMeals = $data['meals'];

                            usort($sortedMeals, function($a, $b) use ($orderMap) {
                                $oa = $orderMap[$a['meal_type']] ?? 99;
                                $ob = $orderMap[$b['meal_type']] ?? 99;
                                if ($oa === $ob) {
                                    // fallback stable-ish by assignment_id if available
                                    return ($a['assignment_id'] <=> $b['assignment_id']);
                                }
                                return $oa <=> $ob;
                            });

                            // Now loop through each meal type in sorted order
                            foreach ($sortedMeals as $meal): // This loops through assigned meal types (Breakfast, Lunch, Dinner)
                                $meal_type = $meal['meal_type'];
                                $menu_item = $meal['meal_name'] ?? 'Meal name not found';
                        ?>
                            <div class="meal-item">
                                <div class="meal-info">
                                    <div class="meal-badge"><?php echo ucfirst($meal_type); ?> 
                                        <span class="address-type-badge <?php echo strtolower($meal['address_type']); ?>">
                                            <?php echo ucfirst($meal['address_type']); ?>
                                        </span>
                                    </div>
                                    <div class="meal-time"><i class="fas fa-clock"></i> <?php echo htmlspecialchars($meal['time_slot']); ?></div>
                                    <div class="meal-menu-items">
                                        <i class="fas fa-utensils"></i>
                                        <span>
                                            <?php echo htmlspecialchars($menu_item); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="meal-actions">
                                    <button class="btn-status btn-outfordelivery<?php echo $is_today_delivery ? '' : ' disabled'; ?>" data-id="<?php echo $meal['assignment_id']; ?>" data-status="out_for_delivery" <?php echo $is_today_delivery ? '' : 'disabled title=\"Actions available on ' . $display_date_obj->format('d M Y') . '\"'; ?>><i class="fas fa-truck"></i> Out for Delivery</button>
                                    <button class="btn-status btn-delivered<?php echo $is_today_delivery ? '' : ' disabled'; ?>" data-id="<?php echo $meal['assignment_id']; ?>" data-status="delivered" <?php echo $is_today_delivery ? '' : 'disabled title=\"Actions available on ' . $display_date_obj->format('d M Y') . '\"'; ?>><i class="fas fa-check-circle"></i> Delivered</button>
                                    <button class="btn-status btn-not-delivered<?php echo $is_today_delivery ? '' : ' disabled'; ?>" data-id="<?php echo $meal['assignment_id']; ?>" data-status="cancelled" <?php echo $is_today_delivery ? '' : 'disabled title=\"Actions available on ' . $display_date_obj->format('d M Y') . '\"'; ?>><i class="fas fa-times-circle"></i> Cancelled</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    </div> <!-- /collapsible-content -->
                    <div class="card-footer" style="display:flex; justify-content:flex-end; gap:8px; margin-top:10px;">
                        <button class="btn btn-outline toggle-details" data-target="details-<?php echo (int)$sub_id; ?>" aria-expanded="false">
                            <i class="fas fa-chevron-down"></i> View More
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    <div id="delivery-alert" style="display:none;position:fixed;top:20px;right:20px;z-index:9999;padding:16px 28px;background:#ffe082;color:#ff9800;border:1px solid #ff9800;border-radius:8px;font-weight:600;box-shadow:0 2px 8px rgba(0,0,0,0.08);transition:all 0.3s;"></div>
        <footer>
            <p>&copy; <?php echo date('Y'); ?> Tiffinly. All rights reserved.</p>
        </footer>
    </div>
</div>
<script>
// Toggle collapsible order details
document.querySelectorAll('.toggle-details').forEach(btn => {
    const targetId = btn.dataset.target;
    const panel = document.getElementById(targetId);
    if (!panel) return;
    btn.addEventListener('click', () => {
        const expanded = btn.getAttribute('aria-expanded') === 'true';
        if (expanded) {
            panel.style.display = 'none';
            btn.setAttribute('aria-expanded', 'false');
            btn.innerHTML = '<i class="fas fa-chevron-down"></i> View More';
        } else {
            panel.style.display = 'block';
            btn.setAttribute('aria-expanded', 'true');
            btn.innerHTML = '<i class="fas fa-chevron-up"></i> View Less';
        }
    });
});

document.querySelectorAll('.btn-status').forEach(button => {
    button.addEventListener('click', function() {
        if (this.hasAttribute('disabled')) {
            alert('These actions are only available on the day of delivery.');
            return;
        }
        const assignmentId = this.dataset.id;
        const status = this.dataset.status;
        const mealItem = this.closest('.meal-item');

        fetch('update_delivery_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `assignment_id=${assignmentId}&status=${status}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update button color logic
                var outBtn = mealItem.querySelector('.btn-outfordelivery');
                if (status === 'out_for_delivery') {
                    outBtn.classList.add('marked');
                } else if (status === 'delivered' || status === 'cancelled') {
                    outBtn.classList.remove('marked');
                }

                mealItem.style.opacity = '0.5';
                mealItem.style.pointerEvents = 'none';
                var alertDiv = document.getElementById('delivery-alert');
                let msg = '';
                if (status === 'out_for_delivery') {
                    msg = 'Marked as Out for Delivery';
                } else if (status === 'delivered') {
                    msg = 'Marked as Delivered';
                } else if (status === 'cancelled') {
                    msg = 'Marked as Cancelled';
                }
                if (alertDiv && msg) {
                    alertDiv.textContent = msg;
                    alertDiv.style.display = 'block';
                        setTimeout(function() {
                            alertDiv.style.display = 'none';
                            window.location.reload();
                        }, 1800);
                }
            } else {
                alert('Failed to update status: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating the status.');
        });
    });
});
</script>
</body>
</html>
