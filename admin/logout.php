<?php
/**
 * Admin Logout
 * 
 * Handles admin logout and session destruction
 */
require_once '../includes/init.php';

// Log the logout activity
if (isset($_SESSION['user_id'])) {
    logActivity('logout', 'user', $_SESSION['user_id'], [
        'username' => $_SESSION['username'] ?? 'unknown',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
}

// Use the logout function
logoutUser();

// Redirect to login page
header('Location: login.php');
exit;
