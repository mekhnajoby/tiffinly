<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// DB connection
$host = "127.0.0.1";
$user = "root";
$password = "";
$database = "tiffinly";
//$port=3307;

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


// ===== EMAIL UNIQUENESS AJAX CHECK =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_email'])) {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['exists' => false, 'valid' => false]);
        exit;
    }
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    echo json_encode([
        'exists' => $stmt->num_rows > 0,
        'valid' => true
    ]);
    exit;
}

// ===== REGISTRATION =====
$errors = [];
$registrationSuccess = false;


if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['check_email'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $_POST['userRole'] ?? 'user';

    // === GENERAL VALIDATIONS ===
    if (empty($name)) {
        $errors['name'] = "Full Name is required.";
    } elseif (!preg_match("/^[a-zA-Z\s]{2,50}$/", $name)) {
        $errors['name'] = "Name must be 2-50 letters and spaces only.";
    }

    if (empty($email)) {
        $errors['email'] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Please enter a valid email.";
    }

    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (empty($phone)) {
        $errors['phone'] = "Phone number is required.";
    } elseif (!preg_match("/^[6-9]\d{9}$/", $phone)) {
        $errors['phone'] = "Please enter a valid Indian phone number.";
    }

    if (empty($password)) {
        $errors['password'] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors['password'] = "Password must be at least 8 characters.";
    } elseif (!preg_match('/[A-Z]/', $password) || 
              !preg_match('/[a-z]/', $password) || 
              !preg_match('/\d/', $password) || 
              !preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors['password'] = "Include uppercase, lowercase, number, and special character.";
    }

    if ($password !== $confirmPassword) {
        $errors['confirm_password'] = "Passwords do not match.";
    }

    if (!in_array($role, ['user', 'admin', 'delivery'])) {
        $errors['role'] = "Invalid user role selected.";
    }

    if (!isset($_POST['terms'])) {
        $errors['terms'] = "You must agree to the terms.";
    }

    //security question validation
    $securityQuestion = trim($_POST['security_question'] ?? '');
$securityAnswerRaw = trim($_POST['security_answer'] ?? '');

if (empty($securityQuestion)) {
    $errors['security_question'] = "Please select a security question.";
}
if (empty($securityAnswerRaw)) {
    $errors['security_answer'] = "Answer to the selected question is required.";
}

$securityAnswerHashed = password_hash($securityAnswerRaw, PASSWORD_BCRYPT);


    // === DELIVERY PARTNER VALIDATION ===
    $deliveryFields = [];
    if ($role === 'delivery') {
        $deliveryFields['vehicle_type'] = strtolower(trim($_POST['vehicle_type'] ?? ''));
        $deliveryFields['vehicle_number'] = trim($_POST['vehicle_number'] ?? '');
        $deliveryFields['license_number'] = trim($_POST['license_number'] ?? '');
        $deliveryFields['aadhar_number'] = trim($_POST['aadhar_number'] ?? '');
        $deliveryFields['availability'] = $_POST['availability'] ?? '';

        // Validate vehicle type
        if (empty($deliveryFields['vehicle_type'])) {
            $errors['vehicle_type'] = 'Vehicle type is required.';
        } elseif (!in_array($deliveryFields['vehicle_type'], ['bike', 'scooter', 'car'])) {
            $errors['vehicle_type'] = 'Must be bike, scooter, or car.';
        }

        // Validate vehicle number
        if (empty($deliveryFields['vehicle_number'])) {
            $errors['vehicle_number'] = 'Vehicle number is required.';
        } elseif (!preg_match("/^[A-Z]{2}[0-9]{1,2}[A-Z]{1,2}[0-9]{4}$/i", $deliveryFields['vehicle_number'])) {
            $errors['vehicle_number'] = "Format: TN01AB1234";
        }

        // Validate license number
        if (empty($deliveryFields['license_number'])) {
            $errors['license_number'] = 'License number is required.';
        } elseif (!preg_match('/^[A-Z]{2}\d{2}\d{4,11}$/i', $deliveryFields['license_number'])) {
            $errors['license_number'] = 'Format: TN22YYYYYYYY';
        }

        if (empty($deliveryFields['aadhar_number'])) {
            $errors['aadhar_number'] = 'Aadhar number is required.';
        } elseif (!preg_match("/^\d{12}$/", $deliveryFields['aadhar_number'])) {
            $errors['aadhar_number'] = 'Aadhar number must be 10 digits.';
        }

        // Validate availability
        if (empty($deliveryFields['availability'])) {
            $errors['availability'] = 'Please select availability.';
        } elseif (!in_array($deliveryFields['availability'], ['Part-time', 'Full-time'])) {
            $errors['availability'] = 'Invalid availability selected.';
        }

        // File upload validation
        if (empty($_FILES['license_file']['name'])) {
            $errors['license_file'] = 'License file is required.';
        } elseif ($_FILES['license_file']['error'] !== UPLOAD_ERR_OK) {
            $errors['license_file'] = 'Error uploading file.';
        } else {
            $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
            $fileType = $_FILES['license_file']['type'];
            if (!in_array($fileType, $allowedTypes)) {
                $errors['license_file'] = 'Only PDF, JPG, or PNG files allowed.';
            }
        }
    }

    if (empty($errors)) {
        // First check if email exists
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $errors['email'] = "Email already registered.";
            $check->close();
            // Return errors immediately if email exists
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'errors' => $errors]);
                exit;
            }
        } else {
            $check->close();
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, role, security_question, security_answer)
                        VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssssss", $name, $email, $hashedPassword, $phone, $role, $securityQuestion, $securityAnswerHashed);


           if ($stmt->execute()) {
    $userId = $stmt->insert_id;
    
    // Insert delivery details
    if ($role === 'delivery') {
        // Handle file upload
        $targetDir = "assets/delivery/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $filename = uniqid() . "_" . basename($_FILES['license_file']['name']);
        $targetPath = $targetDir . $filename;
        
        if (move_uploaded_file($_FILES['license_file']['tmp_name'], $targetPath)) {
            $deliveryFields['license_file'] = $targetPath;
            
            $dstmt = $conn->prepare("INSERT INTO delivery_partner_details 
                (partner_id, vehicle_type, vehicle_number, license_number, license_file, aadhar_number, availability)
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $dstmt->bind_param(
                "issssss",
                $userId,
                $deliveryFields['vehicle_type'],
                $deliveryFields['vehicle_number'],
                $deliveryFields['license_number'],
                $deliveryFields['license_file'],
                $deliveryFields['aadhar_number'],
                $deliveryFields['availability']
            );
            $dstmt->execute();
            $dstmt->close();
        }
    }

    // IMPORTANT: Do not set any session variables here
    // Registration should not automatically log in the user
    
   // IMPORTANT: Do not set any session variables here
// Registration should not automatically log in the user
    
$registrationSuccess = true;

// Ensure no session data is set
if (isset($_SESSION['user_id']) || isset($_SESSION['role'])) {
    session_unset(); // Remove all session variables
    session_destroy(); // Destroy the session
}
    
// Handle AJAX vs regular form submission
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => 'Registration successful! You will be redirected to login in 4 seconds...',
        'redirect' => 'login.php'  // Always redirect to login.php regardless of role
    ]);
    exit();
} else {
    // For non-AJAX submissions - always redirect to login
    header("Location: login.php?success=1");
    exit();
}

} else {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Registration failed. Please try again.'
        ]);
        exit();
    }
    $errors['general'] = 'Registration failed. Please try again.';
}


        }}
}
// Show success message if this is a redirect from successful registration
if (isset($_GET['success']) && $_GET['success'] == 1) {
    // Clear all output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
?>
<!DOCTYPE html>
    <html>
    <head>
        <title>Registration Success - Tiffinly</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <link href="css/register.css" rel="stylesheet">
    </head>
    <body>
        <div class="auth-container">
            <div class="container mt-5">
                <div class="alert alert-success text-center">
                    <i class="fas fa-check-circle me-2"></i>
                    Registration successful! You will be redirected to login in 4 seconds...
                    <div class="spinner-border spinner-border-sm ms-2" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
        <script>
            setTimeout(function() {
                window.location.href = "login.php";
            }, 4000);
        </script>
    </body>
    </html>
    <?php
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Tiffinly</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/register.css" rel="stylesheet">
    <style>
        .error-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #dc3545;
            display: none;
        }
        .is-invalid {
            border-color: #dc3545;
            padding-right: 2.25rem;
            background-image: none;
        }
        .invalid-feedback {
            color: #dc3545;
            font-size: 0.875em;
            margin-top: 0.25rem;
        }
        .form-group {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .input-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .form-control {
            padding-left: 40px;
        }
        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
    </style>
</head>
<body>
<div class="auth-container">
    <div class="auth-background"></div>
    <div class="container-fluid">
        <div class="row min-vh-100">
            <!-- Left branding section -->
            <div class="col-lg-6 d-none d-lg-flex auth-brand-side">
                <div class="auth-brand-content">
                    <h1><i class="fas fa-utensils me-3"></i>Tiffinly</h1>
                    <p class="brand-tagline">Join the Food Revolution</p>
                    <ul class="benefits-list">
                        <li><i class="fas fa-check-circle"></i> Authentic home-cooked Indian meals</li>
                        <li><i class="fas fa-check-circle"></i> Flexible meal plans for your lifestyle</li>
                        <li><i class="fas fa-check-circle"></i> Fresh ingredients, traditional recipes</li>
                        <li><i class="fas fa-check-circle"></i> Affordable pricing for students & professionals</li>
                        <li><i class="fas fa-check-circle"></i> Reliable delivery across the city</li>
                        <li><i class="fas fa-check-circle"></i> 24/7 customer support</li>
                    </ul>
                    <blockquote>
                        "Tiffinly brings the taste of home to my busy student life. Best decision ever!"
                    </blockquote>
                    <cite>- Priya S., Engineering Student</cite>
                </div>
            </div>

            <!-- Right form section -->
            <div class="col-lg-6 auth-form-side">
                <div class="auth-form-container">
                    

                    <div class="text-center mb-4">
                        <h2>Create Your Account</h2>
                        <p class="text-muted">Join as a customer or delivery partner to get started</p>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="auth-form" id="registrationForm" autocomplete="off">
                        <!-- Role Selection -->
                        <div class="role-selection mb-3">
                            <h5>I want to join as</h5>
                            <div class="role-options">
                                <input type="radio" name="userRole" id="customer" value="user"
                                    <?php echo (!isset($_POST['userRole']) || $_POST['userRole'] === 'user') ? 'checked' : ''; ?>>
                                <label for="customer" class="role-option">
                                    <div class="role-icon"><i class="fas fa-user"></i></div>
                                    <div class="role-details">
                                        <span class="role-title">Customer</span>
                                        <small>Order delicious meals</small>
                                    </div>
                                </label>

                                <input type="radio" name="userRole" id="delivery" value="delivery"
                                    <?php echo (isset($_POST['userRole']) && $_POST['userRole'] === 'delivery') ? 'checked' : ''; ?>>
                                <label for="delivery" class="role-option">
                                    <div class="role-icon"><i class="fas fa-motorcycle"></i></div>
                                    <div class="role-details">
                                        <span class="role-title">Delivery Partner</span>
                                        <small>Deliver happiness</small>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Full Name -->
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" name="name" id="name"
                                    value=""
                                    placeholder="Enter your full name" maxlength="50">
                                <i class="fas fa-exclamation-circle error-icon" id="name-error-icon"></i>
                            </div>
                            <div class="invalid-feedback" id="name-error"></div>
                        </div>

                        <!-- Email -->
                        <div class="form-group">
                            <label for="email">Email</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" name="email" id="email"
                                    value=""
                                    placeholder="Enter your email">
                                <i class="fas fa-exclamation-circle error-icon" id="email-error-icon"></i>
                            </div>
                            <div class="invalid-feedback" id="email-error"></div>
                        </div>

                        <!-- Phone -->
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control" name="phone" id="phone"
                                    value=""
                                    placeholder="Enter your phone number">
                                <i class="fas fa-exclamation-circle error-icon" id="phone-error-icon"></i>
                            </div>
                            <div class="invalid-feedback" id="phone-error"></div>
                        </div>

                        <!-- Password -->
                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" name="password" id="password"
                                    placeholder="Create a strong password">
                                <i class="fas fa-exclamation-circle error-icon" id="password-error-icon"></i>
                            </div>
                            <div class="invalid-feedback" id="password-error"></div>
                            <small class="form-text text-muted">Must be atleast 8 characters long with uppercase, lowercase, number, and special char</small>
                        </div>

                        <!-- Confirm Password -->
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" name="confirm_password" id="confirm_password"
                                    placeholder="Re-enter your password">
                                <i class="fas fa-exclamation-circle error-icon" id="confirm_password-error-icon"></i>
                            </div>
                            <div class="invalid-feedback" id="confirm_password-error"></div>
                        </div>

    <!--security question and answer-->
<div class="form-group">
    <label for="security_question">Choose a Security Question</label>
    <select name="security_question" id="security_question" class="form-control" required>
        <option value="">-- Select a question --</option>
        <option value="What is your first pet's name?">What is your first pet's name?</option>
        <option value="What is your favorite childhood food?">What is your favorite childhood food?</option>
    </select>
</div>

<div class="form-group">
    <label for="security_answer">Your Answer</label>
    <input type="text" class="form-control" name="security_answer" id="security_answer"
           placeholder="Enter your answer" required>
</div>


                        <!-- Delivery Fields (Hidden initially) -->
                        <div class="delivery-fields d-none" id="deliveryFields">
                            <div class="form-group">
                                <label for="vehicle_type">Vehicle Type</label>
                                <select name="vehicle_type" id="vehicle_type" class="form-control">
                                    <option value="">-- Select Vehicle Type --</option>
                                    <option value="bike" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] === 'bike') ? 'selected' : ''; ?>>Bike</option>
                                    <option value="scooter" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] === 'scooter') ? 'selected' : ''; ?>>Scooter</option>
                                    <option value="car" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] === 'car') ? 'selected' : ''; ?>>Car</option>
                                </select>
                                <i class="fas fa-exclamation-circle error-icon" id="vehicle_type-error-icon"></i>
                                <div class="invalid-feedback" id="vehicle_type-error"></div>
                            </div>

                            <div class="form-group">
                                <label for="vehicle_number">Vehicle Number</label>
                                <input type="text" class="form-control" name="vehicle_number" id="vehicle_number"
                                    value=""
                                    placeholder="e.g. TN01AB1234">
                                <i class="fas fa-exclamation-circle error-icon" id="vehicle_number-error-icon"></i>
                                <div class="invalid-feedback" id="vehicle_number-error"></div>
                            </div>

                            <div class="form-group">
                                <label for="license_number">License Number</label>
                                <input type="text" class="form-control" name="license_number" id="license_number"
                                    value=""
                                    placeholder="Enter your driving license number">
                                <i class="fas fa-exclamation-circle error-icon" id="license_number-error-icon"></i>
                                <div class="invalid-feedback" id="license_number-error"></div>
                            </div>

                            <div class="form-group">
                                <label for="license_file">Upload License (PDF/JPG/PNG)</label>
                                <input type="file" class="form-control" name="license_file" id="license_file"
                                    accept=".pdf,.jpg,.jpeg,.png">
                                <i class="fas fa-exclamation-circle error-icon" id="license_file-error-icon"></i>
                                <div class="invalid-feedback" id="license_file-error"></div>
                            </div>

                            <div class="form-group">
                                <label for="aadhar_number">Aadhar Number</label>
                                <input type="text" class="form-control" name="aadhar_number" id="aadhar_number"
                                    value=""
                                    placeholder="Enter your Aadhar number">
                            </div>

                            <div class="form-group">
                                <label for="availability">Availability</label>
                                <select class="form-control" name="availability" id="availability">
                                    <option value="">Select availability</option>
                                    <option value="Part-time" <?php echo (isset($_POST['availability']) && $_POST['availability'] === 'Part-time') ? 'selected' : ''; ?>>Part Time</option>
                                    <option value="Full-time" <?php echo (isset($_POST['availability']) && $_POST['availability'] === 'Full-time') ? 'selected' : ''; ?>>Full Time</option>
                                </select>
                                <i class="fas fa-exclamation-circle error-icon" id="availability-error-icon"></i>
                                <div class="invalid-feedback" id="availability-error"></div>
                            </div>
                        </div>

                        <!-- Terms Checkbox -->
                        <div class="form-check my-3">
                            <input type="checkbox" class="form-check-input" id="terms" name="terms" <?php echo isset($_POST['terms']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                            </label>
                            <div class="invalid-feedback" id="terms-error"></div>
                        </div>

                        <!-- Submit -->
                        <button type="submit" class="btn btn-primary w-100 btn-lg register-btn">
                            <i class="fas fa-user-plus me-2"></i> Create My Account
                        </button>
                    </form>

                    <div class="auth-footer mt-4 text-center">
                        <p>Already have an account? <a href="login.php">Login</a></p>
                        <p><a href="index.php">← Back to Home</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JS Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Initialize tooltips
$(function () {
    $('[data-bs-toggle="tooltip"]').tooltip();
});

// Add event listener for the security question select to ensure it's not empty
document.getElementById('security_question').addEventListener('change', function() {
    if (this.value === "") {
        this.setCustomValidity("Please select a security question.");
    } else {
        this.setCustomValidity("");
    }
});
</script>
<script src="js/register.js"></script>
</body>
</html>