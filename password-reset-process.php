<?php
// Start session
session_start();

// Database connection
require_once 'includes/db-connection.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate and sanitize inputs
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $studentID = filter_var($_POST['studentID'], FILTER_SANITIZE_STRING);
    
    // Check if email is valid
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Invalid email format
        $_SESSION['reset_error'] = "Invalid email format";
        header("Location: password-reset.php");
        exit();
    }
    
    // Check if student ID is provided
    if (empty($studentID)) {
        $_SESSION['reset_error'] = "Student ID is required for verification";
        header("Location: password-reset.php");
        exit();
    }
    
    // Check if email exists and matches the student ID
    $stmt = $conn->prepare("SELECT id, first_name FROM users WHERE email = ? AND student_id = ? AND role = 'student'");
    $stmt->bind_param("ss", $email, $studentID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $userId = $user['id'];
        $firstName = $user['first_name'];
        
        // Generate a unique token
        $token = bin2hex(random_bytes(32));
        $tokenHash = password_hash($token, PASSWORD_DEFAULT);
        
        // Set expiration time to 1 hour from now
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Delete any existing tokens for this user
        $deleteStmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
        $deleteStmt->bind_param("i", $userId);
        $deleteStmt->execute();
        
        // Store token in the database
        $resetStmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expiry) VALUES (?, ?, ?)");
        $resetStmt->bind_param("iss", $userId, $tokenHash, $expiry);
        $resetStmt->execute();
        
        // In a real application, send an email with the reset link
        // For this example, we'll just display a message
        
        // Create reset link (in a real application, this would be included in an email)
        $resetLink = "https://yourdomain.com/password-new.php?token=" . $token . "&email=" . urlencode($email);
        
        // Log the password reset request
        $action = "Password reset request";
        $details = "Password reset requested for student account";
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        
        $logStmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $logStmt->bind_param("isss", $userId, $action, $details, $ipAddress);
        $logStmt->execute();
        
        // Set success message
        $_SESSION['reset_success'] = "If an account with that email and student ID exists, we've sent a password reset link. Please check your email.";
        
        // For demonstration purposes only - in a real application, this would be sent via email
        $_SESSION['demo_reset_link'] = $resetLink;
        
        header("Location: password-reset.php");
        exit();
    } else {
        // For security reasons, don't disclose whether the email exists or if the student ID doesn't match
        $_SESSION['reset_success'] = "If an account with that email and student ID exists, we've sent a password reset link. Please check your email.";
        header("Location: password-reset.php");
        exit();
    }
    
    // Close statement
    $stmt->close();
} else {
    // If someone tries to access this file directly
    header("Location: password-reset.php");
    exit();
}

// Close connection
$conn->close();
?>