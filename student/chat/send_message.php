<?php
// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db-connection.php';
require_once '../../includes/functions.php';

// Start session and check if user is logged in
session_start();
if (!isset($_SESSION['id'])) {
    // Return error response
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

// Get user information
$user_id = $_SESSION['id'];

// Check if request is AJAX and POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

// Get parameters
$room_id = isset($_POST['room_id']) ? intval($_POST['room_id']) : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

// Validate inputs
if (!$room_id || empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit();
}

// Check if user has access to the room
$pdo = getDbConnection();
$stmt = $pdo->prepare("
    SELECT cr.id
    FROM chat_rooms cr 
    LEFT JOIN chat_room_members crm ON cr.id = crm.room_id AND crm.user_id = ?
    WHERE cr.id = ? AND (cr.type = 'public' OR (cr.type IN ('private', 'group') AND crm.user_id IS NOT NULL))
");
$stmt->execute([$user_id, $room_id]);
$room = $stmt->fetch();

if (!$room) {
    echo json_encode(['success' => false, 'error' => 'You do not have access to this room']);
    exit();
}

try {
    // Insert message
    $stmt = $pdo->prepare("
        INSERT INTO chat_messages (room_id, user_id, message, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$room_id, $user_id, $message]);
    
    // Get the inserted message ID
    $message_id = $pdo->lastInsertId();
    
    // Award points for chat participation (optional)
    // You can award points for chatting if you want to encourage participation
    // Example: awardPoints($user_id, 1, 'Chat Message', "Posted a message in chat room");
    
    echo json_encode([
        'success' => true, 
        'message_id' => $message_id
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
exit();