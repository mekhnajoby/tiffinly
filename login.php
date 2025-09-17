<?php
session_start();

// Check for "Remember Me" cookie before any other logic
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    list($selector, $validator) = explode(':', $_COOKIE['remember_me'], 2);

    if ($selector && $validator) {
        require_once 'config/db_connect.php';
        $stmt = $conn->prepare("SELECT * FROM auth_tokens WHERE selector = ? AND expires_at >= NOW()");
        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $token = $result->fetch_assoc();
            if (password_verify($validator, $token['validator'])) {
                // Token is valid, log the user in
                $userStmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
                $userStmt->bind_param("i", $token['user_id']);
                $userStmt->execute();
                $userResult = $userStmt->get_result();
                $user = $userResult->fetch_assoc();

                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['login_time'] = time();

                // Redirect to dashboard
                switch ($_SESSION['role']) {
                    case 'admin': header('Location: admin/admin_dashboard.php'); break;
                    case 'delivery': header('Location: delivery/partner_dashboard.php'); break;
                    default: header('Location: user/user_dashboard.php');
                }
                exit;
            }
        }
        // If token is invalid, clear the cookie
        setcookie('remember_me', '', time() - 3600, '/');
    }
}


// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: admin/admin_dashboard.php');
            break;
        case 'delivery':
            header('Location: delivery/partner_dashboard.php');
            break;
        default:
            header('Location: user/user_dashboard.php');
    }
    exit;
}

// DB connection
require_once 'config/db_connect.php';

