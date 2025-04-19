<?php
// Start session
session_start();

// Include configuration
require_once 'includes/db-connection.php';

// Check if token and email are provided
if (!isset($_GET['token']) || !isset($_GET['email'])) {
    $_SESSION['error'] = "Invalid password reset link";
    header("Location: login.php");
    exit();
}

$token = $_GET['token'];
$email = $_GET['email'];

// Function to validate token
function validateToken($token, $email, $pdo) {
    // Get user ID by email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return false;
    }
    
    // Check if token exists and is valid
    $stmt = $pdo->prepare("
        SELECT * FROM password_reset_tokens 
        WHERE user_id = ? AND expiry > NOW()
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$user['id']]);
    $tokenRecord = $stmt->fetch();
    
    if (!$tokenRecord) {
        return false;
    }
    
    // Verify token
    return password_verify($token, $tokenRecord['token']);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    
    // Validate passwords
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }
    
    // If no errors, process password reset
    if (empty($errors)) {
        try {
            $pdo = getDbConnection();
            
            // Validate token
            if (!validateToken($token, $email, $pdo)) {
                $_SESSION['error'] = "Invalid or expired token";
                header("Location: password-reset.php");
                exit();
            }
            
            // Get user ID
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            // Update password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $user['id']]);
            
            // Delete used token
            $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            
            // Log the action
            $stmt = $pdo->prepare("
                INSERT INTO activity_log (user_id, action, details, ip_address)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $user['id'],
                'Password reset',
                'Password was reset using email link',
                $_SERVER['REMOTE_ADDR']
            ]);
            
            // Set success message and redirect to login
            $_SESSION['success'] = "Your password has been reset successfully. You can now login with your new password.";
            header("Location: login.php");
            exit();
            
        } catch (Exception $e) {
            $_SESSION['error'] = "An error occurred. Please try again.";
            error_log("Password reset error: " . $e->getMessage());
        }
    }
}

// Validate token before showing the form
try {
    $pdo = getDbConnection();
    $tokenValid = validateToken($token, $email, $pdo);
    
    if (!$tokenValid) {
        $_SESSION['error'] = "Invalid or expired password reset link";
        header("Location: password-reset.php");
        exit();
    }
} catch (Exception $e) {
    $_SESSION['error'] = "An error occurred. Please try again.";
    error_log("Token validation error: " . $e->getMessage());
    header("Location: password-reset.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="PolyEduHub - Reset Password" />
    <meta name="author" content="PolyEduHub Team" />
    <title>Reset Password - PolyEduHub</title>
    
    <!-- Bootstrap CSS -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" />
    
    <!-- PolyEduHub Custom CSS -->
    <link href="assets/css/polyeduhub.css" rel="stylesheet" />
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.11.2/css/all.min.css" />
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
                            <div class="card shadow-lg border-0 rounded-lg">
                                <div class="card-header justify-content-center">
                                    <h3 class="font-weight-light my-4">Set New Password</h3>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($errors)): ?>
                                        <div class="alert alert-danger">
                                            <ul class="mb-0">
                                                <?php foreach ($errors as $error): ?>
                                                    <li><?php echo htmlspecialchars($error); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <form method="post">
                                        <div class="form-group">
                                            <label class="small mb-1" for="inputPassword">New Password</label>
                                            <input class="form-control py-4" id="inputPassword" name="password" type="password" placeholder="Enter new password" required />
                                            <small class="form-text text-muted">Password must be at least 8 characters long.</small>
                                        </div>
                                        <div class="form-group">
                                            <label class="small mb-1" for="inputConfirmPassword">Confirm Password</label>
                                            <input class="form-control py-4" id="inputConfirmPassword" name="confirmPassword" type="password" placeholder="Confirm new password" required />
                                        </div>
                                        <div class="form-group d-flex align-items-center justify-content-end mt-4 mb-0">
                                            <button type="submit" class="btn btn-primary">Reset Password</button>
                                        </div>
                                    </form>
                                </div>
                                <div class="card-footer text-center">
                                    <div class="small"><a href="login.php">Return to login</a></div>
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
                        <div class="col-md-6 small">Copyright &copy; PolyEduHub <?php echo date('Y'); ?></div>
                        <div class="col-md-6 text-md-right small">
                            <a href="privacy.php" class="text-white">Privacy Policy</a>
                            &middot;
                            <a href="terms.php" class="text-white">Terms &amp; Conditions</a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    
    <!-- Bootstrap and jQuery JS -->
    <script src="https://code.jquery.com/jquery-3.4.1.min.js" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>