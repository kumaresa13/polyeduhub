<?php
// Place this file in: polyeduhub/includes/config.php

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Application settings
define('APP_NAME', 'PolyEduHub');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/polyeduhub');

// Environment setting
define('ENVIRONMENT', 'development'); // Change to 'production' for live site

// Security settings
define('SESSION_LIFETIME', 86400); // 24 hours

// Ensure session is started only once
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    ini_set('session.cookie_lifetime', SESSION_LIFETIME);
    
    // Enhanced session security
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    session_start();
}

// Database settings are in db-connection.php

// File upload settings
define('UPLOAD_MAX_SIZE', 10485760); // 10MB
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip', 'rar', 'jpg', 'jpeg', 'png']);

// Define upload paths (create directories if they don't exist)
define('UPLOAD_PATH', dirname(__DIR__) . '/uploads/');
define('RESOURCE_PATH', dirname(__DIR__) . '/resources/');

// Create directories with enhanced error handling
$upload_dirs = [UPLOAD_PATH, RESOURCE_PATH, dirname(__DIR__) . '/logs'];

foreach ($upload_dirs as $dir) {
    try {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new Exception("Failed to create directory: $dir");
            }
        }
        
        // Ensure directory is writable
        if (!is_writable($dir)) {
            throw new Exception("Directory is not writable: $dir");
        }
    } catch (Exception $e) {
        // Log the error but don't halt execution
        error_log("Directory Setup Error: " . $e->getMessage());
    }
}

// Points system configuration
define('POINTS_UPLOAD', 10);
define('POINTS_DOWNLOAD', 1);
define('POINTS_COMMENT', 2);
define('POINTS_RATING', 5);
define('POINTS_ANSWER', 10);

// Error handling based on environment
if (ENVIRONMENT === 'development') {
    // Development environment
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    // Production environment
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
    
    // Log errors instead of displaying them
    ini_set('log_errors', 1);
    $log_path = dirname(__DIR__) . '/logs/error.log';
    ini_set('error_log', $log_path);
    
    // Ensure log file is writable
    if (!file_exists($log_path)) {
        touch($log_path);
        chmod($log_path, 0644);
    }
}

// Time settings
date_default_timezone_set('Asia/Kuala_Lumpur'); // Malaysia timezone

// Chat settings
define('CHAT_MESSAGE_LIMIT', 100); // Number of messages to load in chat history
define('CHAT_REFRESH_RATE', 5000); // Refresh rate in milliseconds for chat updates

// Gamification settings
define('BADGE_LEVELS', [
    'bronze' => 100,   // Points needed for bronze badge
    'silver' => 500,   // Points needed for silver badge
    'gold' => 1000,    // Points needed for gold badge
    'platinum' => 5000 // Points needed for platinum badge
]);

// Additional security headers (optional, can be implemented in .htaccess or server config)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// Prevent potential information disclosure
ini_set('expose_php', 'Off');
?>