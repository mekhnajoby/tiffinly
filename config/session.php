<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Session timeout (30 minutes)
$timeout = 30 * 60;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['LAST_ACTIVITY'] = time();

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function generateCSRFToken() {
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['role'] ?? '';
}

function getUserId() {
    return $_SESSION['user_id'] ?? 0;
}

function getUserName() {
    return $_SESSION['name'] ?? '';
}

function redirectToLogin() {
    header("Location: ../login.php");
    exit();
}

function redirectToDashboard($role) {
    switch ($role) {
        case 'admin':
            header("Location: ../admin/dashboard.php");
            break;
        case 'delivery':
            header("Location: ../delivery/dashboard.php");
            break;
        default:
            header("Location: ../user/dashboard.php");
            break;
    }
    exit();
}
?>