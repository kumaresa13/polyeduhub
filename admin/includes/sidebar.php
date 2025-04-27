<div class="sidebar">
    <div class="text-center py-4">
        <div class="d-flex align-items-center justify-content-center mb-1">
            <i class="fas fa-graduation-cap fa-2x"></i>
        </div>
        <h6 class="m-0 font-weight-bold">POLYEDUHUB</h6>
        <div class="small">ADMIN PANEL</div>
    </div>
    
    <hr class="sidebar-divider my-0">
    
    <div class="sidebar-heading">DASHBOARD</div>
    
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>" 
               href="<?= isset($nested) ? '../' : '' ?>dashboard.php">
                <i class="fas fa-fw fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>
    </ul>
    
    <hr class="sidebar-divider mt-2 mb-0">
    
    <div class="sidebar-heading">RESOURCES</div>
    
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], '/resources/') !== false ? 'active' : '' ?>" 
               href="<?= isset($nested) ? '../resources/' : 'resources/' ?>index.php">
                <i class="fas fa-fw fa-folder"></i>
                <span>All Resources</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'approve.php' ? 'active' : '' ?>" 
               href="<?= isset($nested) ? '../resources/' : 'resources/' ?>approve.php">
                <i class="fas fa-fw fa-check-circle"></i>
                <span>Approve Resources</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : '' ?>" 
               href="<?= isset($nested) ? '../resources/' : 'resources/' ?>categories.php">
                <i class="fas fa-fw fa-tags"></i>
                <span>Categories</span>
            </a>
        </li>
    </ul>
    
    <hr class="sidebar-divider mt-2 mb-0">
    
    <div class="sidebar-heading">USERS</div>
    
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], '/users/') !== false ? 'active' : '' ?>" 
               href="<?= isset($nested) ? '../users/' : 'users/' ?>index.php">
                <i class="fas fa-fw fa-users"></i>
                <span>Manage Users</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'permissions.php' ? 'active' : '' ?>" 
               href="<?= isset($nested) ? '../users/' : 'users/' ?>permissions.php">
                <i class="fas fa-fw fa-user-shield"></i>
                <span>Permissions</span>
            </a>
        </li>
    </ul>
    
    <hr class="sidebar-divider mt-2 mb-0">
    
    <div class="sidebar-heading">COMMUNITY</div>
    
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'rooms.php' ? 'active' : '' ?>" 
               href="<?= isset($nested) ? '../chat/' : 'chat/' ?>rooms.php">
                <i class="fas fa-fw fa-comments"></i>
                <span>Chat Rooms</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>" 
               href="<?= isset($nested) ? '../chat/' : 'chat/' ?>reports.php">
                <i class="fas fa-fw fa-flag"></i>
                <span>Reported Messages</span>
            </a>
        </li>
    </ul>
    
    <hr class="sidebar-divider mt-2 mb-0">
    
    <div class="sidebar-heading">GAMIFICATION</div>
    
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'badges.php' ? 'active' : '' ?>" 
               href="<?= isset($nested) ? '../gamification/' : 'gamification/' ?>badges.php">
                <i class="fas fa-fw fa-award"></i>
                <span>Badges</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'points.php' ? 'active' : '' ?>" 
               href="<?= isset($nested) ? '../gamification/' : 'gamification/' ?>points.php">
                <i class="fas fa-fw fa-star"></i>
                <span>Points System</span>
            </a>
        </li>
    </ul>
    
    <hr class="sidebar-divider mt-2 mb-0">
    
    <div class="sidebar-heading">REPORTS</div>
    
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'user-statistics.php' ? 'active' : '' ?>" 
               href="<?= isset($nested) ? '../reports/' : 'reports/' ?>user-statistics.php">
                <i class="fas fa-fw fa-user-chart"></i>
                <span>User Statistics</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'resource-statistics.php' ? 'active' : '' ?>" 
               href="<?= isset($nested) ? '../reports/' : 'reports/' ?>resource-statistics.php">
                <i class="fas fa-fw fa-file-alt"></i>
                <span>Resource Statistics</span>
            </a>
        </li>
    </ul>
    
    
    <hr class="sidebar-divider mt-2 mb-0">
    
    <div class="sidebar-heading">ACCOUNT</div>
    
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link" href="<?= $base_path ?>logout.php">
                <i class="fas fa-fw fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
    
    <div class="py-2"></div>
</div>