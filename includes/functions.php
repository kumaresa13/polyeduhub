<?php

function formatFileSize($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
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
 * Log user activity
 * @param int $user_id User ID
 * @param string $action Action name
 * @param string $details Additional details
 * @return bool Success or failure
 */
function logActivity($user_id, $action, $details = '') {
    try {
        $pdo = getDbConnection();
        
        // Check if activity_log table exists
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
            AND table_name = 'activity_log'
        ");
        $stmt->execute();
        $tableExists = $stmt->fetchColumn() > 0;
        
        if (!$tableExists) {
            // Create activity_log table
            $pdo->exec("CREATE TABLE `activity_log` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `action` varchar(100) NOT NULL,
                `details` text NULL,
                `ip_address` varchar(45) NULL,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        
        // Insert activity log
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, action, details, ip_address, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}


?>