<?php
// Start session
session_start();

// Database connection
require_once 'includes/db-connection.php';


// Define the admin registration code (should be stored securely in a real application)
define("ADMIN_REGISTRATION_CODE", "polyeduhub2025admin");

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate and sanitize inputs
    $firstName = filter_var($_POST['firstName'], FILTER_SANITIZE_STRING);
    $lastName = filter_var($_POST['lastName'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $adminCode = $_POST['adminCode'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    
    // Initialize an errors array
    $errors = array();
    
    // Validate first name
    if (empty($firstName)) {
        $errors[] = "First name is required";
    }
    
    // Validate last name
    if (empty($lastName)) {
        $errors[] = "Last name is required";
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    
    // Check if admin code is correct
    if ($adminCode !== ADMIN_REGISTRATION_CODE) {
        $errors[] = "Invalid admin registration code";
    }
    
    // Validate password
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }
    
    // Check if passwords match
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $errors[] = "Email already exists";
    }
    
    // If there are errors, redirect back to the registration form
    if (!empty($errors)) {
        $_SESSION['registration_errors'] = $errors;
        header("Location: admin-register.php");
        exit();
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new admin user
    $role = "admin";
    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssss", $firstName, $lastName, $email, $hashedPassword, $role);
    
    if ($stmt->execute()) {
        // Registration successful
        $userId = $stmt->insert_id;
        
        // Log the registration
        $action = "Admin registration";
        $details = "New admin account created";
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        
        $logStmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $logStmt->bind_param("isss", $userId, $action, $details, $ipAddress);
        $logStmt->execute();
        
        // Send welcome email to the registered admin
        $welcome_data = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'role' => 'admin'
        ];
        
        
        
        $_SESSION['registration_success'] = "Admin account created successfully. You can now login.";
        header("Location: admin-login.php");
        exit();
    } else {
        // Registration failed
        $_SESSION['registration_errors'] = array("Registration failed: " . $conn->error);
        header("Location: admin-register.php");
        exit();
    }
    
    // Close statement
    $stmt->close();
} else {
    // If someone tries to access this file directly
    header("Location: admin-register.php");
    exit();
}

// Close connection
$conn->close();
?>