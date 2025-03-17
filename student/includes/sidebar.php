<!-- Student Sidebar -->
<div class="sidebar col-md-3 col-lg-2 d-md-block bg-light sidebar">
    <div class="position-sticky pt-3">
        <div class="sidebar-brand text-center mb-4">
            <a href="dashboard.php" class="navbar-brand">
                <img src="../assets/img/polyeduhub-logo.png" alt="PolyEduHub Logo" height="40">
                Student Portal
            </a>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'resources') !== false ? 'active' : '' ?>" href="resources/index.php">
                    <i class="fas fa-folder"></i> Resources
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'upload.php') !== false ? 'active' : '' ?>" href="resources/upload.php">
                    <i class="fas fa-upload"></i> Upload Resource
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'my-resources.php') !== false ? 'active' : '' ?>" href="resources/my-resources.php">
                    <i class="fas fa-list"></i> My Resources
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'leaderboard') !== false ? 'active' : '' ?>" href="leaderboard/index.php">
                    <i class="fas fa-trophy"></i> Leaderboard
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'profile') !== false ? 'active' : '' ?>" href="profile/index.php">
                    <i class="fas fa-user"></i> Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'chat') !== false ? 'active' : '' ?>" href="chat/index.php">
                    <i class="fas fa-comments"></i> Chat Rooms
                </a>
            </li>

            <hr class="sidebar-divider">

            <li class="nav-item">
                <a class="nav-link text-danger" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>
</div>

<script>
    // Optional: Toggle sidebar on smaller screens
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('show');
            });
        }
    });
</script>