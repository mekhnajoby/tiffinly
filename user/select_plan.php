<?php
session_start();
include('../config/db_connect.php'); // Ensure this path is correct

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = '';

// Active subscription check
$has_active_subscription = false;
$active_check_sql = "SELECT subscription_id FROM subscriptions WHERE user_id = ? AND status = 'active' LIMIT 1";
$active_check_stmt = $conn->prepare($active_check_sql);
$active_check_stmt->bind_param("i", $user_id);
$active_check_stmt->execute();
$active_check_stmt->store_result();
if ($active_check_stmt->num_rows > 0) {
    $has_active_subscription = true;
}
$active_check_stmt->close();

// Edit/view mode: allow loading plan by subscription_id if posted or in GET
$edit_subscription_id = null;
if (isset($_POST['edit_subscription_id'])) {
    $edit_subscription_id = intval($_POST['edit_subscription_id']);
} elseif (isset($_GET['edit_subscription_id'])) {
    $edit_subscription_id = intval($_GET['edit_subscription_id']);
}

// Block new plan selection if active subscription
if ($has_active_subscription && !$edit_subscription_id) {
    $error_message = "You already have an active subscription. Please cancel it before ordering a new one.";
    // Optionally redirect to cart or just show message
}

// Get user data for sidebar
$user_query = $conn->prepare("SELECT name, email FROM users WHERE user_id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user = $user_result->fetch_assoc();

// If editing, fetch subscription and plan data
$edit_plan_selection = null;
if ($edit_subscription_id) {
    // Fetch subscription and meal selections from DB
    $sql = "SELECT s.*, mp.plan_name, mp.plan_type, mp.base_price FROM subscriptions s JOIN meal_plans mp ON s.plan_id = mp.plan_id WHERE s.subscription_id = ? AND s.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $edit_subscription_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $subscription = $result->fetch_assoc();
    if ($subscription) {
        $edit_plan_selection = [
            'plan_id' => $subscription['plan_id'],
            'plan_type' => $subscription['plan_type'],
            'option_type' => $subscription['dietary_preference'] ?? '',
            'schedule' => $subscription['schedule'],
            'start_date' => $subscription['start_date'],
            'duration_weeks' => round((strtotime($subscription['end_date'])-strtotime($subscription['start_date']))/604800),
        ];
    }
}

// Fetch meal plans from the database
$plans_query = $conn->query("SELECT * FROM meal_plans WHERE is_active = 1");
$meal_plans = [];
while ($row = $plans_query->fetch_assoc()) {
    $meal_plans[strtolower($row['plan_type'])] = $row; // Use plan_type as key, ensure lowercase
}

// Function to fetch meals for display
function fetch_meals_for_display($conn, $plan_id, $option_type = null) {
    $meals = [];
    $days = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'];
    foreach ($days as $day) {
        $meals[$day] = ['Breakfast' => [], 'Lunch' => [], 'Dinner' => []];
    }

    // Normalize requested option_type to canonical hyphenated lowercase (e.g., non-veg)
    $sql = "SELECT pm.day_of_week, pm.meal_type, m.meal_name, mc.option_type AS diet_type
            FROM plan_meals pm
            JOIN meals m ON pm.meal_id = m.meal_id
            JOIN meal_categories mc ON m.category_id = mc.category_id
            WHERE pm.plan_id = ? AND m.is_active = 1 AND pm.meal_id > 0
            ORDER BY FIELD(pm.day_of_week, 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'), FIELD(pm.meal_type, 'Breakfast', 'Lunch', 'Dinner'), m.meal_name";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $plan_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['meal_name']) && !empty($row['diet_type'])) {
                $day = strtoupper($row['day_of_week']);
                $slot = ucfirst(strtolower($row['meal_type']));
                $diet = $row['diet_type'];
                if (!isset($meals[$day])) {
                    $meals[$day] = ['Breakfast' => [], 'Lunch' => [], 'Dinner' => []];
                }
                $meals[$day][$slot][] = [
                    'name' => $row['meal_name'],
                    'type' => strtolower(trim($diet))
                ];
            }
        }
        $stmt->close();
    }
    return $meals;
    }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['customize_plan'])) {
    $plan_type = $_POST['plan_type'] ?? '';
    $option_type = $_POST['option_type'] ?? null;
    $schedule = $_POST['schedule'] ?? '';
    
    // Server-side date validation
    $start_date = $_POST['start_date'] ?? '';
    if ($start_date) {
        $selected_date = new DateTime($start_date);
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        if ($selected_date < $today) {
            $error_message = 'Start date cannot be in the past';
            $_SESSION['error'] = $error_message;
            header('Location: select_plan.php');
            exit();
        }
    }
    $start_date = $_POST['start_date'] ?? '';
    $end_date_input = $_POST['end_date'] ?? '';

    if (empty($plan_type) || empty($schedule) || empty($start_date) || empty($end_date_input)) {
        $error_message = 'Please fill in all required fields.';
    } elseif ($plan_type === 'basic' && empty($option_type)) {
        $error_message = 'Please select a dietary preference for the Basic plan.';
    } else {
        $start_date = new DateTime($_POST['start_date']);
        $end_date = new DateTime($end_date_input);
        $today = new DateTime();

        if ($start_date < $today->setTime(0, 0, 0)) {
            $error_message = "Start date cannot be in the past.";
        } elseif ($end_date <= $start_date) {
            $error_message = "End date must be after the start date.";
        } else {
            $plan_id = $meal_plans[$plan_type]['plan_id'];
            $_SESSION['plan_selection'] = [
                'plan_id' => $plan_id,
                'plan_type' => $plan_type,
                'option_type' => $option_type,
                'schedule' => $schedule,
                'start_date' => $start_date->format('Y-m-d'),
                'end_date' => $end_date->format('Y-m-d'),
            ];
            // If editing, pass the subscription_id to customize_meals.php
            if ($edit_subscription_id) {
                $_SESSION['plan_selection']['subscription_id'] = $edit_subscription_id;
            }
            header('Location: customize_meals.php');
            exit();
        }
    }
}

