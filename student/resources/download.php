<?php
// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db-connection.php';
require_once '../../includes/functions.php';

// Start session and check if user is logged in
session_start();
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

// Get resource information
$pdo = getDbConnection();
$stmt = $pdo->prepare("
    SELECT r.id, r.title, r.file_path, r.file_type, r.user_id,
           (r.user_id = ?) as is_own_resource
    FROM resources r
    WHERE r.id = ? AND r.status = 'approved'
");
$stmt->execute([$user_id, $resource_id]);
$resource = $stmt->fetch();

if (!$resource) {
    $_SESSION['error_message'] = "Resource not found or not approved";
    header("Location: index.php");
    exit();
}

// If path exists, increment download count and record download
try {
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
    if (!$resource['is_own_resource']) {
        // Award points to resource owner
        $points_stmt = $pdo->prepare("
            INSERT INTO points_history (user_id, points, action, description)
            VALUES (?, ?, ?, ?)
        ");
        $points_stmt->execute([
            $resource['user_id'], 
            POINTS_DOWNLOAD, 
            'Resource Download', 
            "Your resource '{$resource['title']}' was downloaded by a user"
        ]);
        
        // Update user points
        $update_points_stmt = $pdo->prepare("
            UPDATE user_points 
            SET points = points + ? 
            WHERE user_id = ?
        ");
        $update_points_stmt->execute([POINTS_DOWNLOAD, $resource['user_id']]);
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
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['error_message'] = "Error downloading file: " . $e->getMessage();
    header("Location: view.php?id=" . $resource_id);
    exit();
}