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

// Weekly meal plans data
$basic_plan = [
    'MONDAY' => [
        'breakfast' => ['Idli + chutney', '—'],
        'lunch' => ['Veg thali', 'Chicken curry + rice'],
        'dinner' => ['Veg curry', 'Egg curry + chapati']
    ],
    'TUESDAY' => [
        'breakfast' => ['Upma + banana', '—'],
        'lunch' => ['Chole + jeera rice', '—'],
        'dinner' => ['Veg kurma + chapati', 'Egg curry']
    ],
    'WEDNESDAY' => [
        'breakfast' => ['Poori + potato masala', '—'],
        'lunch' => ['Sambar + rice + papad', '—'],
        'dinner' => ['Dal fry', 'Chicken curry + chapati']
    ],
    'THURSDAY' => [
        'breakfast' => ['Dosa + chutney', '—'],
        'lunch' => ['Mixed veg curry + rice', '—'],
        'dinner' => ['Dal fry + roti', 'Egg masala']
    ],
    'FRIDAY' => [
        'breakfast' => ['Appam + coconut milk', '—'],
        'lunch' => ['Veg pulao', 'Egg masala + lemon rice'],
        'dinner' => ['Paneer curry + roti', 'Chicken curry']
    ],
    'SATURDAY' => [
        'breakfast' => ['Bread + jam/butter', 'Omelette + Toast'],
        'lunch' => ['Veg pulao + raita', 'Chicken pulao'],
        'dinner' => ['Chana masala + chapati', 'Egg curry']
    ],
    'SUNDAY' => [
        'breakfast' => ['Idiyappam + veg curry', 'Egg curry'],
        'lunch' => ['Sambar + rice', 'Chicken curry + rice'],
        'dinner' => ['Veg stew', 'Chicken curry + parotta']
    ]
];

$premium_plan = [
    'MONDAY' => [
        'breakfast' => ['Appam + veg stew', 'Appam + Chicken stew', 'Puttu + kadala curry', 'Masala dosa with sambar'],
        'lunch' => ['Veg biryani + raita + papad', 'Chicken biryani + raita + mirchi ka salan', 'Fish curry meal (rice + fish curry + sides)', 'Vegetable pulao + paneer butter masala'],
        'dinner' => ['Paneer curry + naan + Payasam + salad', 'Mutton fry + parotta + Payasam + salad', 'Chicken tikka masala + garlic naan + dessert', 'Vegetable kofta + roti + dal tadka']
    ],
    // ... (rest of premium plan data remains the same)
];

// Get all meals from both plans for autocomplete
$all_meals = [];
foreach ($basic_plan as $day => $meals) {
    foreach ($meals as $meal_type => $items) {
        foreach ($items as $item) {
            if ($item != '—') {
                $all_meals[] = $item;
            }
        }
    }
}
foreach ($premium_plan as $day => $meals) {
    foreach ($meals as $meal_type => $items) {
        foreach ($items as $item) {
            $all_meals[] = $item;
        }
    }
}
$all_meals = array_unique($all_meals);
sort($all_meals);

// Handle form submission
// Check for success message from redirect
$success_message = '';
if (isset($_SESSION['feedback_success'])) {
    $success_message = $_SESSION['feedback_success'];
    unset($_SESSION['feedback_success']);
}

$error_message = '';

