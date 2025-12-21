<?php
/**
 * Session Validator
 * Checks if user is authenticated
 */

session_start();

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check if user has specific role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Get user info from session
function getUserInfo() {
    if (isLoggedIn()) {
        return [
            'user_id' => $_SESSION['user_id'],
            'email' => $_SESSION['email'],
            'role' => $_SESSION['role'],
            'customer_id' => $_SESSION['customer_id'] ?? null,
            'first_name' => $_SESSION['first_name'] ?? '',
            'last_name' => $_SESSION['last_name'] ?? ''
        ];
    }
    return null;
}

// Require login (redirect if not authenticated)
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/../../../index.html');
        exit;
    }
}

// Require specific role
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: ' . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/../../../pages/user/home.php');
        exit;
    }
}
?>
