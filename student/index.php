<?php
// File: student/index.php

// Start session
session_start();

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || 
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    
    // Log the access attempt
    error_log("Unauthorized access attempt to student/index.php");
    
    // Redirect to login page
    header("Location: ../login.php");
    exit();
}

// If authentication passes, redirect to dashboard
header("Location: dashboard.php");
exit();
?>