// Process POST regardless of submit button name (Enter key may omit button name)
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $feedback_type = $_POST['feedback_type'];
    $rating = (int)$_POST['rating'];
    $comments = trim($_POST['comments']);
    $meal_id = ($feedback_type == 'meal' && !empty($_POST['meal_id'])) ? (int)$_POST['meal_id'] : null;
    $meal_search = ($feedback_type == 'meal' && isset($_POST['meal_search'])) ? trim($_POST['meal_search']) : '';
    $partner_id = ($feedback_type == 'service' && !empty($_POST['partner_id'])) ? (int)$_POST['partner_id'] : null;
    $delivery_date = ($feedback_type == 'service' && !empty($_POST['delivery_date'])) ? $_POST['delivery_date'] : null;
    
    // Validate inputs
    if(empty($feedback_type)) {
        $error_message = "Please select feedback type";
    } elseif($rating < 1 || $rating > 5) {
        $error_message = "Please provide a valid rating (1-5)";
    } elseif ($feedback_type == 'meal') {
        // If no meal_id but user typed a name, try resolving by name
        if (empty($meal_id) && $meal_search !== '') {
            // 1) exact match
            if (empty($meal_id)) {
                $resolve = $db->prepare("SELECT meal_id FROM meals WHERE is_active = 1 AND meal_name = ? LIMIT 1");
                if ($resolve) {
                    $resolve->bind_param("s", $meal_search);
                    $resolve->execute();
                    $res = $resolve->get_result();
                    if ($row = $res->fetch_assoc()) {
                        $meal_id = (int)$row['meal_id'];
                    }
                    $resolve->close();
                }
            }
            // 2) case-insensitive exact
            if (empty($meal_id)) {
                $resolve2 = $db->prepare("SELECT meal_id FROM meals WHERE is_active = 1 AND LOWER(meal_name) = LOWER(?) LIMIT 1");
                if ($resolve2) {
                    $resolve2->bind_param("s", $meal_search);
                    $resolve2->execute();
                    $res2 = $resolve2->get_result();
                    if ($row2 = $res2->fetch_assoc()) {
                        $meal_id = (int)$row2['meal_id'];
                    }
                    $resolve2->close();
                }
            }
            // 3) fuzzy LIKE - pick shortest match first
            if (empty($meal_id)) {
                $resolve3 = $db->prepare("SELECT meal_id FROM meals WHERE is_active = 1 AND meal_name LIKE ? ORDER BY CHAR_LENGTH(meal_name) ASC, meal_name ASC LIMIT 1");
                if ($resolve3) {
                    $like = '%' . $meal_search . '%';
                    $resolve3->bind_param("s", $like);
                    $resolve3->execute();
                    $res3 = $resolve3->get_result();
                    if ($row3 = $res3->fetch_assoc()) {
                        $meal_id = (int)$row3['meal_id'];
                    }
                    $resolve3->close();
                }
            }
        }
        if (!empty($meal_id)) {
            // Verify selected meal exists in meals table (active)
            $check_meal = $db->prepare("SELECT meal_id, meal_name FROM meals WHERE meal_id = ? AND is_active = 1");
            $check_meal->bind_param("i", $meal_id);
            $check_meal->execute();
            $meal_row = $check_meal->get_result()->fetch_assoc();
            if (!$meal_row) {
                $error_message = "Invalid meal selection. Please choose a valid meal.";
            }
        } elseif ($meal_search === '' || mb_strlen($meal_search) < 2) {
            $error_message = "Please type at least 2 characters or select a meal from suggestions";
        }
    } elseif ($feedback_type == 'service' && empty($delivery_date)) {
        $error_message = "Please provide the delivery date for service feedback";
    }

    if (empty($error_message)) {
        // Get meal details if it's a meal feedback
        $meal_description = null;
        if ($feedback_type == 'meal') {
            if ($meal_id) {
                // Fetch meal name for description
                $meal_query = $db->prepare("SELECT meal_name FROM meals WHERE meal_id = ?");
                $meal_query->bind_param("i", $meal_id);
                $meal_query->execute();
                $meal_result = $meal_query->get_result();
                if ($meal = $meal_result->fetch_assoc()) {
                    $meal_description = $meal['meal_name'];
                }
            } else {
                // Fallback to free-text description
                $meal_description = $meal_search;
            }
        }

        // If service feedback and a partner was selected, prefix comments with partner info
        if ($feedback_type === 'service' && !empty($partner_id)) {
            $p = $db->prepare("SELECT name, phone FROM users WHERE user_id = ?");
            if ($p) {
                $p->bind_param("i", $partner_id);
                $p->execute();
                $pr = $p->get_result();
                if ($prow = $pr->fetch_assoc()) {
                    $prefix = '[Partner: ' . ($prow['name'] ?? 'Unknown') . ' #' . (int)$partner_id . (isset($prow['phone']) && $prow['phone'] !== '' ? (' • ' . $prow['phone']) : '') . '] ';
                    $comments = $prefix . $comments;
                } else {
                    $comments = '[Partner ID: ' . (int)$partner_id . '] ' . $comments;
                }
                $p->close();
            }
        }

        // Prepare the SQL statement based on feedback type (match table schema)
        // Note: feedback.feedback_id is NOT NULL in schema; insert 0 explicitly
        if ($feedback_type == 'meal') {
            $stmt = $db->prepare("INSERT INTO feedback (feedback_id, user_id, feedback_type, rating, comments, meal_description) VALUES (0, ?, ?, ?, ?, ?)");
            if ($stmt) $stmt->bind_param("isiss", $user_id, $feedback_type, $rating, $comments, $meal_description);
        } elseif ($feedback_type == 'service') {
            $stmt = $db->prepare("INSERT INTO feedback (feedback_id, user_id, feedback_type, rating, comments, delivery_date) VALUES (0, ?, ?, ?, ?, ?)");
            if ($stmt) $stmt->bind_param("isisss", $user_id, $feedback_type, $rating, $comments, $delivery_date);
        } else {
            $stmt = $db->prepare("INSERT INTO feedback (feedback_id, user_id, feedback_type, rating, comments) VALUES (0, ?, ?, ?, ?)");
            if ($stmt) $stmt->bind_param("isis", $user_id, $feedback_type, $rating, $comments);
        }
        
        if(!$stmt) {
            $error_message = "Failed to prepare feedback insert: " . $db->error;
        } elseif($stmt->execute()) {
            // Store success message in session
            $_SESSION['feedback_success'] = "Thank you for your feedback! We appreciate your input.";
            // Redirect to prevent form resubmission
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $error_message = "Failed to submit feedback: " . ($stmt->error ?: $db->error);
        }
        if (isset($stmt) && $stmt instanceof mysqli_stmt) { $stmt->close(); }
    }
    // Safety net: if POST reached here without redirect and without an error set, show a generic message
    if (empty($error_message) && empty($success_message)) {
        $error_message = "Submission could not be completed due to an unexpected condition. Please try again.";
    }
}

