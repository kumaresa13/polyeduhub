<?php
// File path: admin/resources/delete_comment.php

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

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

// Get parameters
$comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
$resource_id = isset($_POST['resource_id']) ? intval($_POST['resource_id']) : 0;

// Validate inputs
if (!$comment_id || !$resource_id) {
    $_SESSION['error_message'] = "Invalid parameters";
    header("Location: view.php?id=$resource_id");
    exit();
}

try {
    $pdo = getDbConnection();
    
    // Get comment information for logging
    $stmt = $pdo->prepare("
        SELECT rc.comment, u.first_name, u.last_name 
        FROM resource_comments rc
        JOIN users u ON rc.user_id = u.id
        WHERE rc.id = ?
    ");
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch();
    
    if (!$comment) {
        throw new Exception("Comment not found");
    }
    
    // Delete the comment
    $stmt = $pdo->prepare("DELETE FROM resource_comments WHERE id = ?");
    $stmt->execute([$comment_id]);
    
    // Log the action
    logAdminAction(
        $admin_id,
        "Deleted comment",
        "Deleted comment by " . $comment['first_name'] . " " . $comment['last_name'] . 
        " on resource ID: $resource_id"
    );
    
    $_SESSION['success_message'] = "Comment deleted successfully";
    
} catch (Exception $e) {
    error_log("Error deleting comment: " . $e->getMessage());
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
}

// Redirect back to the resource view page
header("Location: view.php?id=$resource_id");
exit();
?>