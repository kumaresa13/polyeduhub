<?php
// Start session
session_start();

// Include configuration and database connection
require_once 'includes/db-connection.php';
require_once 'includes/functions.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate and sanitize email input
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    // Check if email is valid
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email format: $email");
        $_SESSION['login_error'] = "Invalid email format";
        header("Location: login.php");
        exit();
    }
    
    // Get user password
    $userPassword = $_POST['password'];
    
    try {
        // Get database connection
        $pdo = getDbConnection();
        
        if (!$pdo) {
            error_log("Database connection failed");
            throw new Exception("Database connection failed");
        }
        
        // Prepare SQL statement to prevent SQL injection
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'student'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        // Check if user exists and password is correct
        if ($user && password_verify($userPassword, $user['password'])) {
            // Password is correct, start a new session
            session_regenerate_id(true);
            
            // Clear any previous session data
            $_SESSION = array();
            
            // Store data in session variables
            $_SESSION['loggedin'] = true;
            $_SESSION['id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['department'] = $user['department']; 
            $_SESSION['profile_image'] = $user['profile_image'] ?? '';
            
            // Update last login time
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Record login action in the activity log
            $action = "Student login";
            $details = "Student user logged in";
            $ip_address = $_SERVER['REMOTE_ADDR'];
            
            $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$user['id'], $action, $details, $ip_address]);
            
            // Award points for logging in (daily login reward)
            awardLoginPoints($user['id'], $pdo);
            
            // Set success message
            $_SESSION['success'] = "Welcome back, " . $user['first_name'] . "! You have successfully logged in.";
            
            // Redirect to student dashboard
            header("Location: student/dashboard.php");
            exit();
        } else {
            // Invalid credentials
            error_log("Login failed for email: $email - Invalid credentials");
            $_SESSION['login_error'] = "Incorrect email or password";
            header("Location: login.php");
            exit();
        }
    } catch (Exception $e) {
        // Handle database errors
        error_log("Login error: " . $e->getMessage());
        $_SESSION['login_error'] = "A system error occurred. Please try again later.";
        header("Location: login.php");
        exit();
    }
}

/**
 * Award points to user for daily login
 * 
 * @param int $userId The user ID
 * @param PDO $pdo Database connection
 * @return void
 */
