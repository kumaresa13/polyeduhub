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

// Get parameters
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
$after_id = isset($_GET['after']) ? intval($_GET['after']) : 0;

// Validate parameters
if (!$room_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid room ID']);
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

// Get new messages
$stmt = $pdo->prepare("
    SELECT cm.id, cm.message, cm.created_at, 
           u.id AS user_id, u.first_name, u.last_name, u.profile_image,
           (u.id = ?) AS is_own_message
    FROM chat_messages cm
    JOIN users u ON cm.user_id = u.id
    WHERE cm.room_id = ? AND cm.id > ?
    ORDER BY cm.created_at DESC
    LIMIT 50
");
$stmt->execute([$user_id, $room_id, $after_id]);
$messages = $stmt->fetchAll();

// Process messages for display
foreach ($messages as &$message) {
    // Convert booleans from strings to actual booleans
    $message['is_own_message'] = (bool)$message['is_own_message'];
    
    // Use default profile image if none exists
    if (empty($message['profile_image'])) {
        $message['profile_image'] = '../../assets/img/ui/default-profile.png';
    }
    
    // Escape HTML in message content
    $message['message'] = htmlspecialchars($message['message']);

    $message['time_ago'] = time_elapsed_string($message['created_at']);
}

// Return messages as JSON
echo json_encode([
    'success' => true,
    'messages' => $messages
]);
exit();