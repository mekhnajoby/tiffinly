<?php
// Start session
session_start();

// Verify user is logged in (optional but recommended)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Unset all session variables
// If "Remember Me" cookie exists, invalidate it
if (isset($_COOKIE['remember_me'])) {
    require_once '../config/db_connect.php';
    list($selector, $validator) = explode(':', $_COOKIE['remember_me'], 2);
    if ($selector) {
        $stmt = $conn->prepare("DELETE FROM auth_tokens WHERE selector = ?");
        $stmt->bind_param("s", $selector);
        $stmt->execute();
    }
    setcookie('remember_me', '', time() - 3600, '/');
}
$_SESSION = array();

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page outside user folder
header("Location: ../login.php?logout=success");
exit();
?>