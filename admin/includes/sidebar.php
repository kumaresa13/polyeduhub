<?php
/**
 * Admin Sidebar
 * Place this file in: polyeduhub/admin/includes/sidebar.php
 */

// Get pending resources count for notification badge
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM resources WHERE status = 'pending'");
    $stmt->execute();
    $pending_resources = $stmt->fetchColumn();
} catch (Exception $e) {
    error_log("Failed to get pending resources count: " . $e->getMessage());
    $pending_resources = 0;
}

// Determine current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$current_directory = basename(dirname($_SERVER['PHP_SELF']));

// Function to determine if a menu item should be active
function isActive($page_name, $directory = null) {
    global $current_page, $current_directory;
    
    if ($directory !== null) {
        return $current_directory === $directory;
    }
    
    return $current_page === $page_name;
}
?>

<!-- Sidebar -->
<div class="sidebar bg-gradient-primary text-white">
    <div class="sidebar-heading text-center py-4">
        <i class="fas fa-graduation-cap fs-2 mb-2"></i>
        <h4 class="mb-0">PolyEduHub Admin</h4>
    </div>
    
    <hr class="sidebar-divider">
    
    <!-- Nav Item - Dashboard -->
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link text-white <?= isActive('dashboard.php') ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/dashboard.php">
                <i class="fas fa-fw fa-tachometer-alt me-2"></i> Dashboard
            </a>
        </li>
    </ul>
    
    <hr class="sidebar-divider">
    
    <!-- Heading -->
    <div class="sidebar-category">
        <span class="text-uppercase px-3 small">Resource Management</span>
    </div>
    
    <!-- Nav Items - Resource Management -->
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link text-white <?= isActive('index.php', 'resources') ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/resources/index.php">
                <i class="fas fa-fw fa-folder me-2"></i> All Resources
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?= isActive('approve.php', 'resources') ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/resources/approve.php">
                <i class="fas fa-fw fa-check-circle me-2"></i> 
                <span>Approve Resources</span>
                <?php if ($pending_resources > 0): ?>
                <span class="badge bg-danger ms-1"><?= $pending_resources ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?= isActive('categories.php', 'resources') ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/resources/categories.php">
                <i class="fas fa-fw fa-list me-2"></i> Categories
            </a>
        </li>
    </ul>
    
    <hr class="sidebar-divider">
    
    <!-- Heading -->
    <div class="sidebar-category">
        <span class="text-uppercase px-3 small">User Management</span>
    </div>
    
    <!-- Nav Items - User Management -->
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link text-white <?= isActive('index.php', 'users') ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/users/index.php">
                <i class="fas fa-fw fa-users me-2"></i> All Users
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?= isActive('edit.php', 'users') ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/users/edit.php">
                <i class="fas fa-fw fa-user-edit me-2"></i> Edit User
            </a>
        </li>
    </ul>
    
    <hr class="sidebar-divider">
    
    <!-- Heading -->
    <div class="sidebar-category">
        <span class="text-uppercase px-3 small">Gamification</span>
    </div>
    
    <!-- Nav Items - Gamification -->
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link text-white <?= isActive('badges.php', 'gamification') ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/gamification/badges.php">
                <i class="fas fa-fw fa-award me-2"></i> Badges
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?= isActive('points.php', 'gamification') ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/gamification/points.php">
                <i class="fas fa-fw fa-star me-2"></i> Points System
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?= isActive('leaderboard.php', 'gamification') ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/gamification/leaderboard.php">
                <i class="fas fa-fw fa-trophy me-2"></i> Leaderboard
            </a>
        </li>
    </ul>
    
    <hr class="sidebar-divider">
    
    <!-- Heading -->
    <div class="sidebar-category">
        <span class="text-uppercase px-3 small">Community</span>
    </div>
    
    <!-- Nav Items - Community -->
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link text-white <?= isActive('rooms.php', 'chat') ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/chat/rooms.php">
                <i class="fas fa-fw fa-comments me-2"></i> Chat Rooms
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?= isActive('activity.php', 'reports') ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/reports/activity.php">
                <i class="fas fa-fw fa-chart-area me-2"></i> Activity Reports
            </a>
        </li>
    </ul>
    
    <hr class="sidebar-divider">
    
    <!-- Heading -->
    <div class="sidebar-category">
        <span class="text-uppercase px-3 small">System</span>
    </div>
    
    <!-- Nav Items - System -->
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link text-white <?= isActive('general.php', 'settings') ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/settings/general.php">
                <i class="fas fa-fw fa-cog me-2"></i> Settings
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white" href="<?= APP_URL ?>/logout.php">
                <i class="fas fa-fw fa-sign-out-alt me-2"></i> Logout
            </a>
        </li>
    </ul>
    
    <hr class="sidebar-divider">
    
    <!-- Sidebar Toggler -->
    <div class="text-center d-none d-md-inline mt-3">
        <button class="btn btn-sm btn-circle bg-white text-primary" id="sidebarToggle">
            <i class="fas fa-angle-left"></i>
        </button>
    </div>
</div>