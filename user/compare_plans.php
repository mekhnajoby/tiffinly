<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$db = new mysqli('localhost', 'root', '', 'tiffinly');
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

$user_id = $_SESSION['user_id'];
$user_query = $db->prepare("SELECT name, email, phone FROM users WHERE user_id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user = $user_result->fetch_assoc();
// Build menus from DB instead of static arrays
$basicMenu = [];
$premiumMenu = [];

// Resolve plan IDs for basic and premium (prefer active, fallback to any)
$planIds = ['basic' => null, 'premium' => null];
foreach (['basic','premium'] as $ptype) {
    // Prefer active plans and the one with most linked plan_meals
    $sql = "SELECT mp.plan_id
            FROM meal_plans mp
            LEFT JOIN plan_meals pm ON pm.plan_id = mp.plan_id
            WHERE LOWER(mp.plan_type)=?
            GROUP BY mp.plan_id, mp.is_active
            ORDER BY mp.is_active DESC, COUNT(pm.plan_meal_id) DESC, mp.plan_id ASC
            LIMIT 1";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $ptype);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $planIds[$ptype] = (int)$row['plan_id'];
        }
        $stmt->close();
    }
}

// Helper to fetch menu for a plan_id
function fetchPlanMenu($db, $planId, $planType) {
    $menu = [];
    if (!$planId) return $menu;
    $sql = "SELECT pm.day_of_week, pm.meal_type, m.meal_name,
                    mc.option_type AS diet_type
            FROM plan_meals pm
            JOIN meals m ON pm.meal_id = m.meal_id
            LEFT JOIN meal_categories mc ON m.category_id = mc.category_id
            WHERE pm.plan_id = ? AND m.is_active = 1
            ORDER BY pm.day_of_week, pm.meal_type, m.meal_name";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $planId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $day = strtoupper($r['day_of_week']);
            // Normalize to match rendering keys
            $slot = ucfirst(strtolower($r['meal_type'])); // 'Breakfast' | 'Lunch' | 'Dinner'
            $diet = $r['diet_type'];
            $diet = $diet === null ? 'veg' : $diet;
            $menu[$day][$slot][] = [
                'name' => $r['meal_name'],
                'diet_type' => strtolower(trim($diet)) // veg | non-veg
            ];
        }
        $stmt->close();
    }
    return $menu;
}

$basicMenu = fetchPlanMenu($db, $planIds['basic'], 'basic');
$premiumMenu = fetchPlanMenu($db, $planIds['premium'], 'premium');
// Popular meals data from DB
$popular_meals = [];
$sqlPop = "SELECT pm.image_url, COALESCE(pm.description,'') AS description, pm.plan_type, m.meal_name
           FROM popular_meals pm
           JOIN meals m ON m.meal_id = pm.meal_id
           WHERE pm.is_active = 1
           ORDER BY pm.sort_order, pm.id";
