<?php
// Start session
session_start();

// Database connection
require_once 'includes/db-connection.php';
require_once 'includes/mailer.php';

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
        
        if (!defined('APP_URL')) {
            define('APP_URL', 'http://localhost/polyeduhub');
        }
        
        // Create reset link with the actual domain
        $resetLink = APP_URL . "/password-new.php?token=" . urlencode($token) . "&email=" . urlencode($email);
        
        // Prepare data for the password reset email
        $resetData = [
            'first_name' => $firstName,
            'email' => $email,
            'reset_link' => $resetLink,
            'expiry_time' => 60 // Token expiration in minutes
        ];
        
        // Send the password reset email
        $emailSent = sendPasswordResetEmail($resetData);
        
        // Log email sending status
        if ($emailSent) {
            error_log("Password reset email sent to {$email}");
        } else {
            error_log("Failed to send password reset email to {$email}");
        }
        
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
    
    // Close connection
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="PolyEduHub Student Password Recovery - Educational Resource Sharing Platform" />
    <meta name="author" content="PolyEduHub Team" />
    <title>Student Password Recovery - PolyEduHub</title>
    
    <!-- Bootstrap CSS -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" />
    
    <!-- PolyEduHub Custom CSS -->
    <link href="assets/css/polyeduhub.css" rel="stylesheet" />
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.png" />
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.11.2/css/all.min.css" />
    
    <!-- Feather Icons -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.24.1/feather.min.js" crossorigin="anonymous"></script>
</head>
<body class="bg-primary">
    <div id="layoutAuthentication">
        <div id="layoutAuthentication_content">
            <main>
                <div class="container">
                    <div class="row justify-content-center">
                        <!-- Logo Section -->
                        <div class="col-lg-5 text-center mb-4">
                            <img src="assets/img/polyeduhub-logo.png" alt="PolyEduHub Logo" class="img-fluid mt-5" style="max-width: 280px;">
                        </div>
                    </div>
                    <div class="row justify-content-center">
                        <div class="col-lg-5">
                            <!-- Alert Messages -->
                            <?php if (isset($_SESSION['reset_error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-circle mr-2"></i>
                                    <?php 
                                        echo $_SESSION['reset_error']; 
                                        unset($_SESSION['reset_error']);
                                    ?>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($_SESSION['reset_success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle mr-2"></i>
                                    <?php 
                                        echo $_SESSION['reset_success']; 
                                        unset($_SESSION['reset_success']);
                                    ?>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                
                                <?php if (isset($_SESSION['demo_reset_link'])): ?>
                                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        <strong>Demo Mode:</strong> In a real environment, an email would be sent. Here's the reset link:
                                        <a href="<?php echo $_SESSION['demo_reset_link']; ?>" target="_blank" class="alert-link">
                                            Reset Password Link
                                        </a>
                                        <?php unset($_SESSION['demo_reset_link']); ?>
                                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <div class="card shadow-lg border-0 rounded-lg">
                                <div class="card-header justify-content-center"><h3 class="font-weight-light my-4">Student Password Recovery</h3></div>
                                <div class="card-body">
                                    <div class="small mb-3 text-muted">Enter your student email address and student ID for verification. We will send you a link to reset your password.</div>
                                    <form id="studentPasswordResetForm" action="password-reset.php" method="POST">
                                        <div class="form-group">
                                            <label class="small mb-1" for="inputEmailAddress">Email</label>
                                            <input class="form-control py-4" id="inputEmailAddress" name="email" type="email" aria-describedby="emailHelp" placeholder="Enter email address" required />
                                        </div>
                                        <div class="form-group">
                                            <label class="small mb-1" for="inputStudentID">Student ID</label>
                                            <input class="form-control py-4" id="inputStudentID" name="studentID" type="text" placeholder="Enter your student ID for verification" required />
                                        </div>
                                        <div class="form-group d-flex align-items-center justify-content-between mt-4 mb-0">
                                            <a class="small" href="login.php">Return to login</a>
                                            <button type="submit" class="btn btn-primary">Reset Password</button>
                                        </div>
                                    </form>
                                </div>
                                <div class="card-footer text-center">
                                    <div class="small"><a href="register.php">Need an account? Sign up!</a></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
        <div id="layoutAuthentication_footer">
            <footer class="footer mt-auto footer-dark">
                <div class="container">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="small text-white">
                                <strong>PolyEduHub</strong> &copy; <?php echo date('Y'); ?> All Rights Reserved
                            </div>
                            <div class="small text-white-50">A collaboration platform for Polytechnic students</div>
                        </div>
                        <div class="col-md-6 text-md-right">
                            <ul class="list-inline footer-links mb-0">
                                <li class="list-inline-item">
                                    <a href="about.php">About</a>
                                </li>
                                <li class="list-inline-item">
                                    <span class="footer-divider">|</span>
                                </li>
                                <li class="list-inline-item">
                                    <a href="contact.php">Contact</a>
                                </li>
                                <li class="list-inline-item">
                                    <span class="footer-divider">|</span>
                                </li>
                                <li class="list-inline-item">
                                    <a href="privacy.php">Privacy</a>
                                </li>
                                <li class="list-inline-item">
                                    <span class="footer-divider">|</span>
                                </li>
                                <li class="list-inline-item">
                                    <a href="terms.php">Terms</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    
    <!-- Bootstrap and jQuery JS -->
    <script src="https://code.jquery.com/jquery-3.4.1.min.js" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="js/scripts.js"></script>
    <script src="js/student-validation.js"></script>
    <script src="assets/js/alerts.js"></script>
</body>
</html>