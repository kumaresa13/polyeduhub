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

// Mark all notifications as read if requested
if (isset($_GET['mark_all_read']) && $_GET['mark_all_read'] == 1) {
    markNotificationsAsRead($user_id);
    
    // Redirect to remove the query parameter
    header("Location: index.php");
    exit();
}

// Mark specific notification as read if requested
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    markNotificationsAsRead($user_id, intval($_GET['mark_read']));
    
    // Redirect to the referenced link if available
    if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
        header("Location: " . $_GET['redirect']);
        exit();
    }
    
    // Otherwise redirect to the notifications page
    header("Location: index.php");
    exit();
}

// Get filter parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Get notifications with pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query based on filter
$where_clause = "WHERE user_id = ?";
$params = [$user_id];

if ($filter === 'unread') {
    $where_clause .= " AND is_read = 0";
}

try {
    $pdo = getDbConnection();
    
    // Check if notifications table exists
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
        AND table_name = 'notifications'
    ");
    $stmt->execute();
    $table_exists = $stmt->fetchColumn() > 0;
    
    // If table doesn't exist, create it
    if (!$table_exists) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `notifications` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `message` varchar(255) NOT NULL,
                `link` varchar(255) DEFAULT NULL,
                `is_read` tinyint(1) NOT NULL DEFAULT 0,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
    
    // Get total count for pagination
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications $where_clause");
    $stmt->execute($params);
    $total_notifications = $stmt->fetchColumn();
    $total_pages = ceil($total_notifications / $limit);
    
    // Get unread count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetchColumn();
    
    // Get notifications
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        $where_clause 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error in notifications page: " . $e->getMessage());
    $notifications = [];
    $unread_count = 0;
    $total_notifications = 0;
    $total_pages = 0;
}

// Page title
$page_title = "Notifications";
$nested = true;

// Include header
include_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Notifications</h1>
        <div>
            <?php if ($unread_count > 0): ?>
            <a href="?mark_all_read=1" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm me-2">
                <i class="fas fa-check-double fa-sm text-white-50"></i> Mark All as Read
            </a>
            <?php endif; ?>
            <a href="settings.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-cog fa-sm text-white-50"></i> Notification Settings
            </a>
        </div>
    </div>
    
    <!-- Notification Filters -->
    <div class="card shadow mb-4">
        <div class="card-body py-3">
            <div class="nav nav-pills">
                <a class="nav-link <?= $filter === 'all' ? 'active' : '' ?>" href="index.php">
                    All <span class="badge bg-secondary"><?= $total_notifications ?></span>
                </a>
                <a class="nav-link <?= $filter === 'unread' ? 'active' : '' ?>" href="?filter=unread">
                    Unread <span class="badge bg-primary"><?= $unread_count ?></span>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Notifications List -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <?php if (empty($notifications)): ?>
            <div class="text-center py-5">
                <i class="fas fa-bell fa-4x text-gray-300 mb-3"></i>
                <p class="text-gray-600 mb-0">No notifications found.</p>
                <p class="text-gray-600">
                    <?php if ($filter === 'unread'): ?>
                    You have no unread notifications.
                    <?php else: ?>
                    You'll see notifications about your activities and interactions here.
                    <?php endif; ?>
                </p>
            </div>
            <?php else: ?>
            <div class="list-group">
                <?php foreach ($notifications as $notification): ?>
                <div class="list-group-item list-group-item-action <?= $notification['is_read'] ? '' : 'bg-light' ?>">
                    <div class="d-flex w-100 justify-content-between align-items-center">
                        <div>
                            <div class="d-flex align-items-center">
                                <i class="fas fa-bell text-primary me-2"></i>
                                <h6 class="mb-1"><?= htmlspecialchars($notification['message']) ?></h6>
                                <?php if (!$notification['is_read']): ?>
                                <span class="badge bg-primary ms-2">New</span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted"><?= time_elapsed_string($notification['created_at']) ?></small>
                        </div>
                        <div class="btn-group">
                            <?php if (!empty($notification['link'])): ?>
                            <a href="?mark_read=<?= $notification['id'] ?>&redirect=<?= urlencode($notification['link']) ?>" 
                               class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (!$notification['is_read']): ?>
                            <a href="?mark_read=<?= $notification['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-check"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center mt-4">
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&filter=<?= $filter ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&filter=<?= $filter ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&filter=<?= $filter ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>