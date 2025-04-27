<?php
// File path: admin/resources/download.php

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

// Get resource ID
$resource_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$resource_id) {
    $_SESSION['error_message'] = "Invalid resource ID";
    header("Location: index.php");
    exit();
}

try {
    $pdo = getDbConnection();
    
    // Get resource information
    $stmt = $pdo->prepare("SELECT file_path, file_type, title FROM resources WHERE id = ?");
    $stmt->execute([$resource_id]);
    $resource = $stmt->fetch();
    
    if (!$resource) {
        $_SESSION['error_message'] = "Resource not found";
        header("Location: index.php");
        exit();
    }
    
    // Get the file path
    $file_path = RESOURCE_PATH . $resource['file_path'];
    
    // Check if file exists
    if (!file_exists($file_path)) {
        $_SESSION['error_message'] = "File not found on server";
        header("Location: view.php?id=" . $resource_id);
        exit();
    }
    
    // Log the download by admin
    logAdminAction(
        $_SESSION['id'],
        "Downloaded resource",
        "Resource: " . $resource['title'] . " (ID: $resource_id)"
    );
    
    // Set headers for download
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($resource['file_path']) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    
    // Clear output buffer
    ob_clean();
    flush();
    
    // Output file
    readfile($file_path);
    exit;
} catch (PDOException $e) {
    error_log("Error downloading resource: " . $e->getMessage());
    $_SESSION['error_message'] = "Error downloading file: " . $e->getMessage();
    header("Location: view.php?id=" . $resource_id);
    exit();
}
?>