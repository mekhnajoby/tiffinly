<?php
session_start();

// If "Remember Me" cookie exists, invalidate it
if (isset($_COOKIE['remember_me'])) {
    require_once 'config/db_connect.php';
    
    list($selector, $validator) = explode(':', $_COOKIE['remember_me'], 2);
    
    if ($selector) {
        $stmt = $conn->prepare("DELETE FROM auth_tokens WHERE selector = ?");
        $stmt->bind_param("s", $selector);
        $stmt->execute();
    }
    
    // Clear the cookie from the browser
    setcookie('remember_me', '', time() - 3600, '/');
}

session_destroy();
header('Location: login.php');
exit();
?>
