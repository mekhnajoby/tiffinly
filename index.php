<?php
session_start();
// Prevent caching to stop forward/back navigation after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

function getDashboardUrl($userRole) {
    switch ($userRole) {
        case 'admin':
            return 'admin/admin_dashboard.php';
        case 'delivery':
            return 'delivery/partner_dashboard.php';
        default:
            return 'user/user_dashboard.php';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiffinly - Home Cooked Meals Delivered</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/index.css" rel="stylesheet">
</head>
<?php
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['role'] ?? '';
$userName = $_SESSION['name'] ?? '';
?>
<body <?php if ($isLoggedIn) echo 'class="logged-in"'; ?>>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top" id="mainNav">
        <div class="container">
            <a class="navbar-brand" href="#home">
                <i class="fas fa-utensils me-2"></i>
                <span class="brand-text">Tiffinly</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#meal-plans">Meal Plans</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#reviews">Reviews</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                    <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
                    
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle user-dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($userName); ?>
                            </a>
                            <ul class="dropdown-menu user-dropdown-menu">
                                <li><a class="dropdown-item user-dropdown-item" href="<?php echo getDashboardUrl($userRole); ?>">
                                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item user-dropdown-item" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link btn-login" href="login.php">Login</a></li>
                        <li class="nav-item"><a class="nav-link btn-register" href="register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="hero-overlay"></div>
        <div class="container">
            <div class="row align-items-center min-vh-100">
                <div class="col-lg-6">
                    <div class="hero-content animate-slide-up">
                        <h1 class="hero-title">
                            Authentic <span class="text-gradient">Home Cooked</span> Meals
                        </h1>
                        <p class="hero-subtitle">
                            Experience the comfort of home-cooked food with our convenient meal subscription service. 
                            Fresh, healthy, and delicious Indian meals delivered to your doorstep.<br>
                            Perfect for students and working professionals.
                        </p>
                        <div class="hero-stats">
                            
                            <div class="stat-item">
                                <span class="stat-number">20+</span>
                                <span class="stat-label">Dishes Available</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number">99%</span>
                                <span class="stat-label">Satisfaction Rate</span>
                            </div>
                                <div class="stat-item">
                                    <span class="stat-number">100%</span>
                                    <span class="stat-label">Fresh &amp; Safe</span>
                                </div>
                        </div>
                        <div class="hero-buttons">
                            <a href="#meal-plans" class="btn btn-primary btn-lg me-3">
                                <i class="fas fa-utensils me-2"></i>View Meal Plans
                            </a>
                            <?php if (!$isLoggedIn): ?>
                                <a href="register.php" class="btn btn-outline-light btn-lg">
                                    <i class="fas fa-user-plus me-2"></i>Get Started
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-image animate-float">
                        <img src="assets/meals/muttonbiriyani.jpg" alt="Chole Jeera Rice" class="img-fluid rounded-4">
                        <div class="floating-card">
                            <i class="fas fa-star text-warning"></i>
                            <span>4.9/5 Rating</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Meal Plans -->
    <section id="meal-plans" class="meal-plans-section pattern-bg">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Our Meal Plans</h2>
                <p class="section-subtitle text-center" style="margin:0 auto; max-width:500px;">
                    Choose the perfect plan that suits your needs and budget.
                </p>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-6 mb-4">
                    <div class="meal-card">
                        <div class="overflow-hidden">
                            <img src="assets/meals/idlichutney.jpg" 
                                 alt="Basic Meal Plan" class="img-fluid">
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h3 class="plan-title mb-0">Basic Plan</h3>
                                <span class="plan-badge popular">Most Popular</span>
                            </div>
                            <p class="plan-description">
                                Simple meals. Perfect for students and individuals looking for
                                daily meals at an affordable price.
                            </p>
                            <div class="plan-features">
                                <div class="feature-row">
                                        <span class="feature-text">Simple veg/non-veg meals</span>
                                        <i class="fas fa-check feature-check"></i>
                                    </div>
                                    <div class="feature-row">
                                        <span class="feature-text">No meal customization</span>
                                        <i class="fas fa-check feature-check"></i>
                                    </div>
                                    <div class="feature-row">
                                        <span class="feature-text">Simple eco-packaging</span>
                                        <i class="fas fa-check feature-check"></i>
                                    </div>
                            </div>
                            <div class="plan-price">
                                    <span class="price-amount">₹250</span>
                                    <span class="price-period">/day</span>
                            </div>
                             <button class="btn btn-primary btn-lg w-100 subscribe-btn" onclick="selectPlan('basic')">
                            Choose Basic Plan
                        </button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5 col-md-6 mb-4">
                    <div class="meal-card">
                        <div class="overflow-hidden">
                            <img src="assets/meals/curdrice.jpg" 
                                 alt="Premium Meal Plan" class="img-fluid">
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h3 class="plan-title mb-0">Premium Plan</h3>
                                <span class="plan-badge">Best Value</span>
                            </div>
                            <p class="plan-description">
                                Enhanced meal experience with premium ingredients and additional
                                items for savouring taste.
                            </p>
                            <div class="plan-features">
                                    <div class="feature-row">
                                        <span class="feature-text">Customizable menu</span>
                                        <i class="fas fa-check feature-check"></i>
                                    </div>
                                    <div class="feature-row">
                                        <span class="feature-text">Deserts available</span>
                                        <i class="fas fa-check feature-check"></i>
                                    </div>
                                    <div class="feature-row">
                                        <span class="feature-text">Premium Packaging</span>
                                        <i class="fas fa-check feature-check"></i>
                                    </div>
                            </div>
                            <div class="plan-price">
                                    <span class="price-amount">₹320</span>
                                    <span class="price-period">/day</span>
                            </div>
                            <button class="btn btn-primary btn-lg w-100 subscribe-btn" onclick="selectPlan('premium')">
                            Choose Premium Plan
                        </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about-section py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="about-content">
                        <h2 class="section-title">About Tiffinly</h2>
                        <p class="section-subtitle">
                            We connect busy Indian students and professionals with authentic, home-cooked meals. 
                            Our platform bridges the gap between traditional home cooking and modern convenience.
                        </p>
                        <div class="about-features">
                            <div class="feature-item">
                                <div class="feature-icon">
                                    <i class="fas fa-home"></i>
                                </div>
                                <div class="feature-content">
                                    <h4>Home-Style Cooking</h4>
                                    <p>Fresh, healthy meals prepared with love and traditional recipes</p>
                                </div>
                            </div>
                            <div class="feature-item">
                                <div class="feature-icon">
                                    <i class="fas fa-truck"></i>
                                </div>
                                <div class="feature-content">
                                    <h4>Reliable Delivery</h4>
                                    <p>On-time delivery by our trusted delivery partners</p>
                                </div>
                            </div>
                            <div class="feature-item">
                                <div class="feature-icon">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="feature-content">
                                    <h4>Affordable Pricing</h4>
                                    <p>Quality meals at student and professional-friendly prices</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="about-images">
                        <img src="assets/meals/cholejeerarice.jpg" alt="Appam and Curry" class="img-fluid rounded-4 main-image">
                        <img src="assets/meals/spices.jpg" alt="Masala Dosa" class="img-fluid rounded-4 floating-image">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Reviews Section -->
    <section class="reviews-section py-5" id="reviews">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">What Our Customers Say</h2>
                <p class="section-subtitle">Real experiences from real people</p>
            </div>
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="review-card">
                        <div class="review-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p class="review-text">
                            "The biryani tastes exactly like my mom's cooking! Perfect for a homesick student."
                        </p>
                        <div class="review-author">
                            <img src="assets/review/person3.jpg" alt="Priya" class="author-image">
                            <div>
                                <h5>Joel Sebastian</h5>
                                <span>Engineering Student</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mb-4">
                    <div class="review-card">
                        <div class="review-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p class="review-text">
                            "Consistent quality and on-time delivery. The premium plan is worth every rupee!"
                        </p>
                        <div class="review-author">
                            <img src="assets/review/person2.jpg" alt="Rahul" class="author-image">
                            <div>
                                <h5>Tom Ison</h5>
                                <span>Software Developer</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mb-4">
                    <div class="review-card">
                        <div class="review-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p class="review-text">
                            "Amazing variety and authentic taste. Saves me so much time and effort!"
                        </p>
                        <div class="review-author">
                            <img src="assets/review/person1.jpg" alt="Sneha" class="author-image">
                            <div>
                                <h5>Antonio Frenandez</h5>
                                <span>CA Student</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact & FAQ Section -->
    <section id="contact" class="contact-section py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-6">
                    <div class="contact-info">
                        <h2 class="section-title">Get in Touch</h2>
                        <p class="section-subtitle">Have questions? We'd love to hear from you.</p><br>
                        
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="contact-content">
                                <h4>Call Us</h4>
                                <p>+91 98765 43210</p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="contact-content">
                                <h4>Email Us</h4>
                                <p>support@tiffinly.in</p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="contact-content">
                                <h4>Visit Us</h4>
                                <p>Tiffinly Food Services Pvt. Ltd. 1st Floor, Valiya Tower,<br>
                                Infopark Road, Kakkanad, Kochi – 682030
                            </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div id="faq" class="faq-section fade-in-up" style="animation: fadeInUp 0.8s ease-out;">
                        <h2 class="section-title">Frequently Asked Questions</h2>
                        <p class="section-subtitle">Find answers to common questions about our service.</p><br>
                        
                        <div class="accordion" id="faqAccordion" style="animation: slideInRight 0.6s ease-out 0.2s both;">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingOne">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne" style="transition: all 0.3s ease; padding: 1.25rem 1.5rem; margin-bottom: 0.5rem; border-radius: 8px;">
                                        What payment methods do you accept?
                                    </button>
                                </h2>
                                <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#faqAccordion" style="transition: all 0.4s ease;">
                                    <div class="accordion-body" style="padding: 1.5rem; line-height: 1.6; animation: fadeIn 0.5s ease;">
                                        We accept all major payment methods including credit cards, debit cards, UPI, net banking, and digital wallets like PhonePe, Google Pay, and Paytm.
                                </div>
                            </div>
                            
                            <div class="accordion-item" style="margin-bottom: 0.5rem;">
                                <h2 class="accordion-header" id="headingTwo">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo" style="transition: all 0.3s ease; padding: 1.25rem 1.5rem; border-radius: 8px;">
                                        What are your delivery timings?
                                    </button>
                                </h2>
                                <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#faqAccordion" style="transition: all 0.4s ease;">
                                    <div class="accordion-body" style="padding: 1.5rem; line-height: 1.6; animation: fadeIn 0.5s ease;">
                                        We deliver fresh meals three times a day: Breakfast, Lunch, and Dinner. You can choose your preferred time slot for delivery. Our delivery partners ensure your meals reach you hot and on time.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item" style="margin-bottom: 0.5rem;">
                                <h2 class="accordion-header" id="headingThree">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree" style="transition: all 0.3s ease; padding: 1.25rem 1.5rem; border-radius: 8px;">
                                        How does the subscription work?
                                    </button>
                                </h2>
                                <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#faqAccordion" style="transition: all 0.4s ease;">
                                    <div class="accordion-body" style="padding: 1.5rem; line-height: 1.6; animation: fadeIn 0.5s ease;">
                                        Choose your preferred meal plan (Basic or Premium), select your schedule, duration and delivery preferences, and we'll handle the rest. You can cancel your subscription anytime through your dashboard. 
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

   <!-- Footer -->
    <footer class="footer-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-4">
                    <div class="footer-brand">
                        <h3><i class="fas fa-utensils me-2"></i>Tiffinly</h3>
                        <p>Connecting you with authentic home-cooked Indian meals. Fresh, healthy, and delivered with love.</p>
                       
                    </div>
                </div>
                <div class="col-lg-2">
                    <div class="footer-links">
                        <h4>Quick Links</h4>
                        <ul>
                            <li><a href="#home">Home</a></li>
                            <li><a href="#meal-plans">Meal Plans</a></li>
                            <li><a href="#about">About</a></li>
                            <li><a href="#contact">Contact</a></li>
                            <li><a href="#faq">FAQ</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-2">
                    <div class="footer-links">
                        <h4>Account</h4>
                        <ul>
                            <li><a href="login.php">Login</a></li>
                            <li><a href="register.php">Register</a></li>
                            <?php if ($isLoggedIn): ?>
                                <li><a href="<?php echo getDashboardUrl($userRole); ?>">Dashboard</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-2">
                    <div class="footer-links">
                        <h4>Support</h4>
                        <ul>
                            <li><a href="#">Help Center</a></li>
                            <li><a href="#">Privacy Policy</a></li>
                            <li><a href="#">Terms of Service</a></li>
                            <li><a href="#faq">FAQ</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-2">
                    <div class="footer-links">
                        <h4>Partners</h4>
                        <ul>
                            <li><a href="register.php">Join as a delivery partner</a></li>
                            
                        </ul>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <p>&copy; 2025 Tiffinly. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/index.js"></script>
</body>
</html>