<?php
/**
 * Authentication helper functions
 * File path: includes/auth.php
 */

/**
 * Check if student is logged in, redirect to login page if not
 * @return void
 */
function checkStudentLogin() {
    // Ensure session is started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Log authentication check
    error_log("Auth check - Session data: " . print_r($_SESSION, true));
    
    // Simplified check to avoid timing issues
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || 
        !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
        
        // Log the failure
        error_log("Authentication failed - redirecting to login");
        
        // Redirect to login page
        header("Location: ../login.php");
        exit();
    }
    
    // Authentication successful, update last activity
    $_SESSION['last_activity'] = time();
}

/**
 * Check if admin is logged in, redirect to admin login page if not
 * @return void
 */
function checkAdminLogin() {
    // Ensure session is started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is logged in as admin
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || 
        !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        
        // Redirect to admin login
        header("Location: ../admin-login.php");
        exit();
    }
    
    // Authentication successful, update last activity
    $_SESSION['last_activity'] = time();
}


