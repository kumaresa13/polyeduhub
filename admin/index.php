<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Log any authentication attempts
error_log("[Admin Index] Authentication attempt at " . date('Y-m-d H:i:s'));

// Check admin login
try {
    checkAdminLogin();
    
    // If authentication passes, redirect to dashboard
    header("Location: dashboard.php");
    exit();
} catch (Exception $e) {
    // Log any unexpected errors
    error_log("[Admin Index] Unexpected error: " . $e->getMessage());
    
    // Redirect to admin login page
    header("Location: ../admin-login.php");
    exit();
}
?>