function awardLoginPoints($userId, $pdo) {
    try {
        // Check if user has already received points today
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM activity_log 
            WHERE user_id = ? AND action = 'Login points awarded' 
            AND DATE(created_at) = ?
        ");
        $stmt->execute([$userId, $today]);
        $alreadyAwarded = $stmt->fetchColumn() > 0;
        
        if (!$alreadyAwarded) {
            // Award 5 points for daily login
            $loginPoints = 5;
            
            // Check if user_points record exists
            $checkStmt = $pdo->prepare("SELECT id FROM user_points WHERE user_id = ?");
            $checkStmt->execute([$userId]);
            $pointsRecord = $checkStmt->fetch();
            
            if ($pointsRecord) {
                // Update existing record
                $updateStmt = $pdo->prepare("
                    UPDATE user_points 
                    SET points = points + ?, last_updated = NOW() 
                    WHERE user_id = ?
                ");
                $updateStmt->execute([$loginPoints, $userId]);
            } else {
                // Create new record
                $insertStmt = $pdo->prepare("
                    INSERT INTO user_points (user_id, points, level) 
                    VALUES (?, ?, 1)
                ");
                $insertStmt->execute([$userId, $loginPoints]);
            }
            
            // Log the points award
            $logStmt = $pdo->prepare("
                INSERT INTO activity_log (user_id, action, details, ip_address) 
                VALUES (?, ?, ?, ?)
            ");
            $logStmt->execute([
                $userId, 
                'Login points awarded', 
                "Awarded $loginPoints points for daily login", 
                $_SERVER['REMOTE_ADDR']
            ]);
            
            // Check and update level if needed
            updateUserLevel($userId, $pdo);
            
            // Check for new badges
            checkForBadges($userId, $pdo);
        }
    } catch (Exception $e) {
        error_log("Error awarding login points: " . $e->getMessage());
    }
}

/**
 * Update user level based on points
 * 
 * @param int $userId The user ID
 * @param PDO $pdo Database connection
 * @return void
 */
function updateUserLevel($userId, $pdo) {
    try {
        // Get current points
        $stmt = $pdo->prepare("SELECT points, level FROM user_points WHERE user_id = ?");
        $stmt->execute([$userId]);
        $userPoints = $stmt->fetch();
        
        if ($userPoints) {
            $points = $userPoints['points'];
            $currentLevel = $userPoints['level'];
            
            // Calculate new level (1 level per 100 points)
            $newLevel = floor($points / 100) + 1;
            
            // If level increased, update it
            if ($newLevel > $currentLevel) {
                $updateStmt = $pdo->prepare("
                    UPDATE user_points 
                    SET level = ? 
                    WHERE user_id = ?
                ");
                $updateStmt->execute([$newLevel, $userId]);
                
                // Log level up
                $logStmt = $pdo->prepare("
                    INSERT INTO activity_log (user_id, action, details, ip_address) 
                    VALUES (?, ?, ?, ?)
                ");
                $logStmt->execute([
                    $userId, 
                    'Level up', 
                    "Advanced to level $newLevel", 
                    $_SERVER['REMOTE_ADDR']
                ]);
            }
        }
    } catch (Exception $e) {
        error_log("Error updating user level: " . $e->getMessage());
    }
}

/**
 * Check for new badges based on points
 * 
 * @param int $userId The user ID
 * @param PDO $pdo Database connection
 * @return void
 */
function checkForBadges($userId, $pdo) {
    try {
        // Get user's current points
        $pointsStmt = $pdo->prepare("SELECT points FROM user_points WHERE user_id = ?");
        $pointsStmt->execute([$userId]);
        $userPoints = $pointsStmt->fetch();
        
        if ($userPoints) {
            $points = $userPoints['points'];
            
            // Find badges that the user qualifies for but doesn't have yet
            $badgeStmt = $pdo->prepare("
                SELECT b.* FROM badges b
                LEFT JOIN user_badges ub ON b.id = ub.badge_id AND ub.user_id = ?
                WHERE ub.id IS NULL AND b.points_required <= ?
                ORDER BY b.points_required ASC
            ");
            $badgeStmt->execute([$userId, $points]);
            $newBadges = $badgeStmt->fetchAll();
            
            // Award new badges
            foreach ($newBadges as $badge) {
                $awardStmt = $pdo->prepare("
                    INSERT INTO user_badges (user_id, badge_id)
                    VALUES (?, ?)
                ");
                $awardStmt->execute([$userId, $badge['id']]);
                
                // Log badge award
                $logStmt = $pdo->prepare("
                    INSERT INTO activity_log (user_id, action, details, ip_address)
                    VALUES (?, ?, ?, ?)
                ");
                $logStmt->execute([
                    $userId,
                    'Badge earned',
                    "Earned the '{$badge['name']}' badge",
                    $_SERVER['REMOTE_ADDR']
                ]);
            }
        }
    } catch (Exception $e) {
        error_log("Error checking for badges: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="PolyEduHub Student Login - Educational Resource Sharing Platform" />
    <meta name="author" content="PolyEduHub Team" />
    <title>Student Login - PolyEduHub</title>
    
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
                                <div class="card-header justify-content-center"><h3 class="font-weight-light my-4">Student Login</h3></div>
                                <div class="card-body">
                                    <form id="studentLoginForm" action="login.php" method="POST">
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
                                                <input class="custom-control-input" id="rememberPasswordCheck" name="remember" type="checkbox" />
                                                <label class="custom-control-label" for="rememberPasswordCheck">Remember password</label>
                                            </div>
                                        </div>
                                        <div class="form-group d-flex align-items-center justify-content-between mt-4 mb-0">
                                            <a class="small" href="password-reset.php">Forgot Password?</a>
                                            <button type="submit" class="btn btn-primary">Login</button>
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