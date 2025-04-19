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
    
    // If there are errors, store in session to display after redirect
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
    <meta name="description" content="PolyEduHub Admin Login - Educational Resource Sharing Platform" />
    <meta name="author" content="PolyEduHub Team" />
        <title>Admin Registration - PolyEduHub</title>
        <!-- Bootstrap CSS -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" />
    
    <!-- PolyEduHub Custom CSS -->
    <link href="assets/css/polyeduhub.css" rel="stylesheet" />
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon.png" />
    
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
                            <div class="col-lg-7 text-center mb-4">
                                <img src="assets/img/polyeduhub-logo.png" alt="PolyEduHub Logo" class="img-fluid mt-5" style="max-width: 280px;">
                            </div>
                        </div>
                        <div class="row justify-content-center">
                            <div class="col-lg-7">
                                <!-- Display errors if any -->
                                <?php if (isset($_SESSION['registration_errors'])): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="fas fa-exclamation-circle mr-2"></i>
                                        <strong>Registration Error:</strong>
                                        <ul class="mb-0 mt-2">
                                            <?php foreach ($_SESSION['registration_errors'] as $error): ?>
                                                <li><?php echo htmlspecialchars($error); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php unset($_SESSION['registration_errors']); ?>
                                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card shadow-lg border-0 rounded-lg">
                                    <div class="card-header justify-content-center"><h3 class="font-weight-light my-4">Create Admin Account</h3></div>
                                    <div class="card-body">
                                        <form id="adminRegisterForm" action="admin-register.php" method="POST">
                                            <div class="form-row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="small mb-1" for="inputFirstName">First Name</label>
                                                        <input class="form-control py-4" id="inputFirstName" name="firstName" type="text" placeholder="Enter first name" required />
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="small mb-1" for="inputLastName">Last Name</label>
                                                        <input class="form-control py-4" id="inputLastName" name="lastName" type="text" placeholder="Enter last name" required />
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label class="small mb-1" for="inputEmailAddress">Email</label>
                                                <input class="form-control py-4" id="inputEmailAddress" name="email" type="email" aria-describedby="emailHelp" placeholder="Enter email address" required />
                                            </div>
                                            <div class="form-group">
                                                <label class="small mb-1" for="inputAdminCode">Admin Registration Code</label>
                                                <input class="form-control py-4" id="inputAdminCode" name="adminCode" type="text" placeholder="Enter admin registration code" required />
                                                <small class="form-text text-muted">This code is required for admin registration and is provided by system administrators.</small>
                                            </div>
                                            <div class="form-row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="small mb-1" for="inputPassword">Password</label>
                                                        <input class="form-control py-4" id="inputPassword" name="password" type="password" placeholder="Enter password" required />
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="small mb-1" for="inputConfirmPassword">Confirm Password</label>
                                                        <input class="form-control py-4" id="inputConfirmPassword" name="confirmPassword" type="password" placeholder="Confirm password" required />
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group mt-4 mb-0">
                                                <button type="submit" class="btn btn-primary btn-block">Create Account</button>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="card-footer text-center">
                                        <div class="small"><a href="admin-login.php">Have an admin account? Go to login</a></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
            <div id="layoutAuthentication_footer">
                <footer class="footer mt-auto footer-dark">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-md-6 small">Copyright &copy; PolyEduHub 2025</div>
                            <div class="col-md-6 text-md-right small">
                                <a href="#!">Privacy Policy</a>
                                &middot;
                                <a href="#!">Terms &amp; Conditions</a>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
        <script src="https://code.jquery.com/jquery-3.4.1.min.js" crossorigin="anonymous"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
    </body>
</html>