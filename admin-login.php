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
                
                // Set success message
                $_SESSION['success'] = "Welcome back, " . $user['first_name'] . "! You have successfully logged in as Administrator.";
                
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="PolyEduHub Admin Login - Educational Resource Sharing Platform" />
    <meta name="author" content="PolyEduHub Team" />
    <title>Admin Login - PolyEduHub</title>
    
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
                            <?php if (isset($_SESSION['success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle mr-2"></i>
                                    <?php 
                                        echo $_SESSION['success']; 
                                        unset($_SESSION['success']);
                                    ?>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($_SESSION['login_error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-circle mr-2"></i>
                                    <?php 
                                        echo $_SESSION['login_error']; 
                                        unset($_SESSION['login_error']);
                                    ?>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-circle mr-2"></i>
                                    <?php 
                                        echo $_SESSION['error']; 
                                        unset($_SESSION['error']);
                                    ?>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($_SESSION['registration_success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle mr-2"></i>
                                    <?php 
                                        echo $_SESSION['registration_success']; 
                                        unset($_SESSION['registration_success']);
                                    ?>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card shadow-lg border-0 rounded-lg">
                                <div class="card-header justify-content-center"><h3 class="font-weight-light my-4">Admin Login</h3></div>
                                <div class="card-body">
                                    <form id="adminLoginForm" action="admin-login.php" method="POST">
                                        <div class="form-group">
                                            <label class="small mb-1" for="inputEmailAddress">Email</label>
                                            <input class="form-control py-4" id="inputEmailAddress" name="email" type="email" placeholder="Enter email address" required />
                                        </div>
                                        <div class="form-group">
                                            <label class="small mb-1" for="inputPassword">Password</label>
                                            <input class="form-control py-4" id="inputPassword" name="password" type="password" placeholder="Enter password" required />
                                        </div>
                                        <div class="form-group">
                                            <div class="custom-control custom-checkbox">
                                                <input class="custom-control-input" id="rememberPasswordCheck" type="checkbox" />
                                                <label class="custom-control-label" for="rememberPasswordCheck">Remember password</label>
                                            </div>
                                        </div>
                                        <div class="form-group d-flex align-items-center justify-content-between mt-4 mb-0">
                                            <a class="small" href="admin-register.php">Need an admin account?</a>
                                            <button type="submit" class="btn btn-primary">Login</button>
                                        </div>
                                    </form>
                                </div>
                                <div class="card-footer text-center">
                                    <div class="small"><a href="index.php">Back to Home</a></div>
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
                            <div class="small text-white-50">Educational resource platform for Polytechnic students</div>
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
    <script src="assets/js/alerts.js"></script>
</body>
</html>