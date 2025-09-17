<?php
require_once 'session.php';

function requireAuth($allowedRoles = []) {
    if (!isLoggedIn()) {
        redirectToLogin();
    }
    
    if (!empty($allowedRoles) && !in_array(getUserRole(), $allowedRoles)) {
        header("Location: ../index.php");
        exit();
    }
}

function requireRole($role) {
    requireAuth([$role]);
}

function requireUser() {
    requireRole('user');
}

function requireAdmin() {
    requireRole('admin');
}

function requireDelivery() {
    requireRole('delivery');
}
?>