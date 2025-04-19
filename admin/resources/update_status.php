// Path: admin/resources/update_status.php
// This file processes the approval/rejection of resources

<?php
// Make sure this file has the correct path to your includes
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
    header("Location: approve.php");
    exit();
}

// Get POST data
$resource_id = isset($_POST['resource_id']) ? intval($_POST['resource_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';
$feedback = isset($_POST['feedback']) ? trim($_POST['feedback']) : '';
$send_notification = isset($_POST['send_notification']) ? true : false;

// Validate inputs
if (!$resource_id || !in_array($action, ['approve', 'reject'])) {
    $_SESSION['error_message'] = "Invalid parameters";
    header("Location: approve.php");
    exit();
}

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();
    
    // Get resource information
    $stmt = $pdo->prepare("
        SELECT r.title, r.user_id, u.first_name, u.email 
        FROM resources r
        JOIN users u ON r.user_id = u.id
        WHERE r.id = ?
    ");
    $stmt->execute([$resource_id]);
    $resource = $stmt->fetch();
    
    if (!$resource) {
        throw new Exception("Resource not found");
    }
    
    if ($action === 'approve') {
        // Update resource status to approved
        $stmt = $pdo->prepare("UPDATE resources SET status = 'approved', updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$resource_id]);
        
        if (!$result) {
            throw new Exception("Failed to update resource status");
        }
        
        // Log the action
        logAdminAction(
            $admin_id,
            "Resource approved",
            "Approved resource ID: $resource_id, Title: {$resource['title']}"
        );
        
        // Award points to the user
        awardPoints(
            $resource['user_id'], 
            POINTS_UPLOAD, 
            'Resource Approved', 
            "Resource '{$resource['title']}' was approved by admin"
        );
        
        $message = "Resource has been approved successfully";
        
        // Send notification if checked
        if ($send_notification) {
            // Create notification for the user
            createNotification(
                $resource['user_id'],
                "Your resource '{$resource['title']}' has been approved",
                "resources/view.php?id=$resource_id"
            );
            
            // Send email notification if function exists
            if (function_exists('sendNotificationEmail')) {
                $notification_data = [
                    'email' => $resource['email'],
                    'first_name' => $resource['first_name'],
                    'resource_title' => $resource['title'],
                    'action_link' => APP_URL . "/student/resources/view.php?id=$resource_id"
                ];
                
                sendNotificationEmail('resource_approved', $notification_data);
            }
        }
    } else { // reject
        if (empty($feedback)) {
            throw new Exception("Feedback is required when rejecting a resource");
        }
        
        // Update resource status to rejected with feedback
        $stmt = $pdo->prepare("
            UPDATE resources 
            SET status = 'rejected', 
                admin_feedback = ?, 
                updated_at = NOW() 
            WHERE id = ?
        ");
        $result = $stmt->execute([$feedback, $resource_id]);
        
        if (!$result) {
            throw new Exception("Failed to update resource status");
        }
        
        // Log the action
        logAdminAction(
            $admin_id,
            "Resource rejected",
            "Rejected resource ID: $resource_id, Title: {$resource['title']}, Feedback: $feedback"
        );
        
        $message = "Resource has been rejected with feedback";
        
        // Send notification if checked
        if ($send_notification) {
            // Create notification for the user
            createNotification(
                $resource['user_id'],
                "Your resource '{$resource['title']}' needs revisions: $feedback",
                "resources/edit.php?id=$resource_id"
            );
            
            // Send email notification if function exists
            if (function_exists('sendNotificationEmail')) {
                $notification_data = [
                    'email' => $resource['email'],
                    'first_name' => $resource['first_name'],
                    'resource_title' => $resource['title'],
                    'rejection_reason' => $feedback,
                    'action_link' => APP_URL . "/student/resources/edit.php?id=$resource_id"
                ];
                
                sendNotificationEmail('resource_rejected', $notification_data);
            }
        }
    }
    
    $pdo->commit();
    $_SESSION['success_message'] = $message;
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error updating resource status: " . $e->getMessage());
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
}

// Redirect back to the resource view page
header("Location: view.php?id=" . $resource_id);
exit();
?>