$basic_plan_id = $meal_plans['basic']['plan_id'] ?? 0;
$premium_plan_id = $meal_plans['premium']['plan_id'] ?? 0;

$meal_data = [
    'basic_veg' => fetch_meals_for_display($conn, $basic_plan_id, 'veg'),
    'basic_non_veg' => fetch_meals_for_display($conn, $basic_plan_id, 'non-veg'),
    'premium' => fetch_meals_for_display($conn, $premium_plan_id),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Your Meal Plan - Tiffinly</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="user_css/user_dashboard_style.css"> 
    <link rel="stylesheet" href="user_css/button_styles.css">
    <!-- For sidebar and general layout -->
    <style>
        /* Progress Bar */
        .progress-bar {
            display: flex;
            justify-content: space-between;
            width: 1000px;
            margin: 70px 0 50px 0;
            position: relative;
        }
        .progress-bar::before, .progress-line {
            content: '';
            position: absolute;
            top: 17px; /* Align with center of the step-number */
            left: 0;
            height: 4px;
            width: 100%;
            background-color: #e0e0e0;
            z-index: 1;
            transform: scaleX(0.9);
            transform-origin: left;
        }
        .progress-line {
            background-color: #1D5F60;
            width: 0%;
            transition: width 0.4s ease;
            z-index: 2;
        }
        .progress-step {
            text-align: center;
            position: relative;
            width: 25%;
            z-index: 3; /* Ensure steps are on top of the line */
        }
        .progress-step .step-number {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: #fff; /* Make background white to cover the line */
            color: #999;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
            border: 3px solid #e0e0e0;
            transition: all 0.3s ease;
        }
        .progress-step.active .step-number {
            background-color: #1D5F60;
            border-color: #1D5F60;
            color: #fff;
            box-shadow: 0 0 10px rgba(29, 95, 96, 0.5);
        }
        .progress-step .step-label {
            margin-top: 10px;
            color: #999;
            font-size: 14px;
        }
        .progress-step.active .step-label {
            color: #1D5F60;
            font-weight: bold;
        }
        .progress-bar::before {
            content: '';
            position: absolute;
            top: 17px;
            left: 0;
            right: 0;
            height: 3px;
            background-color: #e0e0e0;
            z-index: -1;
        }

        /* Premium Table Theme */
        .meal-plan-table.premium-theme th {
            background-color: #FFA500; /* Orange */
            color: white;
        }
        .meal-plan-table.premium-theme td {
            font-family: 'Poppins', sans-serif;
        }
        .non-veg-meal {
            color: #FFA500; /* Orange color like in the image */
            font-weight: 500;
        }

        /* Page-specific styles */
        .plan-selection-form { background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 6px 12px rgba(0,0,0,0.1); }
        .form-section { margin-bottom: 35px; }
        .form-section h2 { text-align: center; color: #333; margin-bottom: 20px; }
        .options-grid { display: flex; justify-content: center; gap: 25px; flex-wrap: wrap; }
        .option-label { cursor: pointer; }
        .option-label input[type="radio"] { display: none; }
        .option-card { border: 2px solid #e0e0e0; border-radius: 10px; padding: 25px; text-align: center; transition: all 0.3s ease; min-width: 200px; }
        .option-label input:checked + .option-card { border-color: #28a745; box-shadow: 0 0 15px rgba(40, 167, 69, 0.4); background-color: #f0fff4; transform: translateY(-5px); }
        .option-card h3 { margin-top: 0; color: #1D5F60; }
        .option-card p { color: #555; font-size: 1.1em; }
        #option-type-section { display: none; }
        .date-selection { display: flex; justify-content: center; gap: 20px; margin-top: 20px; flex-wrap: wrap;}
        .date-selection div { display: flex; flex-direction: column; }
        .date-selection label { margin-bottom: 5px; font-weight: 500; }
        .date-selection input, .date-selection select { padding: 12px; border: 1px solid #ccc; border-radius: 8px; font-size: 1em; background-color: #f9f9f9; transition: all 0.3s ease; }
        .date-selection input:focus, .date-selection select:focus { border-color: #1D5F60; box-shadow: 0 0 8px rgba(29, 95, 96, 0.2); outline: none; }
        .meal-plan-display { margin-top: 40px; margin-bottom: 40px; }
        .meal-plan-table { width: 100%; border-collapse: collapse; border-radius: 8px; margin-top: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
        .meal-plan-table th, .meal-plan-table td { border: 3px solid #dee2e6; padding: 14px; text-align: left; }
        .meal-plan-table th { background-color: #1D5F60; color: white; }
        .meal-plan-table td strong { color: #333; }
        .meal-plan-table tr:nth-child(even) { background-color: #f9f9f9; }
        .submit-btn { display: block; width: 400px; max-width: 90vw; margin: 30px auto 0 auto; padding: 13px 0; background-color:#2C7A7B; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 17px; font-weight: 600; transition: background-color 0.3s; text-align: center; }
        .submit-btn:hover { background-color:#1D5F60; }
        .error { color: #dc3545; text-align: center; margin-bottom: 20px; font-weight: 500; }
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
                <div class="user-avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
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
               
                <a href="select_plan.php" class="menu-item <?php if ($_SERVER['PHP_SELF'] == '/tiffinlycompletedpages/user/select_plan.php') echo 'active'; ?>"><i class="fas fa-check-circle"></i> Select Plan</a>
                <a href="customize_meals.php" class="menu-item <?php if ($_SERVER['PHP_SELF'] == '/tiffinlycompletedpages/user/customize_meals.php') echo 'active'; ?>"><i class="fas fa-sliders-h"></i> Customize Meals</a>
               
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
            <div class="header">
                <div class="welcome-message">
                    <h1>Select Your Plan</h1>
                    <p class="subtitle">Choose your meal plan and subscription schedule</p>

                    <div class="progress-bar">
        <div class="progress-line"></div>
                        <div class="progress-step active">
                            <div class="step-number">1</div>
                            <div class="step-label">Select Plan</div>
                        </div>
                        <div class="progress-step">
                            <div class="step-number">2</div>
                            <div class="step-label">Select Schedule</div>
                        </div>
                        <div class="progress-step">
                            <div class="step-number">3</div>
                            <div class="step-label">Plan Dates</div>
                        </div>
                        <div class="progress-step">
                            <div class="step-number">4</div>
                            <div class="step-label">Confirm</div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($has_active_subscription && !$edit_subscription_id): ?>
                <div class="plan-selection-form" style="text-align: center;">
                    <h2 style="color: #1D5F60;">Active Subscription Found</h2>
                    <p style="font-size: 1.1em; color: #555; max-width: 600px; margin: 20px auto;">
                        You already have an active subscription. You cannot select a new plan until your current one is canceled.
                    </p>
                    <a href="manage_subscriptions.php" class="submit-btn" style="text-decoration: none; display: inline-block; width: auto; padding: 13px 30px; margin-top: 20px;">Manage Subscription</a>
                </div>
            <?php else: ?>
                <form class="plan-selection-form" method="POST" action="select_plan.php">
                    <?php if ($error_message): ?>
                        <p class="error"><?php echo htmlspecialchars($error_message); ?></p>
                    <?php endif; ?>

                    <div class="form-section">
                        <h2>1. Choose Your Plan</h2>
                        <div class="options-grid">
                            <label class="option-label">
                                <input type="radio" name="plan_type" value="basic" required onchange="handlePlanChange()" <?php echo (isset($edit_plan_selection) && $edit_plan_selection['plan_type'] === 'basic') ? 'checked' : ''; ?>>
                                <div class="option-card">
                                    <h3>Basic Plan</h3>
                                    <p>₹<?php echo isset($meal_plans['basic']) ? number_format($meal_plans['basic']['base_price'], 2) : 'N/A'; ?>/day</p>
                                </div>
                            </label>
                            <label class="option-label">
                                <input type="radio" name="plan_type" value="premium" required onchange="handlePlanChange()" <?php echo (isset($edit_plan_selection) && $edit_plan_selection['plan_type'] === 'premium') ? 'checked' : ''; ?>>
                                <div class="option-card">
                                    <h3>Premium Plan</h3>
                                    <p>₹<?php echo isset($meal_plans['premium']) ? number_format($meal_plans['premium']['base_price'], 2) : 'N/A'; ?>/day</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div id="option-type-section" class="form-section">
                        <h2>2. Dietary Preference (For Basic Plan)</h2>
                        <div class="options-grid">
                            <label class="option-label">
                                <input type="radio" name="option_type" value="veg" onchange="updateMealPlanDisplay()" <?php echo (isset($edit_plan_selection) && $edit_plan_selection['option_type'] === 'veg') ? 'checked' : ''; ?>>
                                <div class="option-card"><h3>Vegetarian</h3></div>
                            </label>
                            <label class="option-label">
                                <input type="radio" name="option_type" value="non_veg" onchange="updateMealPlanDisplay()" <?php echo (isset($edit_plan_selection) && $edit_plan_selection['option_type'] === 'non_veg') ? 'checked' : ''; ?>>
                                <div class="option-card"><h3>Non-Vegetarian</h3></div>
                            </label>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>3. Select Schedule & Dates</h2>
                        <div class="options-grid">
                            <label class="option-label">
                                <input type="radio" name="schedule" value="Weekdays" required <?php echo (isset($edit_plan_selection) && $edit_plan_selection['schedule'] === 'Weekdays') ? 'checked' : ''; ?>>
                                <div class="option-card"><h3>Weekdays</h3><p>Mon-Fri</p><h4>Base price</h4></div>
                            </label>
                            <label class="option-label">
                                <input type="radio" name="schedule" value="Extended" <?php echo (isset($edit_plan_selection) && $edit_plan_selection['schedule'] === 'Extended') ? 'checked' : ''; ?>>
                                <div class="option-card"><h3>Extended</h3><p>Mon-Sat</p><h4>+ 20% of base price</h4></div>
                            </label>
                            <label class="option-label">
                                <input type="radio" name="schedule" value="Full Week" <?php echo (isset($edit_plan_selection) && $edit_plan_selection['schedule'] === 'Full Week') ? 'checked' : ''; ?>>
                                <div class="option-card"><h3>Full Week</h3><p>All 7 Days</p><h4>+ 75% of base price</h4></div>
                            </label>
                        </div>
                        <div class="date-selection">
                            <div>
                                <label for="start_date">Select Start Date:</label>
                                <input type="date" id="start_date" name="start_date" required 
                                    <?php 
                                    if (isset($edit_plan_selection) && !empty($edit_plan_selection['start_date'])) {
                                        echo 'value="' . $edit_plan_selection['start_date'] . '"';
                                    }
                                    ?>>
                                <small style="color:#1D5F60;">* Start date cannot be in the past.</small>
                                <?php if (isset($_SESSION['error'])): ?>
                                    <div class="error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label for="end_date">Select End Date:</label>
                                <input type="date" id="end_date" name="end_date" required 
                                    <?php 
                                    if (isset($edit_plan_selection) && !empty($edit_plan_selection['start_date'])) {
                                        $computed_end = (new DateTime($edit_plan_selection['start_date']))->modify('+4 weeks')->format('Y-m-d');
                                        echo 'value="' . $computed_end . '"';
                                    }
                                    ?>>
                                <small style="color:#1D5F60;">* End date must be after start date.</small>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="customize_plan" class="submit-btn">
                        <?php echo $edit_subscription_id ? 'Save Changes & Proceed' : 'Proceed to Customize Meals'; ?>
                    </button>
                </form>
                <div class="meal-plan-display">
                    <h2>Weekly Meal Menu</h2>
                    <table class="meal-plan-table" id="meal-plan-table">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Breakfast</th>
                                <th>Lunch</th>
                                <th>Dinner</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Rows will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
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
        const mealData = <?php echo json_encode($meal_data); ?>;
        const mealTableBody = document.querySelector('#meal-plan-table tbody');
        const mealTable = document.getElementById('meal-plan-table');

        function handlePlanChange() {
            const planType = document.querySelector('input[name="plan_type"]:checked').value;
            const optionSection = document.getElementById('option-type-section');
            const submitBtn = document.querySelector('.submit-btn');
            if (planType === 'basic') {
                optionSection.style.display = 'block';
                // Reset dietary choice if any
                document.querySelectorAll('input[name="option_type"]').forEach(r => r.checked = false);
                clearMealDisplay();
                submitBtn.textContent = 'Proceed';
            } else {
                optionSection.style.display = 'none';
                updateMealPlanDisplay();
                submitBtn.textContent = 'Proceed to Customize Meals';
            }
        }

        function updateMealPlanDisplay() {
            const planType = document.querySelector('input[name="plan_type"]:checked')?.value;
            const optionType = document.querySelector('input[name="option_type"]:checked')?.value;

            let dataToShow = null;
            mealTable.classList.remove('premium-theme');

            if (planType === 'premium') {
                dataToShow = mealData.premium;
                mealTable.classList.add('premium-theme');
            } else if (planType === 'basic') {
    // For basic plan, show only one non-veg slot (if available) per day, rest veg
    if (optionType === 'veg') {
        // Only show veg meals that have a non-veg alternative for the same slot and day
        dataToShow = {};
        const days = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'];
        const slots = ['Breakfast', 'Lunch', 'Dinner'];
        days.forEach(day => {
            dataToShow[day] = { Breakfast: [], Lunch: [], Dinner: [] };
            slots.forEach(slot => {
                const vegMeals = mealData.basic_veg[day][slot] || [];
                const nonVegMeals = mealData.basic_non_veg[day][slot] || [];
                // If there is a non-veg meal for this slot, show the veg meal(s) for this slot
                if (nonVegMeals.length > 0 && vegMeals.length > 0) {
                    dataToShow[day][slot] = vegMeals;
                } else {
                    dataToShow[day][slot] = [];
                }
            });
        });
    } else if (optionType === 'non_veg') {
        // Compose a new table with only one non-veg meal per day (if available), rest veg
        dataToShow = {};
        const days = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'];
        const slots = ['Breakfast', 'Lunch', 'Dinner'];
        days.forEach(day => {
            dataToShow[day] = { Breakfast: [], Lunch: [], Dinner: [] };
            // Find the first slot with a non-veg meal for the day
            let nonVegSlot = null;
            let nonVegMeal = null;
            for (const slot of slots) {
                const meals = mealData.basic_non_veg[day][slot];
                if (meals && meals.length > 0) {
                    // Find the first non-veg meal in this slot (normalize type)
                    const found = meals.find(m => {
                        if (typeof m === 'object' && m) {
                            const t = String(m.type || '').toLowerCase().replace('_','-');
                            return t === 'non-veg';
                        }
                        return false;
                    });
                    if (found) {
                        nonVegSlot = slot;
                        nonVegMeal = found;
                        break;
                    }
                }
            }
            // Assign meals for each slot
            slots.forEach(slot => {
                if (slot === nonVegSlot && nonVegMeal) {
                    dataToShow[day][slot] = [nonVegMeal];
                } else {
                    // Use veg meal for this slot
                    dataToShow[day][slot] = mealData.basic_veg[day][slot] || [];
                }
            });
        });
    } else {
        dataToShow = null;
    }
}
            
            renderMealTable(dataToShow);
        }

        function formatMeals(meals) {
            if (!meals || !Array.isArray(meals) || meals.length === 0) return 'N/A';

            return meals.map(meal => {
                // Premium plan sends objects: {name: '...', type: '...'}
                if (typeof meal === 'object' && meal !== null) {
                    const t = String(meal.type || '').toLowerCase().replace('_','-');
                    if (t === 'non-veg') {
                        return `<span class="non-veg-meal">${meal.name}</span>`;
                    }
                    return meal.name;
                } 
                // Basic plans send strings
                else if (typeof meal === 'string') {
                    return meal;
                }
                return ''; // Should not happen
            }).join('<br>');
        }

        function renderMealTable(data) {
            mealTableBody.innerHTML = '';
            if (!data) return;

            const days = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'];
            days.forEach(day => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${day.charAt(0).toUpperCase() + day.slice(1).toLowerCase()}</strong></td>
                    <td>${formatMeals(data[day]['Breakfast'])}</td>
                    <td>${formatMeals(data[day]['Lunch'])}</td>
                    <td>${formatMeals(data[day]['Dinner'])}</td>
                `;
                mealTableBody.appendChild(row);
            });
        }

        function clearMealDisplay() {
            mealTableBody.innerHTML = '';
        }

        document.addEventListener('DOMContentLoaded', function() {
            // --- Date Picker Setup and Validation ---
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            const today = new Date().toISOString().split('T')[0];

            // Set minimum date for both inputs to today
            if (startDateInput) {
                startDateInput.setAttribute('min', today);
                // If no value is set (e.g., on fresh load), default to today
                if (!startDateInput.value) {
                    startDateInput.value = today;
                }
            }
            if (endDateInput) {
                endDateInput.setAttribute('min', today);
            }

            // Function to handle date validation
            function validateDates() {
                if (!startDateInput.value || !endDateInput.value) return;

                const start = new Date(startDateInput.value);
                const end = new Date(endDateInput.value);

                // Ensure end date is not before start date
                if (end < start) {
                    alert('End date cannot be before the start date.');
                    endDateInput.value = startDateInput.value;
                }
                
                // Optional: Ensure a minimum duration, e.g., 7 days
                const diffTime = Math.abs(end - start);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                if (diffDays < 6) { // 7 days inclusive means 6 days difference
                    alert('Subscription must be for at least one week.');
                    const newEnd = new Date(start);
                    newEnd.setDate(newEnd.getDate() + 6);
                    endDateInput.value = newEnd.toISOString().split('T')[0];
                }
                
                // Also update the min attribute of end date when start date changes
                endDateInput.min = startDateInput.value;
            }

            // Add event listeners to trigger validation
            if (startDateInput) {
                startDateInput.addEventListener('change', validateDates);
            }
            if (endDateInput) {
                endDateInput.addEventListener('change', validateDates);
            }
            validateDates(); // Initial validation check on load

            // --- Progress Bar Logic ---
            const progressSteps = document.querySelectorAll('.progress-step');
            const formInputs = document.querySelectorAll('input[name="plan_type"], input[name="schedule"], input[name="option_type"], #start_date, #end_date');

            function updateProgressBar() {
                const progressLine = document.querySelector('.progress-line');
                let activeSteps = 0;

                const planType = document.querySelector('input[name="plan_type"]:checked');
                const schedule = document.querySelector('input[name="schedule"]:checked');
                const startDate = document.getElementById('start_date').value;
                const endDate = document.getElementById('end_date').value;

                // Step 1: Select Plan (always active on this page)
                progressSteps[0].classList.add('active');
                activeSteps = 1;

                // Step 2: Select Schedule, Start Date
                if (planType && schedule && startDate) {
                    progressSteps[1].classList.add('active');
                    activeSteps = 2;
                } else {
                    progressSteps[1].classList.remove('active');
                }

                // Step 3: Plan Dates
                if (planType && schedule && startDate && endDate) {
                    progressSteps[2].classList.add('active');
                    activeSteps = 3;
                } else {
                    progressSteps[2].classList.remove('active');
                }

                // Step 4: Confirm (for next page)
                progressSteps[3].classList.remove('active');

                // Update progress line width
                const totalSteps = progressSteps.length;
                const progressPercentage = ((activeSteps - 1) / (totalSteps - 1)) * 100;
                progressLine.style.width = `${progressPercentage}%`;
            }

            formInputs.forEach(input => {
                input.addEventListener('change', updateProgressBar);
            });

            // Initial check
            updateProgressBar();
            if(document.querySelector('input[name="plan_type"]:checked')) {
                handlePlanChange();
            }
        });
    </script>
</body>
</html>