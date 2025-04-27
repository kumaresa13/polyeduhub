<!-- Place this file in: polyeduhub/student/includes/sidebar.php -->
<?php
// Calculate the proper base path for links
$current_path = $_SERVER['PHP_SELF'];
$path_parts = explode('/', $current_path);
$student_index = array_search('student', $path_parts);

// Calculate depth after student directory
$depth = count($path_parts) - $student_index - 2; // -2 accounts for student dir and file name
$base_path = '';
if ($depth > 0) {
    $base_path = str_repeat('../', $depth);
}
?>

<div class="sidebar bg-primary text-white">
    <div class="sidebar-heading text-center py-4">
        <i class="fas fa-graduation-cap fs-2 mb-2"></i>
        <h4 class="mb-0">PolyEduHub</h4>
    </div>

    <div class="sidebar-category mt-4">
        <span class="text-uppercase px-3 small">NAVIGATION</span>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link text-white <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>"
                href="<?= $base_path ?>dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
        </li>
    </ul>

    <div class="sidebar-category mt-3">
        <span class="text-uppercase px-3 small">RESOURCES</span>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link text-white <?= strpos($_SERVER['PHP_SELF'], '/resources/index.php') !== false ? 'active' : '' ?>"
                href="<?= $base_path ?>resources/index.php">
                <i class="fas fa-fw fa-folder me-2"></i>
                <span>Browse Resources</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?= strpos($_SERVER['PHP_SELF'], '/resources/upload.php') !== false ? 'active' : '' ?>"
                href="<?= $base_path ?>resources/upload.php">
                <i class="fas fa-fw fa-upload me-2"></i>
                <span>Upload Resource</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?= strpos($_SERVER['PHP_SELF'], '/resources/my-resources.php') !== false ? 'active' : '' ?>"
                href="<?= $base_path ?>resources/my-resources.php">
                <i class="fas fa-fw fa-list me-2"></i>
                <span>My Resources</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?= strpos($_SERVER['PHP_SELF'], '/resources/favorites.php') !== false ? 'active' : '' ?>"
                href="<?= $base_path ?>resources/favorites.php">
                <i class="fas fa-fw fa-star me-2"></i>
                <span>Favorites</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-category mt-3">
        <span class="text-uppercase px-3 small">COMMUNITY</span>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link text-white <?= strpos($_SERVER['PHP_SELF'], '/chat/') !== false ? 'active' : '' ?>"
                href="<?= $base_path ?>chat/index.php">
                <i class="fas fa-comments me-2"></i> Chat Rooms
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?= strpos($_SERVER['PHP_SELF'], '/leaderboard/') !== false ? 'active' : '' ?>"
                href="<?= $base_path ?>leaderboard/index.php">
                <i class="fas fa-trophy me-2"></i> Leaderboard
            </a>
        </li>
    </ul>

    <div class="sidebar-category mt-3">
        <span class="text-uppercase px-3 small">ACCOUNT</span>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link text-white <?= strpos($_SERVER['PHP_SELF'], '/profile/index.php') !== false ? 'active' : '' ?>"
                href="<?= $base_path ?>profile/index.php">
                <i class="fas fa-user me-2"></i> My Profile
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?= strpos($_SERVER['PHP_SELF'], '/profile/badges.php') !== false ? 'active' : '' ?>"
                href="<?= $base_path ?>profile/badges.php">
                <i class="fas fa-award me-2"></i> My Badges
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?= strpos($_SERVER['PHP_SELF'], '/notifications/') !== false ? 'active' : '' ?>"
                href="<?= $base_path ?>notifications/index.php">
                <i class="fas fa-bell me-2"></i> Notifications
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white" href="<?= $base_path ?>../logout.php">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </li>
    </ul>
</div>