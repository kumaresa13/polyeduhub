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
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= APP_URL ?>/admin/dashboard.php">
        <div class="sidebar-brand-icon rotate-n-15">
            <i class="fas fa-graduation-cap"></i>
        </div>
        <div class="sidebar-brand-text mx-3">PolyEduHub Admin</div>
    </a>

    <!-- Divider -->
    <hr class="sidebar-divider my-0">

    <!-- Nav Item - Dashboard -->
    <li class="nav-item <?= isActive('dashboard.php') ? 'active' : '' ?>">
        <a class="nav-link" href="<?= APP_URL ?>/admin/dashboard.php">
            <i class="fas fa-fw fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading -->
    <div class="sidebar-heading">
        Resource Management
    </div>

    <!-- Nav Item - Resources -->
    <li class="nav-item <?= isActive('index.php', 'resources') ? 'active' : '' ?>">
        <a class="nav-link" href="<?= APP_URL ?>/admin/resources/index.php">
            <i class="fas fa-fw fa-folder"></i>
            <span>All Resources</span>
        </a>
    </li>

    <!-- Nav Item - Approve Resources -->
    <li class="nav-item <?= isActive('approve.php', 'resources') ? 'active' : '' ?>">
        <a class="nav-link" href="<?= APP_URL ?>/admin/resources/approve.php">
            <i class="fas fa-fw fa-check-circle"></i>
            <span>Approve Resources</span>
            <?php if ($pending_resources > 0): ?>
            <span class="badge badge-danger badge-counter"><?= $pending_resources ?></span>
            <?php endif; ?>
        </a>
    </li>

    <!-- Nav Item - Categories -->
    <li class="nav-item <?= isActive('categories.php', 'resources') ? 'active' : '' ?>">
        <a class="nav-link" href="<?= APP_URL ?>/admin/resources/categories.php">
            <i class="fas fa-fw fa-list"></i>
            <span>Categories</span>
        </a>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading -->
    <div class="sidebar-heading">
        User Management
    </div>

    <!-- Nav Item - Users -->
    <li class="nav-item <?= isActive('index.php', 'users') ? 'active' : '' ?>">
        <a class="nav-link" href="<?= APP_URL ?>/admin/users/index.php">
            <i class="fas fa-fw fa-users"></i>
            <span>All Users</span>
        </a>
    </li>

    <!-- Nav Item - Edit User -->
    <li class="nav-item <?= isActive('edit.php', 'users') ? 'active' : '' ?>">
        <a class="nav-link" href="<?= APP_URL ?>/admin/users/edit.php">
            <i class="fas fa-fw fa-user-edit"></i>
            <span>Edit User</span>
        </a>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading -->
    <div class="sidebar-heading">
        Gamification
    </div>

    <!-- Nav Item - Badges -->
    <li class="nav-item <?= isActive('badges.php', 'gamification') ? 'active' : '' ?>">
        <a class="nav-link" href="<?= APP_URL ?>/admin/gamification/badges.php">
            <i class="fas fa-fw fa-award"></i>
            <span>Badges</span>
        </a>
    </li>

    <!-- Nav Item - Points -->
    <li class="nav-item <?= isActive('points.php', 'gamification') ? 'active' : '' ?>">
        <a class="nav-link" href="<?= APP_URL ?>/admin/gamification/points.php">
            <i class="fas fa-fw fa-star"></i>
            <span>Points System</span>
        </a>
    </li>

    <!-- Nav Item - Leaderboard -->
    <li class="nav-item <?= isActive('leaderboard.php', 'gamification') ? 'active' : '' ?>">
        <a class="nav-link" href="<?= APP_URL ?>/admin/gamification/leaderboard.php">
            <i class="fas fa-fw fa-trophy"></i>
            <span>Leaderboard</span>
        </a>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading -->
    <div class="sidebar-heading">
        Community
    </div>

    <!-- Nav Item - Chat Rooms -->
    <li class="nav-item <?= isActive('rooms.php', 'chat') ? 'active' : '' ?>">
        <a class="nav-link" href="<?= APP_URL ?>/admin/chat/rooms.php">
            <i class="fas fa-fw fa-comments"></i>
            <span>Chat Rooms</span>
        </a>
    </li>

    <!-- Nav Item - Reports -->
    <li class="nav-item <?= isActive('activity.php', 'reports') ? 'active' : '' ?>">
        <a class="nav-link" href="<?= APP_URL ?>/admin/reports/activity.php">
            <i class="fas fa-fw fa-chart-area"></i>
            <span>Activity Reports</span>
        </a>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading -->
    <div class="sidebar-heading">
        System
    </div>

    <!-- Nav Item - Settings -->
    <li class="nav-item <?= isActive('general.php', 'settings') ? 'active' : '' ?>">
        <a class="nav-link" href="<?= APP_URL ?>/admin/settings/general.php">
            <i class="fas fa-fw fa-cog"></i>
            <span>Settings</span>
        </a>
    </li>
    
    <!-- Nav Item - Logout -->
    <li class="nav-item">
        <a class="nav-link" href="<?= APP_URL ?>/logout.php">
            <i class="fas fa-fw fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider d-none d-md-block">

    <!-- Sidebar Toggler (Sidebar) -->
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"><i class="fas fa-angle-left"></i></button>
    </div>