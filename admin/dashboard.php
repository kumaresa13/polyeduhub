<?php
// Start session and include necessary files
session_start();

// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db-connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if admin is logged in
checkAdminLogin();

// Get admin information from session
$admin_id = $_SESSION['id'];
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];
$email = $_SESSION['email'];

// Initialize database connection
try {
    $pdo = getDbConnection();
    
    // Get total users count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'student'");
    $stmt->execute();
    $total_students = $stmt->fetchColumn();
    
    // Get total resources count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM resources");
    $stmt->execute();
    $total_resources = $stmt->fetchColumn();
    
    // Get pending resources count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM resources WHERE status = 'pending'");
    $stmt->execute();
    $pending_resources = $stmt->fetchColumn();
    
    // Get total downloads count
    $stmt = $pdo->prepare("SELECT SUM(download_count) FROM resources");
    $stmt->execute();
    $total_downloads = $stmt->fetchColumn() ?: 0;
    
    // Get recent resources for approval
    $stmt = $pdo->prepare("
        SELECT r.id, r.title, r.created_at, u.first_name, u.last_name, rc.name as category
        FROM resources r
        JOIN users u ON r.user_id = u.id
        JOIN resource_categories rc ON r.category_id = rc.id
        WHERE r.status = 'pending'
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $pending_resource_list = $stmt->fetchAll();
    
    // Get recent users
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, email, created_at
        FROM users
        WHERE role = 'student'
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_users = $stmt->fetchAll();
    
    // Get recent activity
    $stmt = $pdo->prepare("
        SELECT al.action, al.details, al.created_at, u.first_name, u.last_name
        FROM activity_log al
        JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_activity = $stmt->fetchAll();
    
    // Get resource category distribution
    $stmt = $pdo->prepare("
        SELECT rc.name, COUNT(r.id) as count
        FROM resource_categories rc
        LEFT JOIN resources r ON rc.id = r.category_id
        GROUP BY rc.id
        ORDER BY count DESC
    ");
    $stmt->execute();
    $category_stats = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $error_message = "There was a problem loading the dashboard data.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="PolyEduHub Admin Dashboard">
    <meta name="author" content="PolyEduHub Team">
    <title>PolyEduHub - Admin Dashboard</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon.png">
    
    <!-- Custom fonts -->
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom Admin CSS -->
    <link href="../assets/css/admin.css" rel="stylesheet">

</head>

<body id="page-top">

    <!-- Page Wrapper -->
    <div id="wrapper">

        <!-- Sidebar -->
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

            <!-- Sidebar - Brand -->
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="dashboard.php">
                <div class="sidebar-brand-icon rotate-n-15">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="sidebar-brand-text mx-3">PolyEduHub Admin</div>
            </a>

            <!-- Divider -->
            <hr class="sidebar-divider my-0">

            <!-- Nav Item - Dashboard -->
            <li class="nav-item active">
                <a class="nav-link" href="dashboard.php">
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
            <li class="nav-item">
                <a class="nav-link" href="resources/index.php">
                    <i class="fas fa-fw fa-folder"></i>
                    <span>All Resources</span>
                </a>
            </li>

            <!-- Nav Item - Approve Resources -->
            <li class="nav-item">
                <a class="nav-link" href="resources/approve.php">
                    <i class="fas fa-fw fa-check-circle"></i>
                    <span>Approve Resources</span>
                    <?php if ($pending_resources > 0): ?>
                    <span class="badge badge-danger badge-counter"><?= $pending_resources ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <!-- Nav Item - Categories -->
            <li class="nav-item">
                <a class="nav-link" href="resources/categories.php">
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
            <li class="nav-item">
                <a class="nav-link" href="users/index.php">
                    <i class="fas fa-fw fa-users"></i>
                    <span>All Users</span>
                </a>
            </li>

            <!-- Nav Item - Edit User -->
            <li class="nav-item">
                <a class="nav-link" href="users/edit.php">
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
            <li class="nav-item">
                <a class="nav-link" href="gamification/badges.php">
                    <i class="fas fa-fw fa-award"></i>
                    <span>Badges</span>
                </a>
            </li>

            <!-- Nav Item - Points -->
            <li class="nav-item">
                <a class="nav-link" href="gamification/points.php">
                    <i class="fas fa-fw fa-star"></i>
                    <span>Points System</span>
                </a>
            </li>

            <!-- Nav Item - Leaderboard -->
            <li class="nav-item">
                <a class="nav-link" href="gamification/leaderboard.php">
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
            <li class="nav-item">
                <a class="nav-link" href="chat/rooms.php">
                    <i class="fas fa-fw fa-comments"></i>
                    <span>Chat Rooms</span>
                </a>
            </li>

            <!-- Nav Item - Reports -->
            <li class="nav-item">
                <a class="nav-link" href="reports/activity.php">
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
            <li class="nav-item">
                <a class="nav-link" href="settings/general.php">
                    <i class="fas fa-fw fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
            
            <!-- Nav Item - Logout -->
            <li class="nav-item">
                <a class="nav-link" href="../logout.php">
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

        </ul>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">

                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

                    <!-- Sidebar Toggle (Topbar) -->
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>

                    <!-- Topbar Search -->
                    <form class="d-none d-sm-inline-block form-inline mr-auto ml-md-3 my-2 my-md-0 mw-100 navbar-search">
                        <div class="input-group">
                            <input type="text" class="form-control bg-light border-0 small" placeholder="Search for..." aria-label="Search" aria-describedby="basic-addon2">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="button">
                                    <i class="fas fa-search fa-sm"></i>
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Topbar Navbar -->
                    <ul class="navbar-nav ml-auto">

                        <!-- Nav Item - Search Dropdown (Visible Only XS) -->
                        <li class="nav-item dropdown no-arrow d-sm-none">
                            <a class="nav-link dropdown-toggle" href="#" id="searchDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-search fa-fw"></i>
                            </a>
                            <!-- Dropdown - Messages -->
                            <div class="dropdown-menu dropdown-menu-right p-3 shadow animated--grow-in" aria-labelledby="searchDropdown">
                                <form class="form-inline mr-auto w-100 navbar-search">
                                    <div class="input-group">
                                        <input type="text" class="form-control bg-light border-0 small" placeholder="Search for..." aria-label="Search" aria-describedby="basic-addon2">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="button">
                                                <i class="fas fa-search fa-sm"></i>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </li>

                        <!-- Nav Item - Alerts -->
                        <li class="nav-item dropdown no-arrow mx-1">
                            <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell fa-fw"></i>
                                <!-- Counter - Alerts -->
                                <span class="badge badge-danger badge-counter"><?= $pending_resources ?></span>
                            </a>
                            <!-- Dropdown - Alerts -->
                            <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="alertsDropdown">
                                <h6 class="dropdown-header">
                                    Alerts Center
                                </h6>
                                <a class="dropdown-item d-flex align-items-center" href="resources/approve.php">
                                    <div class="mr-3">
                                        <div class="icon-circle bg-primary">
                                            <i class="fas fa-file-alt text-white"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="small text-gray-500">Now</div>
                                        <span class="font-weight-bold"><?= $pending_resources ?> resources waiting for approval!</span>
                                    </div>
                                </a>
                                <a class="dropdown-item text-center small text-gray-500" href="resources/approve.php">Show All Alerts</a>
                            </div>
                        </li>

                        <div class="topbar-divider d-none d-sm-block"></div>

                        <!-- Nav Item - User Information -->
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?= htmlspecialchars($first_name . ' ' . $last_name) ?></span>
                                <img class="img-profile rounded-circle" src="../assets/img/ui/default-profile.png">
                            </a>
                            <!-- Dropdown - User Information -->
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="settings/profile.php">
                                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Profile
                                </a>
                                <a class="dropdown-item" href="settings/general.php">
                                    <i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Settings
                                </a>
                                <a class="dropdown-item" href="reports/activity.php">
                                    <i class="fas fa-list fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Activity Log
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="../logout.php">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Logout
                                </a>
                            </div>
                        </li>

                    </ul>

                </nav>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">

                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Admin Dashboard</h1>
                        <a href="reports/generate.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                            <i class="fas fa-download fa-sm text-white-50"></i> Generate Report
                        </a>
                    </div>

                    <!-- Content Row -->
                    <div class="row">

                        <!-- Total Students Card -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Students</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_students) ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-users fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Total Resources Card -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Resources</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_resources) ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-folder fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Requests Card -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                Pending Resources</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $pending_resources ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Total Downloads Card -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                Downloads</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_downloads) ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-download fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Content Row -->
                    <div class="row">

                        <!-- Resources Pending Approval -->
                        <div class="col-xl-8 col-lg-7">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">Resources Pending Approval</h6>
                                    <a href="resources/approve.php" class="btn btn-sm btn-primary">View All</a>
                                </div>
                                <!-- Card Body -->
                                <div class="card-body">
                                    <?php if (empty($pending_resource_list)): ?>
                                        <div class="text-center py-4 text-muted">
                                            <i class="fas fa-check-circle fa-3x mb-3"></i>
                                            <p>No resources pending approval at this time.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-bordered" width="100%" cellspacing="0">
                                                <thead>
                                                    <tr>
                                                        <th>Title</th>
                                                        <th>Category</th>
                                                        <th>Uploaded By</th>
                                                        <th>Date</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($pending_resource_list as $resource): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($resource['title']) ?></td>
                                                        <td><?= htmlspecialchars($resource['category']) ?></td>
                                                        <td><?= htmlspecialchars($resource['first_name'] . ' ' . $resource['last_name']) ?></td>
                                                        <td><?= date('M d, Y', strtotime($resource['created_at'])) ?></td>
                                                        <td>
                                                            <a href="resources/view.php?id=<?= $resource['id'] ?>" class="btn btn-sm btn-info">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="resources/approve.php?id=<?= $resource['id'] ?>&action=approve" class="btn btn-sm btn-success">
                                                                <i class="fas fa-check"></i>
                                                            </a>
                                                            <a href="resources/approve.php?id=<?= $resource['id'] ?>&action=reject" class="btn btn-sm btn-danger">
                                                                <i class="fas fa-times"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Resource Categories Pie Chart -->
                        <div class="col-xl-4 col-lg-5">
                            <div class="card shadow mb-4">
                                <!-- Card Header - Dropdown -->
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">Resources by Category</h6>
                                </div>
                                <!-- Card Body -->
                                <div class="card-body">
                                    <div class="chart-pie pt-4 pb-2">
                                        <canvas id="resourceCategoriesChart"></canvas>
                                    </div>
                                    <div class="mt-4 text-center small">
                                        <?php foreach ($category_stats as $index => $category): ?>
                                        <span class="mr-2">
                                            <i class="fas fa-circle" style="color: <?= getChartColor($index) ?>"></i> <?= htmlspecialchars($category['name']) ?>
                                        </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Content Row -->
                    <div class="row">

                        <!-- Recent Users -->
                        <div class="col-lg-6 mb-4">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Recent Users</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered" width="100%" cellspacing="0">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Joined</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_users as $user): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                                    <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                                    <td>
                                                        <a href="users/edit.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-center mt-3">
                                        <a href="users/index.php" class="btn btn-sm btn-primary">View All Users</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="col-lg-6 mb-4">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Recent Activity</h6>
                                </div>
                                <div class="card-body">
                                    <div class="activity-feed">
                                        <?php foreach ($recent_activity as $activity): ?>
                                        <div class="activity-item d-flex">
                                            <div class="activity-icon bg-primary text-white">
                                                <i class="fas fa-history"></i>
                                            </div>
                                            <div class="activity-content">
                                                <div class="activity-header">
                                                    <strong><?= htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']) ?></strong> 
                                                    <?= htmlspecialchars($activity['action']) ?>
                                                </div>
                                                <div class="activity-detail text-muted">
                                                    <?= htmlspecialchars($activity['details']) ?>
                                                </div>
                                                <div class="activity-time small text-muted">
                                                    <?= time_elapsed_string($activity['created_at']) ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="text-center mt-3">
                                        <a href="reports/activity.php" class="btn btn-sm btn-primary">View All Activity</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- /.container-fluid -->

            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; PolyEduHub <?= date('Y') ?></span>
                    </div>
                </div>
            </footer>
            <!-- End of Footer -->

        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom Admin JavaScript -->
    <script>
        // Toggle the side navigation
        document.getElementById('sidebarToggle').addEventListener('click', function(e) {
            document.body.classList.toggle('sidebar-toggled');
            document.querySelector('.sidebar').classList.toggle('toggled');
        });
        
        // Close sidebar on small screens
        window.addEventListener('resize', function() {
            if (window.innerWidth < 768) {
                document.querySelector('.sidebar').classList.add('toggled');
            }
        });
        
        // Chart.js - Resource Categories
        var ctx = document.getElementById("resourceCategoriesChart");
        var myPieChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php foreach ($category_stats as $category): ?>
                        "<?= htmlspecialchars($category['name']) ?>",
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($category_stats as $category): ?>
                            <?= $category['count'] ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        <?php 
                        foreach ($category_stats as $index => $category): 
                            echo "'" . getChartColor($index) . "',";
                        endforeach; 
                        ?>
                    ],
                    hoverBackgroundColor: [
                        <?php 
                        foreach ($category_stats as $index => $category): 
                            echo "'" . getChartColor($index, true) . "',";
                        endforeach; 
                        ?>
                    ],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                maintainAspectRatio: false,
                tooltips: {
                    backgroundColor: "rgb(255,255,255)",
                    bodyFontColor: "#858796",
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    xPadding: 15,
                    yPadding: 15,
                    displayColors: false,
                    caretPadding: 10,
                },
                legend: {
                    display: false
                },
                cutoutPercentage: 80,
            },
        });
    </script>

</body>

</html>