$stmtPop = $db->prepare($sqlPop);
if ($stmtPop) {
    $stmtPop->execute();
    $resPop = $stmtPop->get_result();
    while ($r = $resPop->fetch_assoc()) {
        $popular_meals[] = [
            'image' => $r['image_url'],
            'name' => $r['meal_name'],
            'description' => $r['description'],
            'type' => strtolower($r['plan_type'])
        ];
    }
    $stmtPop->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiffinly - Compare Plans</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="user_css/user_dashboard_style.css">
    <link rel="stylesheet" href="user_css/profile_style.css">
    <link rel="stylesheet" href="user_css/compare_plans_style.css">
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
                <a href="compare_plans.php" class="menu-item active">
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
                    <h1>Compare Meal Plans</h1>
                    <p>View and compare the weekly menus for our Basic and Premium plans</p>
                </div>
            </div>

            <!-- Popular Meals Section -->
            <section class="popular-meals">
                <div class="container">
                    <div class="text-center">
                        <h2 class="section-title animate__animated animate__fadeIn">Popular Meals</h2>
                    </div>
                    <div style="width:100%; text-align:left; margin-top:-10px; margin-bottom:20px;">
                        <p class="section-subtitle animate__animated animate__fadeIn animate__delay-1s" style="text-align:left; margin:0;">
                          &nbsp; &nbsp;   Customer favorites and chef's special recommendations
                        </p>
                    </div>
                    
                    <div class="meals-grid">
                        <?php foreach ($popular_meals as $index => $meal): ?>
                        <div class="meal-card animate__animated animate__fadeInUp" style="animation-delay: <?php echo $index * 0.2; ?>s">
                            <img src="<?php echo $meal['image']; ?>" alt="<?php echo $meal['name']; ?>" class="meal-img">
                            <div class="meal-info">
                                <h3 class="meal-name"><?php echo $meal['name']; ?></h3>
                                <p class="meal-description"><?php echo $meal['description']; ?></p>
                                <span class="meal-type type-<?php echo $meal['type']; ?>">
                                    <?php echo ucfirst($meal['type']); ?> Plan
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <!-- Compare Plans Section -->
            <section id="compare-plans" class="compare-plans-section pattern-bg">
                <div class="container">
                    <div class="text-center">
                        <h2 class="section-title animate__animated animate__bounceIn">Plan Comparison</h2>
                    </div>
                    <div style="width:100%; text-align:left; margin-top:-10px; margin-bottom:10px;">
                        <p class="section-subtitle animate__animated animate__fadeIn animate__delay-1s" style="text-align:left; margin:0;">
                           &nbsp; &nbsp; See what each plan offers and choose what works best for you
                        </p>
                    </div>
<br>
                    <div class="plans-comparison">
                      <!-- Basic Plan Section -->
<div class="plan-container slide-in-left">
    <div class="plan-header basic animate__animated animate__pulse animate__delay-2s">
        <h3 class="plan-name" style="color:#fff;">Basic Plan</h3>
        <div class="price-display">
                <div class="price-option">Weekdays (5 days)</div>
                <div class="price-option">Extended Week (6 days)</div>
                <div class="price-option">Full Week (7 days)</div>
        </div>
    </div>
    <div class="plan-content">
        <table class="menu-table">
            <thead>
                <tr>
                    <th>DAY</th>
                    <th>BREAKFAST OPTIONS</th>
                    <th>LUNCH OPTIONS</th>
                    <th>DINNER OPTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Define day order
                $daysOrder = ['MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY','SUNDAY'];
                if (empty($basicMenu)) {
                    echo '<tr><td colspan="4">Menu not configured yet.</td></tr>';
                } else {
                    foreach ($daysOrder as $day) {
                        $meals = $basicMenu[$day] ?? [];
                        echo '<tr>';
                        echo '<td><strong>'.htmlspecialchars($day).'</strong></td>';
                        // Breakfast
                        echo '<td><div class="meal-options-container">';
                        if (!empty($meals['Breakfast'])) {
                            foreach ($meals['Breakfast'] as $opt) {
                                $isVeg = (stripos($opt['diet_type'], 'veg') === 0); // veg, vegetarian, vegan
                                $cls = $isVeg ? 'veg-option' : 'nonveg-option';
                                echo '<div class="meal-option '.$cls.'">'.htmlspecialchars($opt['name']).'</div>';
                            }
                        }
                        echo '</div></td>';
                        // Lunch
                        echo '<td><div class="meal-options-container">';
                        if (!empty($meals['Lunch'])) {
                            foreach ($meals['Lunch'] as $opt) {
                                $isVeg = (stripos($opt['diet_type'], 'veg') === 0);
                                $cls = $isVeg ? 'veg-option' : 'nonveg-option';
                                echo '<div class="meal-option '.$cls.'">'.htmlspecialchars($opt['name']).'</div>';
                            }
                        }
                        echo '</div></td>';
                        // Dinner
                        echo '<td><div class="meal-options-container">';
                        if (!empty($meals['Dinner'])) {
                            foreach ($meals['Dinner'] as $opt) {
                                $isVeg = (stripos($opt['diet_type'], 'veg') === 0);
                                $cls = $isVeg ? 'veg-option' : 'nonveg-option';
                                echo '<div class="meal-option '.$cls.'">'.htmlspecialchars($opt['name']).'</div>';
                            }
                        }
                        echo '</div></td>';
                        echo '</tr>';
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
<!-- Premium Plan Section -->
<div class="plan-container">
    <div class="plan-header premium animate__animated animate__pulse animate__delay-2s">
        <h3 class="plan-name" style="color:#fff;">Premium Plan</h3>
        <div class="price-display">
                <div class="price-option">Weekdays (5 days)</div>
                <div class="price-option">Extended Week (6 days)</div>
                <div class="price-option">Full Week (7 days)</div>
                    
                </div>
        </div>
    </div>
    <div class="plan-content">
        <table class="menu-table premium-menu">
            <thead>
                <tr>
                    <th>DAY</th>
                    <th>BREAKFAST OPTIONS</th>
                    <th>LUNCH OPTIONS</th>
                    <th>DINNER + SWEET OPTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (empty($premiumMenu)) {
                    echo '<tr><td colspan="4">Menu not configured yet.</td></tr>';
                } else {
                    foreach ($daysOrder as $day) {
                        $meals = $premiumMenu[$day] ?? [];
                        echo '<tr>';
                        echo '<td><strong>'.htmlspecialchars($day).'</strong></td>';
                        // Breakfast
                        echo '<td><div class="meal-options-container">';
                        if (!empty($meals['Breakfast'])) {
                            foreach ($meals['Breakfast'] as $opt) {
                                $isVeg = (stripos($opt['diet_type'], 'veg') === 0);
                                $cls = $isVeg ? 'veg-option' : 'nonveg-option';
                                echo '<div class="meal-option premium-option '.$cls.'">'.htmlspecialchars($opt['name']).'</div>';
                            }
                        }
                        echo '</div></td>';
                        // Lunch
                        echo '<td><div class="meal-options-container">';
                        if (!empty($meals['Lunch'])) {
                            foreach ($meals['Lunch'] as $opt) {
                                $isVeg = (stripos($opt['diet_type'], 'veg') === 0);
                                $cls = $isVeg ? 'veg-option' : 'nonveg-option';
                                echo '<div class="meal-option premium-option '.$cls.'">'.htmlspecialchars($opt['name']).'</div>';
                            }
                        }
                        echo '</div></td>';
                        // Dinner
                        echo '<td><div class="meal-options-container">';
                        if (!empty($meals['Dinner'])) {
                            foreach ($meals['Dinner'] as $opt) {
                                $isVeg = (stripos($opt['diet_type'], 'veg') === 0);
                                $cls = $isVeg ? 'veg-option' : 'nonveg-option';
                                echo '<div class="meal-option premium-option '.$cls.'">'.htmlspecialchars($opt['name']).'</div>';
                            }
                        }
                        echo '</div></td>';
                        echo '</tr>';
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

                     <div class="text-center">
                        <?php
// Active subscription check for Compare Plans
$has_active_subscription = false;
if (isset($user_id)) {
    $conn_compare = new mysqli('localhost', 'root', '', 'tiffinly');
    $active_check_sql = "SELECT subscription_id FROM subscriptions WHERE user_id = ? AND status = 'active' LIMIT 1";
    $active_check_stmt = $conn_compare->prepare($active_check_sql);
    $active_check_stmt->bind_param("i", $user_id);
    $active_check_stmt->execute();
    $active_check_stmt->store_result();
    if ($active_check_stmt->num_rows > 0) {
        $has_active_subscription = true;
    }
    $active_check_stmt->close();
    $conn_compare->close();
}
?>
<?php if ($has_active_subscription): ?>
    <div class="alert" style="background:#ffe5e5;color:#c0392b;padding:12px 20px;border-radius:6px;margin-bottom:18px;">
        <i class="fas fa-exclamation-triangle"></i> You already have an active subscription. Please cancel it before ordering a new one.
    </div>
<?php else: ?>
<a href="select_plan.php" class="back-btn">
     Select Plan &nbsp;<i class="fas fa-arrow-right"></i>
</a>
<?php endif; ?>
                        <?php if ($has_active_subscription): ?>
    <a href="#" id="customize-meals-nav" class="back-btn" style="pointer-events:none;opacity:0.6;cursor:not-allowed;">Customize Meals &nbsp;<i class="fas fa-arrow-right"></i></a>
<?php else: ?>
    <a href="customize_meals.php" id="customize-meals-nav" class="back-btn">Customize Meals &nbsp;<i class="fas fa-arrow-right"></i></a>
<?php endif; ?>
                    </div>
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
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
        });
        
        // Animation triggers
        $(document).ready(function() {
            // Animate elements when they come into view
            $(window).scroll(function() {
                $('.animate-on-scroll').each(function() {
                    var position = $(this).offset().top;
                    var scroll = $(window).scrollTop();
                    var windowHeight = $(window).height();
                    
                    if (scroll + windowHeight > position + 100) {
                        $(this).addClass('animate__animated animate__fadeInUp');
                    }
                });
            });
            
            // Add hover animations
            $('.meal-card').hover(
                function() {
                    $(this).addClass('animate__animated animate__pulse');
                },
                function() {
                    $(this).removeClass('animate__animated animate__pulse');
                }
            );
        });
        // Price slideshow functionality
function initPriceSlideshows() {
    const slideshows = document.querySelectorAll('.price-slideshow');
    
    slideshows.forEach(slideshow => {
        const slides = slideshow.querySelectorAll('.price-slide');
        let currentSlide = 0;
        
        function showSlide(index) {
            slides.forEach((slide, i) => {
                slide.classList.toggle('active', i === index);
            });
        }
        
        function nextSlide() {
            currentSlide = (currentSlide + 1) % slides.length;
            showSlide(currentSlide);
        }
        
        // Start the slideshow
        showSlide(0);
        setInterval(nextSlide, 3000); // Change every 3 seconds
    });
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', initPriceSlideshows);
    </script>
</body>
</html>
<?php
$db->close();
?>