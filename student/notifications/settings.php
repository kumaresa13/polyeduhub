<?php
// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db-connection.php';
require_once '../../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    // Redirect to login page if not logged in
    header("Location: ../../login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['id'];

// Check if notification_settings table exists
try {
    $pdo = getDbConnection();
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
        AND table_name = 'notification_settings'
    ");
    $stmt->execute();
    $table_exists = $stmt->fetchColumn() > 0;
    
    // Create table if it doesn't exist
    if (!$table_exists) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `notification_settings` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `resource_approved` tinyint(1) NOT NULL DEFAULT 1,
                `resource_rejected` tinyint(1) NOT NULL DEFAULT 1,
                `resource_comment` tinyint(1) NOT NULL DEFAULT 1,
                `resource_rating` tinyint(1) NOT NULL DEFAULT 1,
                `resource_download` tinyint(1) NOT NULL DEFAULT 1,
                `badge_earned` tinyint(1) NOT NULL DEFAULT 1,
                `email_notifications` tinyint(1) NOT NULL DEFAULT 1,
                `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
    
    // Get user settings or create default settings
    $stmt = $pdo->prepare("SELECT * FROM notification_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch();
    
    if (!$settings) {
        // Create default settings
        $stmt = $pdo->prepare("
            INSERT INTO notification_settings 
            (user_id, resource_approved, resource_rejected, resource_comment, 
             resource_rating, resource_download, badge_earned, email_notifications) 
            VALUES (?, 1, 1, 1, 1, 1, 1, 1)
        ");
        $stmt->execute([$user_id]);
        
        // Get the newly created settings
        $stmt = $pdo->prepare("SELECT * FROM notification_settings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $settings = $stmt->fetch();
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get form data (converting checkbox values to 0/1)
        $resource_approved = isset($_POST['resource_approved']) ? 1 : 0;
        $resource_rejected = isset($_POST['resource_rejected']) ? 1 : 0;
        $resource_comment = isset($_POST['resource_comment']) ? 1 : 0;
        $resource_rating = isset($_POST['resource_rating']) ? 1 : 0;
        $resource_download = isset($_POST['resource_download']) ? 1 : 0;
        $badge_earned = isset($_POST['badge_earned']) ? 1 : 0;
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        
        // Update settings
        $stmt = $pdo->prepare("
            UPDATE notification_settings 
            SET resource_approved = ?, resource_rejected = ?, resource_comment = ?,
                resource_rating = ?, resource_download = ?, badge_earned = ?,
                email_notifications = ?, updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([
            $resource_approved, $resource_rejected, $resource_comment,
            $resource_rating, $resource_download, $badge_earned,
            $email_notifications, $user_id
        ]);
        
        // Update settings in the current page
        $settings['resource_approved'] = $resource_approved;
        $settings['resource_rejected'] = $resource_rejected;
        $settings['resource_comment'] = $resource_comment;
        $settings['resource_rating'] = $resource_rating;
        $settings['resource_download'] = $resource_download;
        $settings['badge_earned'] = $badge_earned;
        $settings['email_notifications'] = $email_notifications;
        
        $_SESSION['success_message'] = "Notification settings updated successfully";
    }
    
} catch (PDOException $e) {
    error_log("Error in notification settings: " . $e->getMessage());
    $settings = [
        'resource_approved' => 1,
        'resource_rejected' => 1,
        'resource_comment' => 1,
        'resource_rating' => 1,
        'resource_download' => 1,
        'badge_earned' => 1,
        'email_notifications' => 1
    ];
}

// Page title
$page_title = "Notification Settings";
$nested = true;

// Include header
include_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Notification Settings</h1>
        <a href="index.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back to Notifications
        </a>
    </div>
    
    <!-- Display Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['success_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <!-- Notification Settings Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Manage Your Notifications</h6>
        </div>
        <div class="card-body">
            <form action="" method="POST">
                <div class="mb-4">
                    <h5 class="font-weight-bold">Resource Notifications</h5>
                    <p class="text-muted small">Control notifications related to your uploaded resources.</p>
                    
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="resource_approved" name="resource_approved" 
                               <?= $settings['resource_approved'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="resource_approved">
                            Resource Approval
                            <small class="text-muted d-block">Get notified when your uploaded resources are approved</small>
                        </label>
                    </div>
                    
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="resource_rejected" name="resource_rejected" 
                               <?= $settings['resource_rejected'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="resource_rejected">
                            Resource Rejection
                            <small class="text-muted d-block">Get notified when your uploaded resources are rejected</small>
                        </label>
                    </div>
                    
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="resource_comment" name="resource_comment" 
                               <?= $settings['resource_comment'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="resource_comment">
                            Resource Comments
                            <small class="text-muted d-block">Get notified when someone comments on your resources</small>
                        </label>
                    </div>
                    
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="resource_rating" name="resource_rating" 
                               <?= $settings['resource_rating'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="resource_rating">
                            Resource Ratings
                            <small class="text-muted d-block">Get notified when someone rates your resources</small>
                        </label>
                    </div>
                    
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="resource_download" name="resource_download" 
                               <?= $settings['resource_download'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="resource_download">
                            Resource Downloads
                            <small class="text-muted d-block">Get notified when your resources reach download milestones</small>
                        </label>
                    </div>
                </div>
                
                <div class="mb-4">
                    <h5 class="font-weight-bold">Achievement Notifications</h5>
                    <p class="text-muted small">Control notifications related to your achievements on the platform.</p>
                    
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="badge_earned" name="badge_earned" 
                               <?= $settings['badge_earned'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="badge_earned">
                            Badge Earned
                            <small class="text-muted d-block">Get notified when you earn a new badge</small>
                        </label>
                    </div>
                </div>
                
                <div class="mb-4">
                    <h5 class="font-weight-bold">Delivery Preferences</h5>
                    <p class="text-muted small">Control how you receive notifications.</p>
                    
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" 
                               <?= $settings['email_notifications'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="email_notifications">
                            Email Notifications
                            <small class="text-muted d-block">Receive notifications via email in addition to the platform</small>
                        </label>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Notification Info Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">About Notifications</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="text-center mb-2">
                        <i class="fas fa-bell fa-2x text-primary"></i>
                    </div>
                    <h5 class="text-center font-weight-bold">Platform Notifications</h5>
                    <p class="text-center small text-gray-600">
                        These notifications appear in your notifications panel on the PolyEduHub platform.
                    </p>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="text-center mb-2">
                        <i class="fas fa-envelope fa-2x text-primary"></i>
                    </div>
                    <h5 class="text-center font-weight-bold">Email Notifications</h5>
                    <p class="text-center small text-gray-600">
                        If enabled, you'll also receive email notifications for important updates.
                    </p>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="text-center mb-2">
                        <i class="fas fa-user-cog fa-2x text-primary"></i>
                    </div>
                    <h5 class="text-center font-weight-bold">Personalized Experience</h5>
                    <p class="text-center small text-gray-600">
                        Customize your notification preferences to focus on what matters most to you.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>