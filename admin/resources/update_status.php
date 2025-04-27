<?php


// Include necessary files
require_once '../../includes/config.php';
require_once '../../includes/db-connection.php';
require_once '../../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../admin-login.php");
    exit();
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: approve.php");
    exit();
}

// Get POST data
$resource_id = isset($_POST['resource_id']) ? intval($_POST['resource_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Validate inputs
if (!$resource_id || !in_array($action, ['approve', 'reject'])) {
    $_SESSION['error_message'] = "Invalid parameters";
    header("Location: approve.php");
    exit();
}

try {
    $pdo = getDbConnection();
    
    // Get resource information first to make sure it exists
    $stmt = $pdo->prepare("SELECT title FROM resources WHERE id = ?");
    $stmt->execute([$resource_id]);
    $resource = $stmt->fetch();
    
    if (!$resource) {
        throw new Exception("Resource not found");
    }
    
    if ($action === 'approve') {
        // Update resource status to approved
        $stmt = $pdo->prepare("UPDATE resources SET status = 'approved' WHERE id = ?");
        $result = $stmt->execute([$resource_id]);
        
        if ($stmt->rowCount() === 0) {
            // Log detailed info for debugging
            error_log("Update statement executed but no rows affected. Resource ID: $resource_id");
            throw new Exception("No changes made. Resource may already be approved.");
        }
        
        $_SESSION['success_message'] = "Resource has been approved successfully";
    } else {
        // Reject resource
        $stmt = $pdo->prepare("UPDATE resources SET status = 'rejected' WHERE id = ?");
        $result = $stmt->execute([$resource_id]);
        
        if ($stmt->rowCount() === 0) {
            error_log("Update statement executed but no rows affected. Resource ID: $resource_id");
            throw new Exception("No changes made. Resource may already be rejected.");
        }
        
        $_SESSION['success_message'] = "Resource has been rejected";
    }
} catch (Exception $e) {
    error_log("Error updating resource status: " . $e->getMessage());
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
}

// Redirect back to the resource list
if (isset($_POST['from_view_page']) && $_POST['from_view_page'] === '1') {
    header("Location: view.php?id=" . $resource_id);
} else {
    header("Location: approve.php");
}
exit();
?>