// Get user's active subscription and meals
$meals_query = $db->prepare("
    SELECT 
        sm.id, 
        sm.meal_name, 
        sm.meal_type, 
        sm.day_of_week,
        s.start_date,
        CONCAT(sm.meal_name, ' (', UCASE(LEFT(sm.meal_type, 1)), LCASE(SUBSTRING(sm.meal_type, 2)), ' - ', 
               DATE_FORMAT(s.start_date, '%b %d, %Y'), ')') as display_text
    FROM subscription_meals sm
    JOIN subscriptions s ON sm.subscription_id = s.subscription_id
    WHERE s.user_id = ? 
    AND s.status = 'active'
    AND s.end_date >= CURDATE()
    ORDER BY FIELD(sm.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), 
             FIELD(sm.meal_type, 'breakfast', 'lunch', 'dinner'), 
             s.start_date DESC
");
$meals_query->bind_param("i", $user_id);
$meals_query->execute();
$meals_result = $meals_query->get_result();

// Get delivery partners who delivered to the user
$delivery_query = $db->prepare("
    SELECT DISTINCT da.partner_id, u.name, u.phone
    FROM delivery_assignments da
    JOIN users u ON da.partner_id = u.user_id
    JOIN subscriptions s ON da.subscription_id = s.subscription_id
    WHERE s.user_id = ?
    ORDER BY da.assigned_at DESC
");
$delivery_query->bind_param("i", $user_id);
$delivery_query->execute();
$delivery_partners = $delivery_query->get_result();

// Get user's previous feedback
$feedback_query = $db->prepare("
    SELECT f.*, 
           NULL as partner_name,  -- partner_id is not stored in feedback table
           f.delivery_date
    FROM feedback f 
    WHERE f.user_id = ? 
    ORDER BY f.created_at DESC
");
$feedback_query->bind_param("i", $user_id);
$feedback_query->execute();
$feedback_result = $feedback_query->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiffinly - Feedback Center</title>
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
        
        .feedback-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .feedback-section {
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
            position: relative;
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
        
        .feedback-list {
            margin-top: 30px;
        }
        
        .feedback-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .feedback-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .feedback-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .feedback-type {
            font-weight: 600;
            color: var(--primary-color);
            text-transform: capitalize;
        }
        
        .feedback-date {
            color: #666;
            font-size: 14px;
        }
        
        .rating-stars {
            color: var(--secondary-color);
            margin: 10px 0;
        }
        
        .feedback-comments {
            margin-bottom: 15px;
            color: var(--dark-color);
            line-height: 1.6;
        }
        
        .feedback-type-selector {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .feedback-type-btn {
            flex: 1;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .feedback-type-btn:hover {
            border-color: var(--primary-color);
        }
        
        .feedback-type-btn.active {
            border-color: var(--primary-color);
            background-color: rgba(29, 95, 96, 0.1);
        }
        
        .feedback-type-btn i {
            font-size: 24px;
            margin-bottom: 10px;
            display: block;
            color: var(--primary-color);
        }
        
        .rating-selector {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }
        
        .rating-star {
            font-size: 30px;
            color: #ddd;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .rating-star:hover,
        .rating-star.active {
            color: var(--secondary-color);
            transform: scale(1.2);
        }
        
        .type-specific-field {
            display: none;
        }
        
        .meal-suggestions {
            display: none;
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 8px 8px;
            width: calc(100% - 2px);
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-top: -8px;
        }
        
        .meal-suggestion {
            padding: 10px 15px;
            cursor: pointer;
            transition: background-color 0.2s;
            font-size: 14px;
        }
        
        .meal-suggestion:hover {
            background-color: #f5f5f5;
        }
        
        @media (max-width: 768px) {
            .feedback-container {
                padding: 15px;
            }
            
            .feedback-section {
                padding: 20px;
            }
            
            .feedback-type-selector {
                flex-direction: column;
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
                <a href="feedback.php" class="menu-item active">
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
            <div class="header">
                <div class="welcome-message">
                    <h1>Feedback Center</h1><br><br>
                    <p>We value your opinion! Share your experience with us</p>
                </div>
            </div>

            <div class="feedback-container">
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
                
                <section class="feedback-section">
                    <h2 class="section-title">Submit Your Feedback</h2>
                    <form method="POST" action="" id="feedback-form">
                        <div class="form-group">
                            <label>Feedback Type</label>
                            <div class="feedback-type-selector">
                                <div class="feedback-type-btn" onclick="selectFeedbackType('meal')">
                                    <i class="fas fa-utensils"></i>
                                    <span>Meal Feedback</span>
                                    <input type="radio" name="feedback_type" value="meal" id="meal_type" style="display: none;" <?php echo (!isset($_POST['feedback_type']) || $_POST['feedback_type'] == 'meal') ? 'checked' : ''; ?>>
                                </div>
                                <div class="feedback-type-btn" onclick="selectFeedbackType('service')">
                                    <i class="fas fa-concierge-bell"></i>
                                    <span>Service Feedback</span>
                                    <input type="radio" name="feedback_type" value="service" id="service_type" style="display: none;" <?php echo (isset($_POST['feedback_type']) && $_POST['feedback_type'] == 'service') ? 'checked' : ''; ?>>
                                </div>
                                <div class="feedback-type-btn" onclick="selectFeedbackType('platform')">
                                    <i class="fas fa-laptop"></i>
                                    <span>Platform Feedback</span>
                                    <input type="radio" name="feedback_type" value="platform" id="platform_type" style="display: none;" <?php echo (isset($_POST['feedback_type']) && $_POST['feedback_type'] == 'platform') ? 'checked' : ''; ?>>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Meal-specific field with autocomplete -->
                        <div class="form-group type-specific-field" id="meal-field">
                            <label for="meal_search">Search Meal</label>
                            <input type="text" class="form-control" id="meal_search" name="meal_search" placeholder="Type to search meals..." autocomplete="off">
                            <input type="hidden" id="meal_id" name="meal_id" value="<?php echo isset($_POST['meal_id']) ? (int)$_POST['meal_id'] : ''; ?>">
                            <div id="meal-suggestions" class="meal-suggestions"></div>
                            <small class="form-text text-muted">Search across our meals database</small>
                        </div>
                        
                        <!-- Service-specific field -->
                        <div class="form-group type-specific-field" id="service-field">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="partner_id">Delivery Partner</label>
                                    <select class="form-control" id="partner_id" name="partner_id" required>
                                        <option value="">-- Select delivery partner --</option>
                                        <?php 
                                        if ($delivery_partners->num_rows > 0) {
                                            while($partner = $delivery_partners->fetch_assoc()) {
                                                $selected = (isset($_POST['partner_id']) && $_POST['partner_id'] == $partner['partner_id']) ? 'selected' : '';
                                                echo "<option value='{$partner['partner_id']}' $selected>";
                                                echo htmlspecialchars("{$partner['name']} - {$partner['phone']}");
                                                echo "</option>";
                                            }
                                            // Reset pointer for future use
                                            $delivery_partners->data_seek(0);
                                        } else {
                                            echo '<option value="" disabled>No delivery partners found</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="delivery_date">Delivery Date</label>
                                    <input type="date" class="form-control" id="delivery_date" name="delivery_date" 
                                           max="<?php echo date('Y-m-d'); ?>"
                                           value="<?php echo isset($_POST['delivery_date']) ? htmlspecialchars($_POST['delivery_date']) : date('Y-m-d'); ?>"
                                           required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Your Rating</label>
                            <div class="rating-selector">
                                <?php 
                                $currentRating = isset($_POST['rating']) ? intval($_POST['rating']) : 5;
                                for($i = 1; $i <= 5; $i++): 
                                    $active = $i <= $currentRating ? 'active' : '';
                                ?>
                                    <i class="fas fa-star rating-star <?php echo $active; ?>" 
                                       data-value="<?php echo $i; ?>" 
                                       onclick="setRating(<?php echo $i; ?>)"></i>
                                <?php endfor; ?>
                                <input type="hidden" name="rating" id="rating" value="<?php echo isset($_POST['rating']) ? $_POST['rating'] : '5'; ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="comments">Your Comments</label>
                            <textarea class="form-control" id="comments" name="comments" placeholder="Tell us about your experience..." required><?php echo isset($_POST['comments']) ? htmlspecialchars($_POST['comments']) : ''; ?></textarea>
                        </div>
                        
                        <button type="submit" name="submit_feedback" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Feedback
                        </button>
                    </form>
                </section>
                
                <section class="feedback-section">
                    <h2 class="section-title">Your Previous Feedback</h2>
                    <div class="feedback-list">
                        <?php if($feedback_result->num_rows > 0): ?>
                            <?php while($feedback = $feedback_result->fetch_assoc()): ?>
                                <div class="feedback-item">
                                    <div class="feedback-header">
                                        <span class="feedback-type badge <?php 
                                            echo $feedback['feedback_type'] == 'meal' ? 'bg-primary' : 
                                                ($feedback['feedback_type'] == 'service' ? 'bg-success' : 'bg-info'); 
                                        ?>">
                                            <?php 
                                            $typeLabels = [
                                                'meal' => 'Meal Feedback',
                                                'service' => 'Delivery Feedback',
                                                'platform' => 'Platform Feedback'
                                            ];
                                            echo $typeLabels[$feedback['feedback_type']] ?? ucfirst($feedback['feedback_type']);
                                            ?>
                                        </span>
                                        <span class="feedback-date">
                                            <?php echo date('M d, Y h:i A', strtotime($feedback['created_at'])); ?>
                                        </span>
                                    </div>
                                    
                                    <?php if($feedback['feedback_type'] == 'meal' && !empty($feedback['meal_description'])): ?>
                                        <div class="feedback-detail">
                                            <i class="fas fa-utensils me-2"></i>
                                            <?php echo htmlspecialchars($feedback['meal_description']); ?>
                                        </div>
                                    <?php elseif($feedback['feedback_type'] == 'service'): ?>
                                        <div class="row">
                                            <?php if(!empty($feedback['partner_name'])): ?>
                                                <div class="col-md-6">
                                                    <div class="feedback-detail">
                                                        <i class="fas fa-truck me-2"></i>
                                                        <?php echo htmlspecialchars($feedback['partner_name']); ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if(!empty($feedback['delivery_date'])): ?>
                                                <div class="col-md-6">
                                                    <div class="feedback-detail">
                                                        <i class="far fa-calendar-alt me-2"></i>
                                                        <?php echo date('M d, Y', strtotime($feedback['delivery_date'])); ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="rating-stars">
                                        <?php 
                                        $rating = (int)$feedback['rating'];
                                        for($i = 1; $i <= 5; $i++): 
                                            $isActive = $i <= $rating;
                                            $class = $isActive ? 'fas text-warning' : 'far text-muted';
                                        ?>
                                            <i class="fa-star <?php echo $class; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    
                                    <?php if(!empty($feedback['comments'])): ?>
                                        <div class="feedback-comments">
                                            <p><?php echo nl2br(htmlspecialchars($feedback['comments'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p>You haven't submitted any feedback yet.</p>
                        <?php endif; ?>
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
        // Feedback type selection
        function selectFeedbackType(type) {
            // Hide all specific fields first and remove required attributes (excluding hidden inputs)
            document.querySelectorAll('.type-specific-field').forEach(field => {
                field.style.display = 'none';
                field.querySelectorAll('select, input').forEach(input => {
                    if (input.type !== 'hidden') input.required = false;
                });
            });

            // Check the corresponding radio programmatically
            const radio = document.getElementById(type + '_type');
            if (radio) radio.checked = true;

            // Toggle active class on type buttons
            document.querySelectorAll('.feedback-type-btn').forEach(btn => btn.classList.remove('active'));
            if (radio) {
                const btn = radio.closest('.feedback-type-btn');
                if (btn) btn.classList.add('active');
            }

            // Show the selected type's specific fields and make them required
            if (type === 'meal') {
                const mealField = document.getElementById('meal-field');
                if (mealField) {
                    mealField.style.display = 'block';
                    const mealSearch = document.getElementById('meal_search');
                    if (mealSearch) mealSearch.required = true;
                    const mealIdHidden = document.getElementById('meal_id');
                    if (mealIdHidden) mealIdHidden.required = false;
                }
            } else if (type === 'service') {
                const serviceField = document.getElementById('service-field');
                if (serviceField) {
                    serviceField.style.display = 'block';
                    const partner = document.getElementById('partner_id');
                    const date = document.getElementById('delivery_date');
                    if (partner) partner.required = true;
                    if (date) date.required = true;
                }
            }
        }
        
        // Initialize the form based on the selected feedback type
        document.addEventListener('DOMContentLoaded', function() {
            const selectedRadio = document.querySelector('input[name="feedback_type"]:checked');
            if (selectedRadio) {
                selectFeedbackType(selectedRadio.value);
            }
            
            // Set max date for delivery date to today
            const deliveryDateInput = document.getElementById('delivery_date');
            if (deliveryDateInput) {
                const today = new Date().toISOString().split('T')[0];
                deliveryDateInput.max = today;
                if (!deliveryDateInput.value) {
                    deliveryDateInput.value = today;
                }
            }
            
            // Initialize form validation
            const feedbackForm = document.getElementById('feedback-form');
            if (feedbackForm) {
                feedbackForm.addEventListener('submit', function(e) {
                    const feedbackType = document.querySelector('input[name="feedback_type"]:checked')?.value;
                    const rating = document.getElementById('rating')?.value;
                    const comments = document.getElementById('comments')?.value.trim();
                    
                    // Basic validation
                    if (!rating || rating < 1 || rating > 5) {
                        e.preventDefault();
                        alert('Please provide a rating between 1 and 5 stars');
                        return false;
                    }
                    
                    if (!comments) {
                        e.preventDefault();
                        alert('Please provide your comments');
                        return false;
                    }
                    
                    // Type-specific validation
                    if (feedbackType === 'meal') {
                        const mealId = document.getElementById('meal_id')?.value;
                        const mealSearch = document.getElementById('meal_search')?.value.trim();
                        // Allow submit if user typed at least 2 chars (server will resolve) or selected a suggestion (meal_id)
                        if (!mealId && (!mealSearch || mealSearch.length < 2)) {
                            e.preventDefault();
                            alert('Please type at least 2 characters or select a meal from suggestions');
                            return false;
                        }
                    }
                    
                    if (feedbackType === 'service') {
                        if (!document.getElementById('partner_id')?.value) {
                            e.preventDefault();
                            alert('Please select a delivery partner');
                            return false;
                        }
                        if (!document.getElementById('delivery_date')?.value) {
                            e.preventDefault();
                            alert('Please select a delivery date');
                            return false;
                        }
                    }
                    
                    return true;
                });
            }
        });
        
        // Star rating functionality
        function setRating(rating) {
            // Update the hidden input value
            const ratingInput = document.getElementById('rating');
            if (ratingInput) {
                ratingInput.value = rating;
            }
            
            // Update the visual stars
            const stars = document.querySelectorAll('.rating-star');
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.classList.add('active');
                    star.classList.remove('far');
                    star.classList.add('fas');
                } else {
                    star.classList.remove('active');
                    star.classList.remove('fas');
                    star.classList.add('far');
                }
            });
        }
        
        // Meal autocomplete functionality (AJAX search)
        document.addEventListener('DOMContentLoaded', function() {
            const input = document.getElementById('meal_search');
            const hiddenId = document.getElementById('meal_id');
            const box = document.getElementById('meal-suggestions');

            if (!input || !hiddenId || !box) return;

            let controller = null;
            function render(list) {
                box.innerHTML = '';
                if (!list || list.length === 0) { box.style.display = 'none'; return; }
                list.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'meal-suggestion';
                    div.textContent = item.meal_name;
                    div.addEventListener('click', () => {
                        input.value = item.meal_name;
                        hiddenId.value = item.meal_id;
                        box.style.display = 'none';
                    });
                    box.appendChild(div);
                });
                box.style.display = 'block';
            }

            input.addEventListener('input', async function() {
                const q = this.value.trim();
                hiddenId.value = '';
                if (q.length < 2) { box.style.display = 'none'; box.innerHTML=''; return; }
                try {
                    if (controller) controller.abort();
                    controller = new AbortController();
                    const res = await fetch('../ajax/search_meals.php?q=' + encodeURIComponent(q), { signal: controller.signal });
                    if (!res.ok) { box.style.display='none'; return; }
                    const data = await res.json();
                    render(Array.isArray(data) ? data : []);
                } catch(e) { /* aborted or network error */ }
            });

            document.addEventListener('click', function(e){ if (!box.contains(e.target) && e.target !== input) box.style.display='none'; });
            input.addEventListener('focus', function(){ if (box.children.length>0) box.style.display='block'; });
        });
    </script>
</body>
</html>