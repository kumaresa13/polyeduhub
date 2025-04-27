<?php
// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db-connection.php';
require_once '../../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    // Redirect to login page if not logged in
    header("Location: ../../login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['id'];

// Get resource ID (support both GET and POST methods)
$resource_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['resource_id']) ? intval($_POST['resource_id']) : 0);

if (!$resource_id) {
    $_SESSION['error_message'] = "Invalid resource ID";
    header("Location: my-resources.php");
    exit();
}

// Check if the resource exists and belongs to the user
$pdo = getDbConnection();
$stmt = $pdo->prepare("
    SELECT id, file_path, title 
    FROM resources 
    WHERE id = ? AND user_id = ?
");
$stmt->execute([$resource_id, $user_id]);
$resource = $stmt->fetch();

if (!$resource) {
    $_SESSION['error_message'] = "Resource not found or you don't have permission to delete it";
    header("Location: my-resources.php");
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Delete resource from database
    $stmt = $pdo->prepare("DELETE FROM resources WHERE id = ?");
    $stmt->execute([$resource_id]);
    
    // Delete resource tag relationships
    $stmt = $pdo->prepare("DELETE FROM resource_tag_relationship WHERE resource_id = ?");
    $stmt->execute([$resource_id]);
    
    // Delete actual file
    $file_path = RESOURCE_PATH . $resource['file_path'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    // Delete thumbnail if it exists
    $thumbnail_path = RESOURCE_PATH . 'thumbnails/' . pathinfo($resource['file_path'], PATHINFO_FILENAME) . '.jpg';
    if (file_exists($thumbnail_path)) {
        unlink($thumbnail_path);
    }
    
    // Commit transaction
    $pdo->commit();
    
    $_SESSION['success_message'] = "Resource '{$resource['title']}' has been deleted successfully";
} catch (Exception $e) {
    // Rollback on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = "Error deleting resource: " . $e->getMessage();
}

// Redirect back to my resources page
header("Location: my-resources.php");
exit();