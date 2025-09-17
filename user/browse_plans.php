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

// Default sample images (fallbacks)
$fallback_images = [
    'basic' => [
        'assets/meals/idlichutney.jpg',
        'assets/meals/appamcurry.jpg',
        'assets/meals/cholejeerarice.jpg',
        'assets/meals/chapathichicken.jpg'
    ],
    'premium' => [
        'assets/meals/masaladosa2.jpg',
        'assets/meals/muttonbiriyani.jpg',
        'assets/meals/paneertikkanaan.jpg',
        'assets/meals/prawnsfriedrice.jpg'
    ],
];

// Active subscription check
$has_active_subscription = false;
$active_check_sql = "SELECT subscription_id FROM subscriptions WHERE user_id = ? AND status = 'active' LIMIT 1";
$active_check_stmt = $db->prepare($active_check_sql);
$active_check_stmt->bind_param("i", $user_id);
$active_check_stmt->execute();
$active_check_stmt->store_result();
if ($active_check_stmt->num_rows > 0) {
    $has_active_subscription = true;
}
$active_check_stmt->close();

// Load plan details and pricing from DB
// Fetch active plans
$plans = [];
$plan_sql = "SELECT mp.plan_id, mp.plan_name, mp.description, mp.plan_type, mp.base_price, mp.is_active,
                    so.schedule_type, so.price_multiplier, so.days_count
             FROM meal_plans mp
             LEFT JOIN plan_schedule_options so ON so.plan_id = mp.plan_id
             WHERE mp.is_active = 1
             ORDER BY FIELD(mp.plan_type,'basic','premium'), mp.is_active DESC, mp.plan_id DESC";
$res = $db->query($plan_sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $pt = strtolower($row['plan_type']);
        if (!isset($plans[$pt])) {
                $plans[$pt] = [
                    'plan_id' => (int)$row['plan_id'],
                    'plan_name' => $row['plan_name'],
                    'description' => $row['description'],
                    'base_price' => $pt === 'basic' ? 250 : 320,
                    'schedules' => []
                ];
        }
        if (!empty($row["schedule_type"])) {
            // Only take schedules belonging to the chosen plan_id for this plan_type
            if ((int)$row['plan_id'] === (int)$plans[$pt]['plan_id']) {
                $plans[$pt]['schedules'][$row['schedule_type']] = [
                    'multiplier' => (float)$row['price_multiplier'],
                    'days' => is_null($row['days_count']) ? null : (int)$row['days_count']
                ];
            }
        }
    }
    $res->close();
}

// Provide defaults and normalize schedules: fixed days (5/6/7), default multipliers (1.00/1.20/1.75)
$schedule_display = [
    'weekdays' => 'Weekdays',
    'extended' => 'Extended Week',
    'full_week' => 'Full Week'
];
$schedule_defaults = [
    'weekdays' => ['days' => 5, 'mult' => 1.00],
    'extended' => ['days' => 6, 'mult' => 1.20],
    'full_week' => ['days' => 7, 'mult' => 1.75],
];
foreach (['basic','premium'] as $pt) {
    if (isset($plans[$pt])) {
        foreach (array_keys($schedule_display) as $key) {
            if (!isset($plans[$pt]['schedules'][$key])) {
                $plans[$pt]['schedules'][$key] = [
                    'multiplier' => $schedule_defaults[$key]['mult'],
                    'days' => $schedule_defaults[$key]['days']
                ];
            } else {
                // Normalize existing entries
                if (!isset($plans[$pt]['schedules'][$key]['days']) || !$plans[$pt]['schedules'][$key]['days']) {
                    $plans[$pt]['schedules'][$key]['days'] = $schedule_defaults[$key]['days'];
                }
                if (!isset($plans[$pt]['schedules'][$key]['multiplier']) || (float)$plans[$pt]['schedules'][$key]['multiplier'] <= 0) {
                    $plans[$pt]['schedules'][$key]['multiplier'] = $schedule_defaults[$key]['mult'];
                }
            }
        }
    }
}

// Fetch images and features for the plans
$images_by_type = ['basic' => [], 'premium' => []];
$features_by_type = ['basic' => [], 'premium' => []];
$plan_ids = [];
foreach (['basic','premium'] as $pt) { if (isset($plans[$pt])) { $plan_ids[$pt] = (int)$plans[$pt]['plan_id']; } }
if (!empty($plan_ids)) {
    $ids_list = implode(',', array_map('intval', array_values($plan_ids)));
    if ($ids_list !== '') {
        // images
        $q1 = $db->query("SELECT plan_id, image_url FROM plan_images WHERE plan_id IN ($ids_list) ORDER BY sort_order, image_id");
        if ($q1) {
            while ($r = $q1->fetch_assoc()) {
                $pid = (int)$r['plan_id'];
                $type = array_search($pid, $plan_ids, true);
                if ($type) { $images_by_type[$type][] = $r['image_url']; }
            }
            $q1->close();
        }
        // features
        $q2 = $db->query("SELECT plan_id, feature_text FROM plan_features WHERE plan_id IN ($ids_list) ORDER BY sort_order, feature_id");
        if ($q2) {
            while ($r = $q2->fetch_assoc()) {
                $pid = (int)$r['plan_id'];
                $type = array_search($pid, $plan_ids, true);
                if ($type) { $features_by_type[$type][] = $r['feature_text']; }
            }
            $q2->close();
        }
    }
}

