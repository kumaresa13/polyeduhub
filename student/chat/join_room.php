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

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $room_code = filter_var($_POST['roomCode'], FILTER_SANITIZE_STRING);
    
    // Validate room code format (example: abcd1234-5)
    if (!preg_match('/^[a-zA-Z0-9]+-\d+$/', $room_code)) {
        $_SESSION['error_message'] = 'Invalid room code format';
        header("Location: index.php");
        exit();
    }
    
    // Extract room ID from code
    $parts = explode('-', $room_code);
    $room_id = intval(end($parts));
    
    // Check if room exists and is private or group
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        SELECT cr.id, cr.type 
        FROM chat_rooms cr
        WHERE cr.id = ? AND cr.type IN ('private', 'group')
    ");
    $stmt->execute([$room_id]);
    $room = $stmt->fetch();
    
    if (!$room) {
        $_SESSION['error_message'] = 'Invalid room code or room does not exist';
        header("Location: index.php");
        exit();
    }
    
    // Check if user is already a member
    $stmt = $pdo->prepare("
        SELECT id FROM chat_room_members
        WHERE room_id = ? AND user_id = ?
    ");
    $stmt->execute([$room_id, $user_id]);
    $existing_member = $stmt->fetch();
    
    if ($existing_member) {
        // User is already a member, redirect to room
        header("Location: room.php?id=" . $room_id);
        exit();
    }
    
    // Add user to room members
    try {
        $stmt = $pdo->prepare("
            INSERT INTO chat_room_members (room_id, user_id, joined_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$room_id, $user_id]);
        
        // Redirect to the room
        header("Location: room.php?id=" . $room_id);
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error joining room: ' . $e->getMessage();
        header("Location: index.php");
        exit();
    }
} else {
    // If not POST request
    header("Location: index.php");
    exit();
}