// Check if the connection was successful
if (!isset($conn) || $conn->connect_error) {
    // Fallback if db_connect.php didn't establish $conn or it failed
    $conn = new mysqli("127.0.0.1", "root", "", "tiffinly");
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Disable caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$error = '';
$success = '';
$redirectTo = '';
$loginSuccess = false;
$errors = []; // Initialize errors array

//PHP Server side validations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['userRole'] ?? ''; // Don't default to 'user' anymore
    
    // Email format validation
    if (empty($email)) {
        $errors[] = "Email address is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }
    // disposable email format validation
    $disallowedDomains = ['tempmail.com', 'mailinator.com'];
    if (!empty($email)) { // Only check if email is not empty
        $emailDomain = explode('@', $email)[1] ?? '';
        if (in_array(strtolower($emailDomain), $disallowedDomains)) {
            $errors[] = "Disposable email addresses are not allowed";
        }
    }
    
    // Password validation
    if (empty($password)) {
        $errors[] = "Password is required";
    } else {
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
        if (!preg_match("/[A-Z]/", $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        if (!preg_match("/[a-z]/", $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        if (!preg_match("/[0-9]/", $password)) {
            $errors[] = "Password must contain at least one number";
        }
        if (!preg_match("/[^A-Za-z0-9]/", $password)) {
            $errors[] = "Password must contain at least one special character";
        }
    }
    // Role validation - must be one of the allowed roles
    $allowed_roles = ['user', 'admin', 'delivery'];
    if (empty($role)) {
        $errors[] = "Please select a role (User, Admin, or Delivery Partner)";
    } elseif (!in_array($role, $allowed_roles)) {
        $errors[] = "Invalid role selected. Please choose a valid role.";
        $role = ''; // Clear invalid role
    }

    // Check if there are any errors and role is valid
    if (empty($errors) && !empty($role)) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND role = ?");
        $stmt->bind_param("ss", $email, $role);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            //Check if user exist and password matches
            if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['login_time'] = time();
            
            $loginSuccess = true;

            // Handle "Remember Me"
            if (isset($_POST['remember'])) {
                // Clean up old tokens for this user
                $deleteStmt = $conn->prepare("DELETE FROM auth_tokens WHERE user_id = ?");
                $deleteStmt->bind_param("i", $user['user_id']);
                $deleteStmt->execute();

                // Create new token
                $selector = bin2hex(random_bytes(16));
                $validator = bin2hex(random_bytes(32));
                $hashedValidator = password_hash($validator, PASSWORD_BCRYPT);
                $expiry = new DateTime('+30 days');
                $expiryDate = $expiry->format('Y-m-d H:i:s');

                $insertStmt = $conn->prepare("INSERT INTO auth_tokens (user_id, selector, validator, expires_at) VALUES (?, ?, ?, ?)");
                $insertStmt->bind_param("isss", $user['user_id'], $selector, $hashedValidator, $expiryDate);
                $insertStmt->execute();
                setcookie('remember_me', $selector . ':' . $validator, $expiry->getTimestamp(), '/', '', false, true); // Set secure to true in production
            }
            $success = "✅ Login successful! Redirecting to your dashboard...";
            // Redirect based on user role
            switch ($user['role']) {
                case 'admin':
                    $redirectTo = 'admin/admin_dashboard.php';
                    break;
                case 'delivery':
                    $redirectTo = 'delivery/partner_dashboard.php';
                    break;
                default:
                    $redirectTo = 'user/user_dashboard.php';
            }
            } else {
                // Password does not match
                $error = "Incorrect password. Please try again.<br>Forgot your password? <a href='forgot_password.php'>Reset it here</a>";
            }
        
        } else {
            // User not found for the given email and role
            $error = "No account found with this email and role combination.";
        }
        $stmt->close();
    }
    if (!empty($errors)) {
        $error = '<ul class="mb-0">';
        foreach ($errors as $err) {
            $error .= '<li>' . htmlspecialchars($err) . '</li>';
        }
        $error .= '</ul>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Tiffinly</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/login.css" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-background"></div>

        <div class="container-fluid">
            <div class="row min-vh-100">
                <!-- Left Side - Branding -->
                <div class="col-lg-6 d-none d-lg-flex auth-brand-side">
                    <div class="auth-brand-content">
                        <div class="brand-header">
                            <h1><i class="fas fa-utensils me-3"></i>Tiffinly</h1>
                            <p class="brand-tagline">Authentic Home Cooked Meals</p>
                        </div>

                        <div class="brand-features">
                            <div class="feature-item">
                                <div class="feature-icon"><i class="fas fa-home"></i></div>
                                <div class="feature-content">
                                    <h4>Home-Style Cooking</h4>
                                    <p>Fresh, healthy meals prepared with traditional recipes</p>
                                </div>
                            </div>
                            <div class="feature-item">
                                <div class="feature-icon"><i class="fas fa-clock"></i></div>
                                <div class="feature-content">
                                    <h4>On-Time Delivery</h4>
                                    <p>Reliable delivery service by trusted partners</p>
                                </div>
                            </div>
                            <div class="feature-item">
                                <div class="feature-icon"><i class="fas fa-star"></i></div>
                                <div class="feature-content">
                                    <h4>Quality Assured</h4>
                                    <p>Premium ingredients and authentic Indian flavors</p>
                                </div>
                            </div>
                        </div>

                        <div class="brand-stats">
                            <div class="stat-item"><span class="stat-number">5K+</span><span class="stat-label">Happy Customers</span></div>
                            <div class="stat-item"><span class="stat-number">20+</span><span class="stat-label">Dishes Available</span></div>
                            <div class="stat-item"><span class="stat-number">99%</span><span class="stat-label">Satisfaction</span></div>
                        </div>
                    </div>
                </div>

                <!-- Right Side - Login Form -->
                <div class="col-lg-6 auth-form-side">
                    <div class="auth-form-container">
                        <div class="auth-header">
                            <h2>Welcome Back!</h2>
                            <p>Please sign in to your account</p>
                        </div>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success text-center"><?php echo $success; ?></div>
                            <script>
                                window.scrollTo({ top: 0, behavior: 'smooth' });
                                setTimeout(function() {
                                    window.location.href = "<?php echo $redirectTo; ?>";
                                }, 5000);
                            </script>
                        <?php endif; ?>
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger mb-4">
                                <strong>Error:</strong>
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                            

    <!-- Login Form  -->
    <form method="POST" class="auth-form" id="loginForm" autocomplete="off" novalidate>
    <!-- Role Selection -->
    <div class="role-selection">
        <h5>Select Your Role</h5>
        <div class="role-options">
            <input type="radio" name="userRole" id="admin" value="admin"
                <?php echo (isset($_POST['userRole']) && $_POST['userRole'] === 'admin') ? 'checked' : ''; ?>>
            <label for="admin" class="role-option">
                <div class="role-icon"><i class="fas fa-user-shield"></i></div>
                <span>Admin</span>
            </label>

            <input type="radio" name="userRole" id="customer" value="user"
                <?php echo (!isset($_POST['userRole']) || $_POST['userRole'] === 'user') ? 'checked' : ''; ?>>
            <label for="customer" class="role-option">
                <div class="role-icon"><i class="fas fa-user"></i></div>
                <span>Customer</span>
            </label>

            <input type="radio" name="userRole" id="delivery" value="delivery"
                <?php echo (isset($_POST['userRole']) && $_POST['userRole'] === 'delivery') ? 'checked' : ''; ?>>
            <label for="delivery" class="role-option">
                <div class="role-icon"><i class="fas fa-motorcycle"></i></div>
                <span>Delivery Partner</span>
            </label>
        </div>
    </div>

    <div class="form-group">
        <label for="email">Email Address</label>
        <div class="input-group">
            <span class="input-icon"><i class="fas fa-envelope"></i></span>
            <input type="email" class="form-control" id="email" name="email"
                   placeholder="Enter your email"
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
        </div>
    </div>

    <div class="form-group">
        <label for="password">Password</label>
        <div class="input-group">
            <span class="input-icon"><i class="fas fa-lock"></i></span>
            <input type="password" class="form-control" id="password" name="password"
                   placeholder="Enter your password" required minlength="6">
            <span class="password-toggle" onclick="togglePassword()">
                <i class="fas fa-eye" id="passwordToggleIcon"></i>
            </span>
        </div>
    </div>
                            
    <div class="form-options">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="remember" name="remember">
            <label class="form-check-label" for="remember">Remember me</label>
        </div>
        <a href="forgot_password.php" class="forgot-password">Forgot Password?</a>
    </div>

    <button type="submit" class="btn btn-primary btn-lg w-100 login-btn">
        <span class="btn-text">Sign In</span>
    </button>
</form>

                            <div class="auth-footer">
                            <p>Don't have an account? <a href="register.php">Sign up here</a></p>
                            <p><a href="index.php">← Back to Home</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    <?php if (!empty($success)): ?>
        window.scrollTo({ top: 0, behavior: 'smooth' });
        setTimeout(function() {
            window.location.href = "<?php echo $redirectTo; ?>";
        }, 3000);
    <?php endif; ?>
</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/login.js"></script>
</body>
</html>