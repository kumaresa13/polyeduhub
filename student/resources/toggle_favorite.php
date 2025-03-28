<?php
// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db-connection.php';
require_once '../../includes/functions.php';

// Start session and check if user is logged in
session_start();
if (!isset($_SESSION['id'])) {
    // Return error response
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Get user information
$user_id = $_SESSION['id'];

// Check if request is AJAX and POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get parameters
$resource_id = isset($_POST['resource_id']) ? intval($_POST['resource_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Validate inputs
if (!$resource_id || !in_array($action, ['add', 'remove'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

// Check if resource exists and is approved
$pdo = getDbConnection();
$stmt = $pdo->prepare("SELECT id FROM resources WHERE id = ? AND status = 'approved'");
$stmt->execute([$resource_id]);
$resource = $stmt->fetch();

if (!$resource) {
    echo json_encode(['success' => false, 'message' => 'Resource not found or not approved']);
    exit();
}

try {
    if ($action === 'add') {
        // Check if already favorited
        $stmt = $pdo->prepare("SELECT id FROM resource_favorites WHERE resource_id = ? AND user_id = ?");
        $stmt->execute([$resource_id, $user_id]);
        $existing = $stmt->fetch();
        
        if (!$existing) {
            // Add to favorites
            $stmt = $pdo->prepare("
                INSERT INTO resource_favorites (resource_id, user_id, created_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$resource_id, $user_id]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Resource added to favorites']);
    } else {
        // Remove from favorites
        $stmt = $pdo->prepare("
            DELETE FROM resource_favorites
            WHERE resource_id = ? AND user_id = ?
        ");
        $stmt->execute([$resource_id, $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'Resource removed from favorites']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit();