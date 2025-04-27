// File path: admin/index.php

<?php
// Start session
session_start();

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if admin is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || 
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    
    // Log the access attempt
    error_log("Unauthorized access attempt to admin/index.php");
    
    // Redirect to admin login page
    header("Location: ../admin-login.php");
    exit();
}

// If authentication passes, redirect to dashboard
header("Location: dashboard.php");
exit();
?>