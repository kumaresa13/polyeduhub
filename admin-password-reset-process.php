<?php
// Start session
session_start();

// Database connection
require_once 'includes/db-connection.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate and sanitize email input
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    // Check if email is valid
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Invalid email format
        $_SESSION['reset_error'] = "Invalid email format";
        header("Location: admin-password-reset.php");
        exit();
    }
    
    // Check if email exists and is an admin account
    $stmt = $conn->prepare("SELECT id, first_name FROM users WHERE email = ? AND role = 'admin'");
    $stmt->bind_param("s", $email);
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
        
        // Store token in the database
        $resetStmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expiry) VALUES (?, ?, ?)");
        $resetStmt->bind_param("iss", $userId, $tokenHash, $expiry);
        $resetStmt->execute();
        
        // In a real application, send an email with the reset link
        // For this example, we'll just display a message
        
        // Create reset link (in a real application, this would be included in an email)
        $resetLink = "https://yourdomain.com/admin-password-new.php?token=" . $token . "&email=" . urlencode($email);
        
        // Log the password reset request
        $action = "Admin password reset request";
        $details = "Password reset requested for admin account";
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        
        $logStmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $logStmt->bind_param("isss", $userId, $action, $details, $ipAddress);
        $logStmt->execute();
        
        // Set success message
        $_SESSION['reset_success'] = "If an account with that email exists, we've sent a password reset link. Please check your email.";
        
        // For demonstration purposes only - in a real application, this would be sent via email
        $_SESSION['demo_reset_link'] = $resetLink;
        
        header("Location: admin-password-reset.php");
        exit();
    } else {
        // For security reasons, don't disclose whether the email exists
        $_SESSION['reset_success'] = "If an account with that email exists, we've sent a password reset link. Please check your email.";
        header("Location: admin-password-reset.php");
        exit();
    }
    
    // Close statement
    $stmt->close();
} else {
    // If someone tries to access this file directly
    header("Location: admin-password-reset.php");
    exit();
}

// Close connection
$conn->close();
?>