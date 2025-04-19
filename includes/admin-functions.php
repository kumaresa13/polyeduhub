<?php
/**
 * Log admin action
 * @param int $admin_id Admin user ID
 * @param string $action Action performed
 * @param string $details Additional details about the action
 * @return bool Whether the action was logged successfully
 */
function logAdminAction($admin_id, $action, $details = '') {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, action, details, ip_address, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $admin_id,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR']
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Failed to log admin action: " . $e->getMessage());
        return false;
    }
}


