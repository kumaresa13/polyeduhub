<?php
// Start session
session_start();

// Database connection
require_once 'includes/db-connection.php';
require_once 'includes/mailer.php';

// Define the APP_URL constant for links in emails if it's not already defined
if (!defined('APP_URL')) {
    define('APP_URL', 'http://localhost/polyeduhub');
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate and sanitize inputs
    $firstName = filter_var($_POST['firstName'], FILTER_SANITIZE_STRING);
    $lastName = filter_var($_POST['lastName'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $studentID = filter_var($_POST['studentID'], FILTER_SANITIZE_STRING);
    $department = filter_var($_POST['department'], FILTER_SANITIZE_STRING);
    $yearOfStudy = filter_var($_POST['yearOfStudy'], FILTER_SANITIZE_NUMBER_INT);
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
    
    // Validate student ID
    if (empty($studentID)) {
        $errors[] = "Student ID is required";
    }
    
    // Validate department
    if (empty($department)) {
        $errors[] = "Department is required";
    }
    
    // Validate year of study
    if (empty($yearOfStudy) || !in_array($yearOfStudy, [1, 2, 3, 4, 5])) {
        $errors[] = "Valid semester is required";
    }
    
    // Validate password
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }
    
    // Check if passwords match
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }
    
    // Verify terms agreement
    if (!isset($_POST['termsAgreed'])) {
        $errors[] = "You must agree to the terms and conditions";
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $errors[] = "Email already exists";
    }
    
    // Check if student ID already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE student_id = ? AND role = 'student'");
    $stmt->bind_param("s", $studentID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $errors[] = "Student ID already exists";
    }
    
    // If there are errors, redirect back to the registration form
    if (!empty($errors)) {
        $_SESSION['registration_errors'] = $errors;
        header("Location: register.php");
        exit();
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new student user
    $role = "student";
    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, role, student_id, department, year_of_study, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssssssi", $firstName, $lastName, $email, $hashedPassword, $role, $studentID, $department, $yearOfStudy);
    
    if ($stmt->execute()) {
        // Registration successful
        $userId = $stmt->insert_id;
        
        // Initialize user points (gamification)
        $initPointsStmt = $conn->prepare("INSERT INTO user_points (user_id, points, level) VALUES (?, 0, 1)");
        $initPointsStmt->bind_param("i", $userId);
        $initPointsStmt->execute();
        
        // Award the newcomer badge
        $newcomerBadgeId = 1; // Assuming ID 1 is the newcomer badge as set in the SQL setup
        $badgeStmt = $conn->prepare("INSERT INTO user_badges (user_id, badge_id) VALUES (?, ?)");
        $badgeStmt->bind_param("ii", $userId, $newcomerBadgeId);
        $badgeStmt->execute();
        
        // Record points for joining
        $joinPoints = 10;
        $pointsAction = "Account Creation";
        $pointsDetails = "Points awarded for creating an account";
        
        $pointsStmt = $conn->prepare("INSERT INTO points_history (user_id, points, action, description) VALUES (?, ?, ?, ?)");
        $pointsStmt->bind_param("iiss", $userId, $joinPoints, $pointsAction, $pointsDetails);
        $pointsStmt->execute();
        
        // Update user points
        $updatePointsStmt = $conn->prepare("UPDATE user_points SET points = points + ? WHERE user_id = ?");
        $updatePointsStmt->bind_param("ii", $joinPoints, $userId);
        $updatePointsStmt->execute();
        
        // Log the registration
        $action = "Student registration";
        $details = "New student account created";
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        
        $logStmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $logStmt->bind_param("isss", $userId, $action, $details, $ipAddress);
        $logStmt->execute();
        
        // Send welcome email to the student
        try {
            $welcome_data = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'role' => 'student'
            ];
            
            // Send welcome email
            $emailSent = sendWelcomeEmail($welcome_data);
            
            // Log email status
            if ($emailSent) {
                error_log("Welcome email sent to {$email}");
            } else {
                error_log("Failed to send welcome email to {$email}");
            }
            
        } catch (Exception $e) {
            // Log the error but continue with registration
            error_log("Error sending welcome email: " . $e->getMessage());
        }
        
        $_SESSION['registration_success'] = "Congratulations! Your account has been created successfully. You can now login. A welcome email has been sent to your email address.";
        header("Location: login.php");
        exit();
    } else {
        // Registration failed
        $_SESSION['registration_errors'] = array("Registration failed: " . $conn->error);
        header("Location: register.php");
        exit();
    }
    
    // Close statement
    $stmt->close();
} else {
    // If someone tries to access this file directly
    header("Location: register.php");
    exit();
}

// Close connection
$conn->close();
?>