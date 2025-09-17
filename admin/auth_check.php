<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Disable browser cache to prevent navigation after login/logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check if user is logged in and is an admin
function isAdmin() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['user_type']) && 
           $_SESSION['user_type'] === 'admin';
}

// Redirect to login if not logged in or not admin
if (!isAdmin()) {
    $_SESSION['error_message'] = 'You must be logged in as an admin to access this page.';
    header('Location: ../login.php');
    exit();
}
?>
