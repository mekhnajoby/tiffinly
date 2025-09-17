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

$address_query = $db->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC");
$address_query->bind_param("i", $user_id);
$address_query->execute();
$address_result = $address_query->get_result();

$addresses = [];
while ($row = $address_result->fetch_assoc()) {
    $addresses[] = $row;
}

// Initialize preferences array
$preferences = [
    'breakfast' => ['address_id' => null, 'time_slot' => null],
    'lunch' => ['address_id' => null, 'time_slot' => null],
    'dinner' => ['address_id' => null, 'time_slot' => null]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_preferences'])) {
    // On POST, preferences will be updated below as usual
} else {
    // On GET (navigation), fetch saved preferences from DB if they exist
    $pref_query = $db->prepare("SELECT meal_type, address_id, time_slot FROM delivery_preferences WHERE user_id = ?");
    $pref_query->bind_param("i", $user_id);
    $pref_query->execute();
    $pref_result = $pref_query->get_result();
    while ($row = $pref_result->fetch_assoc()) {
        $preferences[$row['meal_type']] = [
            'address_id' => $row['address_id'],
            'time_slot' => $row['time_slot']
        ];
    }
}

// Available time slots
$time_slots = [
    'breakfast' => [
        '07:00 - 08:00',
        '08:00 - 09:00',
        '09:00 - 10:00'
    ],
    'lunch' => [
        '12:00 - 13:00',
        '13:00 - 14:00',
        '14:00 - 15:00'
    ],
    'dinner' => [
        '19:00 - 20:00',
        '20:00 - 21:00',
        '21:00 - 22:00'
    ]
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_preferences'])) {
    // Delete existing preferences
    $delete_query = $db->prepare("DELETE FROM delivery_preferences WHERE user_id = ?");
    $delete_query->bind_param("i", $user_id);
    $delete_query->execute();
    
    // Insert new preferences
    $insert_query = $db->prepare("INSERT INTO delivery_preferences (user_id, meal_type, address_id, time_slot) VALUES (?, ?, ?, ?)");
    
    foreach (['breakfast', 'lunch', 'dinner'] as $meal_type) {
        if (isset($_POST[$meal_type . '_address'])) {
            $address_id = $_POST[$meal_type . '_address'];
            $time_slot = $_POST[$meal_type . '_time'] ?? null;
            
            $insert_query->bind_param("isis", $user_id, $meal_type, $address_id, $time_slot);
            $insert_query->execute();
            
            // Update preferences array
            $preferences[$meal_type] = [
                'address_id' => $address_id,
                'time_slot' => $time_slot
            ];
        }
    }
    
    $success_message = "Delivery preferences updated successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiffinly - Delivery Preferences</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="user_css/user_dashboard_style.css">
    <link rel="stylesheet" href="user_css/profile_style.css">
    <link rel="stylesheet" href="user_css/compare_plans_style.css">
    <style>
        :root {
            --primary-color: #1D5F60;
            --secondary-color: #F39C12;
            --dark-color: #333;
            --light-color: #f8f9fa;
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .delivery-preferences-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .meal-preference-section {
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
            transition: all 0.3s ease-in-out;
        }
        
        .section-title:hover:after {
            width: 38%;
        }
        
        .meal-title {
            font-size: 20px;
            color: var(--primary-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .meal-icon {
            font-size: 24px;
        }
        
        .preference-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .address-selection, .time-selection {
            margin-bottom: 20px;
        }
        
        .address-options, .time-options {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .address-option, .time-option {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .address-option:hover, .time-option:hover,
        .address-option.selected, .time-option.selected {
            border-color: var(--primary-color);
            background-color: rgba(29, 95, 96, 0.05);
        }
        
        .address-option input[type="radio"], .time-option input[type="radio"] {
            margin-right: 15px;
            appearance: none;
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            border: 2px solid #ccc;
            border-radius: 50%;
            outline: none;
            transition: all 0.2s ease;
        }
        
        .address-option input[type="radio"]:checked, 
        .time-option input[type="radio"]:checked {
            border-color: var(--primary-color);
            background-color: var(--primary-color);
        }
        
        .address-details {
            flex-grow: 1;
        }
        
        .address-type {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 5px;
        }
        
        .address-text {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .time-label {
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .form-actions {
            margin-top: 40px;
            display: flex;
            justify-content: flex-end;
            padding-top: 30px;
            border-top: 1px solid #eee;
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
        
        .no-address-message {
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            text-align: center;
            color: #666;
        }
        
        .no-address-message a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .no-address-message a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 12px;
            font-weight: 600;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 10px;
            background-color: var(--secondary-color);
            color: white;
        }
        
        @media (max-width: 768px) {
            .preference-options {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
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
                <a href="delivery_preferences.php" class="menu-item active">
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
                    <h1>Delivery Preferences</h1>
                    <p>Set your preferred delivery times and locations for each meal</p>
                </div>
            </div>

            <!-- Delivery Preferences Section -->
            <div class="delivery-preferences-container">
                <form method="POST" action="delivery_preferences.php">
                    <?php if(isset($success_message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                            <a href="payment.php" style="margin-left:10px; text-decoration: underline;">Click here to proceed to payment</a>
                        </div>
                    <?php endif; ?>
                    
                    <h2 class="section-title">Meal Delivery Preferences</h2>
                    
                    <!-- Breakfast Preferences -->
                    <div class="meal-preference-section">
                        <h3 class="meal-title">
                            <i class="fas fa-sun meal-icon"></i> Breakfast
                        </h3>
                        
                        <div class="preference-options">
                            <div class="address-selection">
                                <h4>Delivery Address</h4>
                                <?php if(count($addresses) > 0): ?>
                                    <div class="address-options">
                                        <?php foreach ($addresses as $address): ?>
                                            <label>
                                                <input type="radio" name="breakfast_address" value="<?php echo $address['address_id']; ?>" 
                                                    <?php echo ($preferences['breakfast']['address_id'] == $address['address_id']) ? 'checked' : ''; ?> required>
                                                <div class="address-option <?php echo ($preferences['breakfast']['address_id'] == $address['address_id']) ? 'selected' : ''; ?>">
                                                    <div class="address-details">
                                                        <div class="address-type">
                                                            <?php echo ucfirst($address['address_type']); ?> Address
                                                            <?php if($address['is_default']): ?>
                                                                <span class="badge">Default</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="address-text">
                                                            <?php echo htmlspecialchars($address['line1']); ?><br>
                                                            <?php if(!empty($address['line2'])) echo htmlspecialchars($address['line2']) . '<br>'; ?>
                                                            <?php echo htmlspecialchars($address['city'] . ', ' . $address['state'] . ' - ' . $address['pincode']); ?><br>
                                                            <?php if(!empty($address['landmark'])) echo 'Landmark: ' . htmlspecialchars($address['landmark']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="no-address-message">
                                        <p>You haven't added any delivery addresses yet.</p>
                                        <p><a href="profile.php">Add an address to your profile</a> to set delivery preferences.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="time-selection">
                                <h4>Preferred Time Slot</h4>
                                <div class="time-options">
                                    <?php foreach($time_slots['breakfast'] as $slot): ?>
                                        <label>
                                            <input type="radio" name="breakfast_time" value="<?php echo $slot; ?>" 
                                                <?php echo ($preferences['breakfast']['time_slot'] == $slot) ? 'checked' : ''; ?> required>
                                            <div class="time-option <?php echo ($preferences['breakfast']['time_slot'] == $slot) ? 'selected' : ''; ?>">
                                                <span class="time-label"><?php echo $slot; ?></span>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Lunch Preferences -->
                    <div class="meal-preference-section">
                        <h3 class="meal-title">
                            <i class="fas fa-utensils meal-icon"></i> Lunch
                        </h3>
                        
                        <div class="preference-options">
                            <div class="address-selection">
                                <h4>Delivery Address</h4>
                                <?php if(count($addresses) > 0): ?>
                                    <div class="address-options">
                                        <?php foreach ($addresses as $address): ?>
                                            <label>
                                                <input type="radio" name="lunch_address" value="<?php echo $address['address_id']; ?>" 
                                                    <?php echo ($preferences['lunch']['address_id'] == $address['address_id']) ? 'checked' : ''; ?> required>
                                                <div class="address-option <?php echo ($preferences['lunch']['address_id'] == $address['address_id']) ? 'selected' : ''; ?>">
                                                    <div class="address-details">
                                                        <div class="address-type">
                                                            <?php echo ucfirst($address['address_type']); ?> Address
                                                            <?php if($address['is_default']): ?>
                                                                <span class="badge">Default</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="address-text">
                                                            <?php echo htmlspecialchars($address['line1']); ?><br>
                                                            <?php if(!empty($address['line2'])) echo htmlspecialchars($address['line2']) . '<br>'; ?>
                                                            <?php echo htmlspecialchars($address['city'] . ', ' . $address['state'] . ' - ' . $address['pincode']); ?><br>
                                                            <?php if(!empty($address['landmark'])) echo 'Landmark: ' . htmlspecialchars($address['landmark']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="no-address-message">
                                        <p>You haven't added any delivery addresses yet.</p>
                                        <p><a href="profile.php">Add an address to your profile</a> to set delivery preferences.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="time-selection">
                                <h4>Preferred Time Slot</h4>
                                <div class="time-options">
                                    <?php foreach($time_slots['lunch'] as $slot): ?>
                                        <label>
                                            <input type="radio" name="lunch_time" value="<?php echo $slot; ?>" 
                                                <?php echo ($preferences['lunch']['time_slot'] == $slot) ? 'checked' : ''; ?> required>
                                            <div class="time-option <?php echo ($preferences['lunch']['time_slot'] == $slot) ? 'selected' : ''; ?>">
                                                <span class="time-label"><?php echo $slot; ?></span>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dinner Preferences -->
                    <div class="meal-preference-section">
                        <h3 class="meal-title">
                            <i class="fas fa-moon meal-icon"></i> Dinner
                        </h3>
                        
                        <div class="preference-options">
                            <div class="address-selection">
                                <h4>Delivery Address</h4>
                                <?php if(count($addresses) > 0): ?>
                                    <div class="address-options">
                                        <?php foreach ($addresses as $address): ?>
                                            <label>
                                                <input type="radio" name="dinner_address" value="<?php echo $address['address_id']; ?>" 
                                                    <?php echo ($preferences['dinner']['address_id'] == $address['address_id']) ? 'checked' : ''; ?> required>
                                                <div class="address-option <?php echo ($preferences['dinner']['address_id'] == $address['address_id']) ? 'selected' : ''; ?>">
                                                    <div class="address-details">
                                                        <div class="address-type">
                                                            <?php echo ucfirst($address['address_type']); ?> Address
                                                            <?php if($address['is_default']): ?>
                                                                <span class="badge">Default</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="address-text">
                                                            <?php echo htmlspecialchars($address['line1']); ?><br>
                                                            <?php if(!empty($address['line2'])) echo htmlspecialchars($address['line2']) . '<br>'; ?>
                                                            <?php echo htmlspecialchars($address['city'] . ', ' . $address['state'] . ' - ' . $address['pincode']); ?><br>
                                                            <?php if(!empty($address['landmark'])) echo 'Landmark: ' . htmlspecialchars($address['landmark']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="no-address-message">
                                        <p>You haven't added any delivery addresses yet.</p>
                                        <p><a href="profile.php">Add an address to your profile</a> to set delivery preferences.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="time-selection">
                                <h4>Preferred Time Slot</h4>
                                <div class="time-options">
                                    <?php foreach($time_slots['dinner'] as $slot): ?>
                                        <label>
                                            <input type="radio" name="dinner_time" value="<?php echo $slot; ?>" 
                                                <?php echo ($preferences['dinner']['time_slot'] == $slot) ? 'checked' : ''; ?> required>
                                            <div class="time-option <?php echo ($preferences['dinner']['time_slot'] == $slot) ? 'selected' : ''; ?>">
                                                <span class="time-label"><?php echo $slot; ?></span>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions" style="display: flex; justify-content: space-between; align-items: center; gap: 15px;">
    <a href="payment.php" id="proceedToPaymentBtn" class="back-btn" style="pointer-events:none;opacity:0.5;">
        Proceed to Payment &nbsp;<i class="fas fa-arrow-right"></i>
    </a>
    <button type="submit" name="save_preferences" class="btn btn-primary">
        <i class="fas fa-save"></i> Save Preferences
    </button>
</div>
<script>
// Enable Proceed to Payment only after preferences are saved in this session
// and all required fields are filled.
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.delivery-preferences-container form');
    const proceedBtn = document.getElementById('proceedToPaymentBtn');
    // If PHP sets a success message, enable the button
    if (document.querySelector('.alert-success')) {
        proceedBtn.style.pointerEvents = 'auto';
        proceedBtn.style.opacity = '1';
        // Auto-scroll to bottom after successful save
        setTimeout(function() {
            window.scrollTo({
                top: Math.max(document.body.scrollHeight, document.documentElement.scrollHeight),
                behavior: 'smooth'
            });
        }, 150);
    }
    // Additional client-side validation before submit
    form.addEventListener('submit', function(e) {
        let valid = true;
        ['breakfast','lunch','dinner'].forEach(function(meal) {
            const address = form.querySelector('input[name="'+meal+'_address"]:checked');
            const time = form.querySelector('input[name="'+meal+'_time"]:checked');
            if (!address || !time) {
                valid = false;
            }
        });
        if (!valid) {
            e.preventDefault();
            alert('Please select an address and time slot for all meals before saving preferences.');
        }
    });
});
</script>
                </form>
            </div>
           <!-- <div style="position: fixed; bottom: 30px; left: 30px; z-index: 1000;">
                <a href="payment.php" class="back-btn">
                    Proceed to Payment &nbsp;<i class="fas fa-arrow-right"></i>
                </a>
            </div>-->
            
            <!-- Footer -->
            <footer style="
                text-align: center;
                padding: 20px;
                margin-top: 40px;
                color: #777;
                font-size: 14px;
                border-top: 1px solid #eee;
            ">
                <p>&copy; 2025 Tiffinly. All rights reserved.</p>
            </footer>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Highlight selected address and time options
        function setupRadioSelection(selector) {
            const inputs = document.querySelectorAll(selector);
            inputs.forEach(input => {
                // Set initial state
                if(input.checked) {
                    input.nextElementSibling.classList.add('selected');
                }
                
                // Add change event
                input.addEventListener('change', function() {
                    // Remove selected class from all options in this group
                    const name = this.getAttribute('name');
                    document.querySelectorAll(`input[name="${name}"]`).forEach(i => {
                        i.nextElementSibling.classList.remove('selected');
                    });
                    
                    // Add selected class to current option
                    if(this.checked) {
                        this.nextElementSibling.classList.add('selected');
                    }
                });
            });
        }
        
        // Initialize for all radio inputs
        setupRadioSelection('.address-options input[type="radio"]');
        setupRadioSelection('.time-options input[type="radio"]');
    });
    </script>
</body>
</html>
<?php
$db->close();
?>