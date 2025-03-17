<?php
// Place this file in: polyeduhub/includes/functions.php

/**
 * Format a timestamp into a readable date
 * @param string $timestamp The timestamp to format
 * @param string $format Output format (default: 'M d, Y')
 * @return string Formatted date
 */
function formatDate($timestamp, $format = 'M d, Y') {
    if (empty($timestamp)) return '';
    return date($format, strtotime($timestamp));
}

/**
 * Calculate time elapsed since given timestamp
 * @param string $datetime Timestamp
 * @return string Formatted time ago string
 */
function time_elapsed_string($datetime) {
    if (empty($datetime)) return 'unknown time';
    
    try {
        $now = new DateTime();
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);
        
        if ($diff->y > 0) {
            return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        } elseif ($diff->m > 0) {
            return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        } elseif ($diff->d > 0) {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        } elseif ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        } elseif ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        } else {
            return 'just now';
        }
    } catch (Exception $e) {
        error_log("Error in time_elapsed_string: " . $e->getMessage());
        return 'some time ago';
    }
}

/**
 * Format file size to human readable format
 * @param int $bytes File size in bytes
 * @param int $precision Decimal precision
 * @return string Formatted file size
 */
function formatFileSize($bytes, $precision = 2) {
    if (!is_numeric($bytes) || $bytes < 0) {
        return '0 B';
    }
    
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Generate a secure random token
 * @param int $length Token length
 * @return string Random token
 */
function generateToken($length = 32) {
    try {
        return bin2hex(random_bytes($length / 2));
    } catch (Exception $e) {
        error_log("Error generating token: " . $e->getMessage());
        // Fallback (less secure)
        return bin2hex(openssl_random_pseudo_bytes($length / 2));
    }
}





/**
 * Check if user is logged in
 * @return bool Whether the user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['id']);
}

/**
 * Check if the current user is an admin
 * @return bool Whether the user is an admin
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Redirect to a URL
 * @param string $url URL to redirect to
 * @return void
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Set a flash message to be displayed on the next page load
 * @param string $type Message type (success, error, info, warning)
 * @param string $message Message content
 * @return void
 */
function setFlashMessage($type, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear all flash messages
 * @return array Flash messages
 */
function getFlashMessages() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $messages = $_SESSION['flash_messages'] ?? [];
    $_SESSION['flash_messages'] = [];
    return $messages;
}

/**
 * Display flash messages HTML
 * @return string HTML for flash messages
 */
function displayFlashMessages() {
    $messages = getFlashMessages();
    $html = '';
    
    foreach ($messages as $message) {
        $type = $message['type'];
        $content = $message['message'];
        
        // Map message type to Bootstrap alert class
        $class = 'alert-info';
        if ($type === 'success') $class = 'alert-success';
        if ($type === 'error') $class = 'alert-danger';
        if ($type === 'warning') $class = 'alert-warning';
        
        $html .= "<div class='alert $class alert-dismissible fade show' role='alert'>
                    $content
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                 </div>";
    }
    
    return $html;
}

/**
 * Sanitize input data to prevent XSS
 * @param string $data Input data
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Get file extension from filename
 * @param string $filename Filename
 * @return string File extension
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Award points to a user
 * @param int $user_id User ID
 * @param int $points Points to award
 * @param string $action Action description
 * @param string $description Detailed description
 * @return bool Whether points were successfully awarded
 */
function awardPoints($user_id, $points, $action, $description = '') {
    try {
        $pdo = getDbConnection();
        if (!$pdo) {
            return false;
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Check if user has a points record
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_points WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $exists = (bool) $stmt->fetchColumn();
        
        if ($exists) {
            // Update existing points
            $stmt = $pdo->prepare("UPDATE user_points SET points = points + ?, last_updated = NOW() WHERE user_id = ?");
            $stmt->execute([$points, $user_id]);
        } else {
            // Create new points record
            $stmt = $pdo->prepare("INSERT INTO user_points (user_id, points, level, last_updated) VALUES (?, ?, 1, NOW())");
            $stmt->execute([$user_id, $points]);
        }
        
        // Record points history
        $stmt = $pdo->prepare("
            INSERT INTO points_history (user_id, points, action, description, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $points, $action, $description]);
        
        // Update user level based on points
        $stmt = $pdo->prepare("
            UPDATE user_points 
            SET level = CASE
                WHEN points >= 5000 THEN 5
                WHEN points >= 1000 THEN 4
                WHEN points >= 500 THEN 3
                WHEN points >= 100 THEN 2
                ELSE 1
            END
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        
        // Commit transaction
        $pdo->commit();
        
        return true;
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Error awarding points: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if a string starts with a specific substring
 * @param string $haystack String to search in
 * @param string $needle String to search for
 * @return bool Whether $haystack starts with $needle
 */
function startsWith($haystack, $needle) {
    return strpos($haystack, $needle) === 0;
}

/**
 * Check if a string ends with a specific substring
 * @param string $haystack String to search in
 * @param string $needle String to search for
 * @return bool Whether $haystack ends with $needle
 */
function endsWith($haystack, $needle) {
    return substr($haystack, -strlen($needle)) === $needle;
}

/**
 * Truncate a string to a maximum length
 * @param string $string String to truncate
 * @param int $length Maximum length
 * @param string $append String to append if truncated
 * @return string Truncated string
 */
function truncateString($string, $length = 100, $append = '...') {
    if (strlen($string) <= $length) {
        return $string;
    }
    
    return substr($string, 0, $length) . $append;
}

/**
 * Get a random greeting message
 * @param string $name Person's name
 * @return string Greeting message
 */
function getRandomGreeting($name) {
    $greetings = [
        "Hello, $name!",
        "Welcome back, $name!",
        "Good to see you, $name!",
        "Hi there, $name!",
        "Greetings, $name!"
    ];
    
    return $greetings[array_rand($greetings)];
}

/**
 * Create a notification for a user
 * @param int $user_id User ID
 * @param string $message Notification message
 * @param string $link Optional link
 * @return int|bool Notification ID or false on failure
 */
function createNotification($user_id, $message, $link = '') {
    try {
        $pdo = getDbConnection();
        if (!$pdo) {
            return false;
        }
        
        // Check if notifications table exists
        $tableExists = $pdo->query("SHOW TABLES LIKE 'notifications'")->rowCount() > 0;
        
        if (!$tableExists) {
            // Create notifications table if it doesn't exist
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `notifications` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `user_id` int(11) NOT NULL,
                    `message` varchar(255) NOT NULL,
                    `link` varchar(255) DEFAULT NULL,
                    `is_read` tinyint(1) NOT NULL DEFAULT 0,
                    `created_at` datetime NOT NULL,
                    PRIMARY KEY (`id`),
                    KEY `user_id` (`user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        }
        
        // Insert notification
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, message, link, is_read, created_at) 
            VALUES (?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$user_id, $message, $link]);
        
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notification count for a user
 * @param int $user_id User ID
 * @return int Number of unread notifications
 */
function getUnreadNotificationCount($user_id) {
    try {
        $pdo = getDbConnection();
        if (!$pdo) {
            return 0;
        }
        
        // Check if notifications table exists
        $tableExists = $pdo->query("SHOW TABLES LIKE 'notifications'")->rowCount() > 0;
        
        if (!$tableExists) {
            return 0;
        }
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn() ?: 0;
    } catch (Exception $e) {
        error_log("Error getting notification count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get recent notifications for a user
 * @param int $user_id User ID
 * @param int $limit Maximum number of notifications
 * @return array Notifications
 */
function getRecentNotifications($user_id, $limit = 5) {
    try {
        $pdo = getDbConnection();
        if (!$pdo) {
            return [];
        }
        
        // Check if notifications table exists
        $tableExists = $pdo->query("SHOW TABLES LIKE 'notifications'")->rowCount() > 0;
        
        if (!$tableExists) {
            return [];
        }
        
        $stmt = $pdo->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting recent notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark notifications as read
 * @param int $user_id User ID
 * @param int|null $notification_id Specific notification ID or null for all
 * @return bool Whether notifications were marked as read
 */
function markNotificationsAsRead($user_id, $notification_id = null) {
    try {
        $pdo = getDbConnection();
        if (!$pdo) {
            return false;
        }
        
        // Check if notifications table exists
        $tableExists = $pdo->query("SHOW TABLES LIKE 'notifications'")->rowCount() > 0;
        
        if (!$tableExists) {
            return false;
        }
        
        if ($notification_id) {
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$notification_id, $user_id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$user_id]);
        }
        
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Error marking notifications as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Create database tables if they don't exist
 * @return bool Whether tables were created successfully
 */
function ensureTablesExist() {
    try {
        $pdo = getDbConnection();
        if (!$pdo) {
            return false;
        }
        
        // Check if notifications table exists
        $notificationsExists = $pdo->query("SHOW TABLES LIKE 'notifications'")->rowCount() > 0;
        
        if (!$notificationsExists) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `notifications` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `user_id` int(11) NOT NULL,
                    `message` varchar(255) NOT NULL,
                    `link` varchar(255) DEFAULT NULL,
                    `is_read` tinyint(1) NOT NULL DEFAULT 0,
                    `created_at` datetime NOT NULL,
                    PRIMARY KEY (`id`),
                    KEY `user_id` (`user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error ensuring tables exist: " . $e->getMessage());
        return false;
    }
}
?>