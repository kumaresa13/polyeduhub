<?php
// File: polyeduhub/login-process.php

// Start session
session_start();

// Include configuration and database connection
require_once 'includes/db-connection.php';
require_once 'includes/functions.php';

// Enable error logging
error_log("Login process started at " . date('Y-m-d H:i:s'));

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
    
    // Get user password (no variable conflict now since we're using constants for DB connection)
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
        
        // Log user details for debugging
        if ($user) {
            error_log("User found with ID: " . $user['id']);
        } else {
            error_log("No user found with email: $email");
        }
        
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
            
            // Log successful login and session data
            error_log("Login successful for user ID: " . $user['id']);
            error_log("Session data: " . print_r($_SESSION, true));
            
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
} else {
    // If someone tries to access this file directly
    header("Location: login.php");
    exit();
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