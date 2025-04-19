<?php
// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db-connection.php';
require_once '../../includes/functions.php';

// Start session only if it's not already active
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
    $stmt = $pdo->prepare("
        SELECT r.*, u.id as uploader_id 
        FROM resources r
        JOIN users u ON r.user_id = u.id
        WHERE r.id = ? AND r.status = 'approved'
    ");
    $stmt->execute([$resource_id]);
    $resource = $stmt->fetch();
    
    if (!$resource) {
        $_SESSION['error_message'] = "Resource not found or not approved";
        header("Location: index.php");
        exit();
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Update download count
    $stmt = $pdo->prepare("UPDATE resources SET download_count = download_count + 1 WHERE id = ?");
    $stmt->execute([$resource_id]);
    
    // Record download in history
    $stmt = $pdo->prepare("
        INSERT INTO resource_downloads (resource_id, user_id, downloaded_at)
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([$resource_id, $user_id]);
    
    // Award points to resource owner (but not if downloading own resource)
    if ($user_id != $resource['uploader_id']) {
        // Award points to resource owner
        awardPoints($resource['uploader_id'], POINTS_DOWNLOAD, 'Resource Downloaded', 
            "Your resource '{$resource['title']}' was downloaded by a user");
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Get the file path
    $file_path = RESOURCE_PATH . $resource['file_path'];
    
    // Check if file exists
    if (file_exists($file_path)) {
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
    } else {
        throw new Exception("File not found on server");
    }
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['error_message'] = "Error downloading file: " . $e->getMessage();
    header("Location: view.php?id=" . $resource_id);
    exit();
}
?>