// Fallback to defaults if admin hasn't configured images
foreach (['basic','premium'] as $pt) {
    if (empty($images_by_type[$pt])) { $images_by_type[$pt] = $fallback_images[$pt]; }
}

// Handle plan selection
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['select_plan'])) {
    if ($has_active_subscription) {
        $error_message = "You already have an active subscription. Please cancel it before ordering a new one.";
    } else {
        $plan_type = $_POST['select_plan'];
        $_SESSION['selected_plan'] = $plan_type;
        header("Location: select_plan.php");
        exit();
    }
}
// Optional debug output for plan selection and schedules
if (isset($_GET['debug_plans']) && $_GET['debug_plans'] === '1') {
    header('Content-Type: text/plain');
    echo "Debug: Selected plans and schedules\n";
    foreach (['basic','premium'] as $pt) {
        if (isset($plans[$pt])) {
            echo strtoupper($pt) . ": plan_id=" . (int)$plans[$pt]['plan_id'] . ", base_price=" . (float)$plans[$pt]['base_price'] . "\n";
            if (!empty($plans[$pt]['schedules'])) {
                foreach ($plans[$pt]['schedules'] as $key => $s) {
                    $days_val = (isset($s['days']) && $s['days'] !== null) ? (int)$s['days'] : 'NULL';
                    echo "  - " . $key . ": multiplier=" . (float)$s['multiplier'] . ", days=" . $days_val . "\n";
                }
            } else {
                echo "  (no schedules loaded)\n";
            }
        } else {
            echo strtoupper($pt) . ": not found\n";
        }
    }
    if (!empty($plan_ids)) {
        echo "Plan IDs map: " . json_encode($plan_ids) . "\n";
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiffinly - Browse Plans</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="user_css/user_dashboard_style.css">
    <link rel="stylesheet" href="user_css/profile_style.css">
    <link rel="stylesheet" href="user_css/browse_plans_style.css">
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
                <a href="browse_plans.php" class="menu-item active">
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
                    <h1>Browse Meal Plans</h1>
                    <p>Choose the perfect plan that suits your needs and budget</p>
                </div>
            </div>

            <!-- Featured Meal Plans Section -->
            <section id="meal-plans" class="meal-plans-section pattern-bg">
                <div class="container">
                    <div class="text-center">
                        <h2 class="section-title">Our Meal Plans</h2>
                    </div>
                    <br>

                    <form method="POST" action="browse_plans.php">
                        <div class="plans-container">
                            <!-- Basic Plan -->
                            <div class="meal-card">
                                <div class="slideshow-container" id="basic-plan-slideshow">
                                    <?php foreach ($images_by_type['basic'] as $index => $image): ?>
                                        <div class="slide <?php echo $index === 0 ? 'active' : ''; ?>">
                                            <img src="<?php echo $image; ?>" alt="Basic Plan Meal <?php echo $index + 1; ?>">
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="slide-nav">
                                        <?php foreach ($images_by_type['basic'] as $index => $image): ?>
                                            <div class="slide-dot <?php echo $index === 0 ? 'active' : ''; ?>" 
                                                 data-index="<?php echo $index; ?>"></div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="title-badge-container">
                                        <h3 class="plan-title"><?php echo htmlspecialchars($plans['basic']['plan_name'] ?? 'Basic Plan'); ?></h3>
                                        <span class="plan-badge popular">Most Popular</span>
                                    </div>
                                    <p class="plan-description">
                                        <?php echo htmlspecialchars($plans['basic']['description'] ?? 'Simple meals. Perfect for students and individuals looking for daily meals at an affordable price.'); ?>
                                    </p>
                                    <div class="plan-features">
                                        <?php if (!empty($features_by_type['basic'])): ?>
                                            <?php foreach ($features_by_type['basic'] as $feat): ?>
                                                <div class="feature-row">
                                                    <span class="feature-text"><?php echo htmlspecialchars($feat); ?></span>
                                                    <i class="fas fa-check feature-check"></i>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="feature-row"><span class="feature-text">3 meals per day</span><i class="fas fa-check feature-check"></i></div>
                                            <div class="feature-row"><span class="feature-text">Rotating weekly menu (simple veg/non-veg meals)</span><i class="fas fa-check feature-check"></i></div>
                                            <div class="feature-row"><span class="feature-text">No meal customization</span><i class="fas fa-check feature-check"></i></div>
                                            <div class="feature-row"><span class="feature-text">Simple eco-packaging</span><i class="fas fa-check feature-check"></i></div>
                                        <?php endif; ?>
                                    </div>
                                   
                                   <div class="price-container">
   
        <div class="price-option"><span class="price-amount">Rs.<?php echo isset($plans['basic']['base_price']) ? $plans['basic']['base_price'] : '250'; ?></span><span class="price-period">per day</span></div>
        <div class="price-option" style="margin-top:8px;">
                <span style="font-weight:600;font-size:1.1em;">weekdays (5 days, *1.00)</span><br>
                <span style="font-weight:600;font-size:1.1em;">extended week (6 days, *1.20)</span><br>
                <span style="font-weight:600;font-size:1.1em;">full week (7 days, *1.75)</span>
        </div>
   
</div>
                                    <?php if ($has_active_subscription): ?>
                                        <button type="button" class="btn btn-primary btn-lg w-100 subscribe-btn" disabled style="opacity:0.6;cursor:not-allowed;">Choose Basic Plan</button>
                                    <?php else: ?>
                                        <button type="submit" name="select_plan" value="basic" class="btn btn-primary btn-lg w-100 subscribe-btn">Choose Basic Plan</button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Premium Plan -->
                            <div class="meal-card">
                                <div class="slideshow-container" id="premium-plan-slideshow">
                                    <?php foreach ($images_by_type['premium'] as $index => $image): ?>
                                        <div class="slide <?php echo $index === 0 ? 'active' : ''; ?>">
                                            <img src="<?php echo $image; ?>" alt="Premium Plan Meal <?php echo $index + 1; ?>">
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="slide-nav">
                                        <?php foreach ($images_by_type['premium'] as $index => $image): ?>
                                            <div class="slide-dot <?php echo $index === 0 ? 'active' : ''; ?>" 
                                                 data-index="<?php echo $index; ?>"></div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="title-badge-container">
                                        <h3 class="plan-title"><?php echo htmlspecialchars($plans['premium']['plan_name'] ?? 'Premium Plan'); ?></h3>
                                        <span class="plan-badge">Best Value</span>
                                    </div>
                                    <p class="plan-description">
                                        <?php echo htmlspecialchars($plans['premium']['description'] ?? 'Enhanced meal experience with premium ingredients and additional items for savouring taste.'); ?>
                                    </p>
                                    <div class="plan-features">
                                        <?php if (!empty($features_by_type['premium'])): ?>
                                            <?php foreach ($features_by_type['premium'] as $feat): ?>
                                                <div class="feature-row">
                                                    <span class="feature-text"><?php echo htmlspecialchars($feat); ?></span>
                                                    <i class="fas fa-check feature-check"></i>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="feature-row"><span class="feature-text">Premium Packaging</span><i class="fas fa-check feature-check"></i></div>
                                            <div class="feature-row"><span class="feature-text">Priority delivery</span><i class="fas fa-check feature-check"></i></div>
                                            <div class="feature-row"><span class="feature-text">Customizable menu</span><i class="fas fa-check feature-check"></i></div>
                                            <div class="feature-row"><span class="feature-text">Desserts available</span><i class="fas fa-check feature-check"></i></div>
                                        <?php endif; ?>
                                    </div>
                                   
                                    <div class="price-container">
            <div class="price-option"><span class="price-amount">Rs.<?php echo isset($plans['premium']['base_price']) ? $plans['premium']['base_price'] : '320'; ?></span><span class="price-period">per day</span></div>
            <div class="price-option" style="margin-top:8px;">
                <span style="font-weight:600;font-size:1.1em;">weekdays (5 days, *1.00)</span><br>
                <span style="font-weight:600;font-size:1.1em;">extended week (6 days, *1.20)</span><br>
                <span style="font-weight:600;font-size:1.1em;">full week (7 days, *1.75)</span>
            </div>
    </div>
                                        <?php if ($has_active_subscription): ?>
                                            <button type="button" class="btn btn-primary btn-lg w-100 subscribe-btn" disabled style="opacity:0.6;cursor:not-allowed;">Choose Premium Plan</button>
                                        <?php else: ?>
                                            <button type="submit" name="select_plan" value="premium" class="btn btn-primary btn-lg w-100 subscribe-btn">Choose Premium Plan</button>
                                        <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </section>

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

            // Slideshow functionality
            function initSlideshow(containerId) {
                const container = document.getElementById(containerId);
                const slides = container.querySelectorAll('.slide');
                const dots = container.querySelectorAll('.slide-dot');
                let currentSlide = 0;
                
                function showSlide(index) {
                    slides.forEach(slide => slide.classList.remove('active'));
                    dots.forEach(dot => dot.classList.remove('active'));
                    
                    slides[index].classList.add('active');
                    dots[index].classList.add('active');
                    currentSlide = index;
                }
                
                // Dot click event
                dots.forEach(dot => {
                    dot.addEventListener('click', function() {
                        showSlide(parseInt(this.dataset.index));
                    });
                });
                
                // Auto-rotate slides
                setInterval(() => {
                    currentSlide = (currentSlide + 1) % slides.length;
                    showSlide(currentSlide);
                }, 4000);
            }
            
            // Initialize slideshows
            initSlideshow('basic-plan-slideshow');
            initSlideshow('premium-plan-slideshow');
            
        });
    </script>
</body>
</html>
<?php
$db->close();
?>