<?php


// Include necessary files
require_once '../../includes/config.php';
require_once '../../includes/db-connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin-functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../admin-login.php");
    exit();
}

// Get admin user information
$admin_id = $_SESSION['id'];

// Check if file parameter is set
if (!isset($_GET['file'])) {
    $_SESSION['error_message'] = "No file specified";
    header("Location: maintenance.php");
    exit();
}

// Sanitize filename
$filename = filter_var($_GET['file'], FILTER_SANITIZE_STRING);

// Validate filename (only allow backup files)
if (strpos($filename, 'backup') === false || strpos($filename, '..') !== false) {
    $_SESSION['error_message'] = "Invalid backup file";
    header("Location: maintenance.php");
    exit();
}

$backup_path = '../../backups/' . $filename;

// Check if file exists
if (!file_exists($backup_path)) {
    $_SESSION['error_message'] = "Backup file not found";
    header("Location: maintenance.php");
    exit();
}

// Delete the file
if (unlink($backup_path)) {
    // Log action
    logAdminAction($admin_id, 'Deleted database backup', "Deleted backup file: $filename");
    $_SESSION['success_message'] = "Backup file deleted successfully";
} else {
    $_SESSION['error_message'] = "Failed to delete backup file";
}

// Redirect back to maintenance page
header("Location: maintenance.php");
exit();
?>