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
        $_SESSION['login_error'] = "Invalid email format";
        header("Location: admin-login.php");
        exit();
    }
    
    // Get password
    $adminpassword = $_POST['password'];
    
    // Prepare SQL statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT id, first_name, last_name, email, password, role FROM users WHERE email = ? AND role = 'admin'");
    $stmt->bind_param("s", $email);
    
    // Wrap the entire execution in a try-catch block
    try {
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Check if user exists and password is correct
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($adminpassword, $user['password'])) {
                // Password is correct, start a new session
                session_regenerate_id();
                
                // Store data in session variables
                $_SESSION['loggedin'] = true;
                $_SESSION['id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['role'] = $user['role'];
                
                // Record login action in the activity log
                $user_id = $user['id'];
                $action = "Admin login";
                $details = "Admin user logged in";
                $ip_address = $_SERVER['REMOTE_ADDR'];
                
                $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                $log_stmt->bind_param("isss", $user_id, $action, $details, $ip_address);
                $log_stmt->execute();
                
                // Redirect to admin dashboard
                header("Location: admin/dashboard.php");
                exit();
            } else {
                // Incorrect password
                $_SESSION['login_error'] = "Incorrect email or password";
                header("Location: admin-login.php");
                exit();
            }
        } else {
            // User not found
            $_SESSION['login_error'] = "Incorrect email or password";
            header("Location: admin-login.php");
            exit();
        }
    } catch (Exception $e) {
        // Catch any unexpected errors
        $_SESSION['login_error'] = "Login failed: " . $e->getMessage();
        header("Location: admin-login.php");
        exit();
    } finally {
        // Close statement
        $stmt->close();
    }
} else {
    // If someone tries to access this file directly
    header("Location: admin-login.php");
    exit();
}

// Close connection
$conn->close();
?>