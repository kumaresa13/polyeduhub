<?php
// Start session and include necessary files
session_start();

// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db-connection.php';
require_once '../includes/functions.php';

// Robust session and authentication check
function checkStudentAuthentication() {
    // Check if user is logged in
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        error_log("Unauthorized dashboard access attempt");
        header("Location: ../login.php");
        exit();
    }
    
    // Additional checks
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
        error_log("Non-student role attempted to access dashboard: " . $_SESSION['role']);
        session_destroy();
        header("Location: ../login.php");
        exit();
    }
}

// Perform authentication check
checkStudentAuthentication();

// Get user information from session
$user_id = $_SESSION['id'];
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];
$email = $_SESSION['email'];
$department = $_SESSION['department'] ?? 'General';

// Error logging function
function logDashboardError($message) {
    error_log("[PolyEduHub Dashboard] " . $message);
}

// Initialize database connection with error handling
try {
    $pdo = getDbConnection();

    // Fetch dashboard statistics
    $stats = [
        'uploaded_resources' => 0,
        'downloaded_resources' => 0,
        'points_earned' => 0,
        'rank' => 'N/A'
    ];

    // Query to get uploaded resources count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM resources WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats['uploaded_resources'] = $stmt->fetchColumn();

    // Query to get downloaded resources count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM resource_downloads WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats['downloaded_resources'] = $stmt->fetchColumn();

    // Query to get user points
    $stmt = $pdo->prepare("SELECT points FROM user_points WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats['points_earned'] = $stmt->fetchColumn() ?: 0;

    // Query to get user rank
    $stmt = $pdo->prepare("
        SELECT COUNT(*) + 1 
        FROM user_points 
        WHERE points > (SELECT points FROM user_points WHERE user_id = ?)
    ");
    $stmt->execute([$user_id]);
    $stats['rank'] = $stmt->fetchColumn();

    // Fetch recent activities
    $recent_activities = [];
    $stmt = $pdo->prepare("
    SELECT 
        CASE 
            WHEN r.id IS NOT NULL THEN 'upload'
            WHEN rd.id IS NOT NULL THEN 'download'
            WHEN rc.id IS NOT NULL THEN 'comment'
        END as type, 
        COALESCE(r.title, rd.title, rc.title) as title, 
        COALESCE(r.created_at, rd.downloaded_at, rc.created_at) as timestamp, 
        CASE 
            WHEN r.id IS NOT NULL THEN 'fa-file-upload'
            WHEN rd.id IS NOT NULL THEN 'fa-file-download'
            WHEN rc.id IS NOT NULL THEN 'fa-comment'
        END as icon 
    FROM users u 
    LEFT JOIN (
        SELECT id, user_id, title, created_at 
        FROM resources 
        WHERE user_id = ?
    ) r ON u.id = r.user_id 
    LEFT JOIN (
        SELECT rd.id, r.title, rd.downloaded_at, rd.user_id 
        FROM resource_downloads rd 
        JOIN resources r ON rd.resource_id = r.id 
        WHERE rd.user_id = ?
    ) rd ON u.id = rd.user_id 
    LEFT JOIN (
        SELECT rc.id, r.title, rc.created_at, rc.user_id 
        FROM resource_comments rc 
        JOIN resources r ON rc.resource_id = r.id 
        WHERE rc.user_id = ?
    ) rc ON u.id = rc.user_id 
    ORDER BY timestamp DESC 
    LIMIT 4
");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch recommended resources
    $recommended_resources = [];
    $stmt = $pdo->prepare("
    SELECT 
        r.id, 
        r.title, 
        rc.name as category, 
        CONCAT(u.first_name, ' ', u.last_name) as uploaded_by, 
        COALESCE(AVG(rr.rating), 0) as rating, 
        r.download_count as downloads 
    FROM resources r 
    JOIN resource_categories rc ON r.category_id = rc.id 
    JOIN users u ON r.user_id = u.id 
    LEFT JOIN resource_ratings rr ON r.id = rr.resource_id 
    WHERE r.status = 'approved' AND r.user_id != ? 
    GROUP BY r.id, r.title, rc.name, uploaded_by, r.download_count 
    ORDER BY r.download_count DESC 
    LIMIT 3
");
    $stmt->execute([$user_id]);
    $recommended_resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Log database errors
    logDashboardError("Database error: " . $e->getMessage());
    
    // Set default values in case of error
    $stats = [
        'uploaded_resources' => 0,
        'downloaded_resources' => 0,
        'points_earned' => 0,
        'rank' => 'N/A'
    ];
    $recent_activities = [];
    $recommended_resources = [];
}

// Page title
$page_title = "Student Dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PolyEduHub - Student Dashboard</title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="../assets/img/favicon.png" type="image/x-icon">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background-color: #4e73df;
            color: white;
            padding-top: 1rem;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .sidebar-brand {
            padding: 1.5rem 1rem;
            text-align: center;
            font-size: 1.2rem;
            font-weight: bold;
        }
        
        .sidebar-heading {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.6);
        }
        
        .sidebar .nav-item {
            padding: 0 1rem;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
            border-radius: 0.25rem;
            margin-bottom: 0.25rem;
        }
        
        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            font-weight: bold;
        }
        
        .sidebar hr {
            margin: 1rem 0;
            border-color: rgba(255, 255, 255, 0.1);
        }
        
        .content {
            margin-left: 250px;
            padding: 1.5rem;
            transition: all 0.3s;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                left: -250px;
            }
            
            .sidebar.show {
                left: 0;
            }
            
            .content {
                margin-left: 0;
            }
            
            .content.pushed {
                margin-left: 250px;
            }
        }
        
        .stat-card {
            border-left: 4px solid;
            border-radius: 0.25rem;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            background-color: white;
        }
        
        .stat-card-primary {
            border-left-color: #4e73df;
        }
        
        .stat-card-success {
            border-left-color: #1cc88a;
        }
        
        .stat-card-info {
            border-left-color: #36b9cc;
        }
        
        .stat-card-warning {
            border-left-color: #f6c23e;
        }
        
        .stat-label {
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 0.25rem;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #5a5c69;
        }
        
        .stat-icon {
            font-size: 2rem;
            color: #dddfeb;
        }
        
        .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 1rem 0;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .activity-icon {
            background-color: #4e73df;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }
        
        .activity-icon.upload {
            background-color: #4e73df;
        }
        
        .activity-icon.download {
            background-color: #1cc88a;
        }
        
        .activity-icon.comment {
            background-color: #f6c23e;
        }
        
        .activity-icon.badge {
            background-color: #e74a3b;
        }
        
        .activity-content h6 {
            margin-bottom: 0.25rem;
        }
        
        .activity-time {
            font-size: 0.8rem;
            color: #858796;
        }
        
        .resource-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .resource-icon {
            background-color: #f8f9fc;
            color: #4e73df;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.5rem;
        }
        
        .resource-details {
            flex: 1;
        }
        
        .resource-details h6 {
            margin-bottom: 0.25rem;
        }
        
        .resource-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.8rem;
            color: #858796;
        }
        
        .resource-actions {
            margin-left: 1rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-info img {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .notification-counter {
            position: absolute;
            top: 0.2rem;
            right: 0.2rem;
            font-size: 0.65rem;
            background-color: #e74a3b;
            color: white;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .navbar-search {
            max-width: 300px;
        }
        
        .toggle-sidebar {
            display: none;
        }
        
        @media (max-width: 768px) {
            .toggle-sidebar {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon rotate-n-15">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="sidebar-brand-text mx-3">PolyEduHub</div>
        </div>
        
        <hr class="sidebar-divider">
        
        <div class="sidebar-heading">
            Navigation
        </div>
        
        <ul class="nav">
            <li class="nav-item">
                <a class="nav-link active" href="dashboard.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <hr class="sidebar-divider">
            
            <div class="sidebar-heading">
                Resources
            </div>
            
            <li class="nav-item">
                <a class="nav-link" href="resources/index.php">
                    <i class="fas fa-fw fa-folder"></i>
                    <span>Browse Resources</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="resources/upload.php">
                    <i class="fas fa-fw fa-file-upload"></i>
                    <span>Upload Resource</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="resources/my-resources.php">
                    <i class="fas fa-fw fa-list"></i>
                    <span>My Resources</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="resources/favorites.php">
                    <i class="fas fa-fw fa-star"></i>
                    <span>Favorites</span>
                </a>
            </li>
            
            <hr class="sidebar-divider">
            
            <div class="sidebar-heading">
                Community
            </div>
            
            <li class="nav-item">
                <a class="nav-link" href="chat/index.php">
                    <i class="fas fa-fw fa-comments"></i>
                    <span>Chat Rooms</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="leaderboard/index.php">
                    <i class="fas fa-fw fa-trophy"></i>
                    <span>Leaderboard</span>
                </a>
            </li>
            
            <hr class="sidebar-divider">
            
            <div class="sidebar-heading">
                Account
            </div>
            
            <li class="nav-item">
                <a class="nav-link" href="profile/index.php">
                    <i class="fas fa-fw fa-user"></i>
                    <span>My Profile</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="profile/badges.php">
                    <i class="fas fa-fw fa-award"></i>
                    <span>My Badges</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="notifications/index.php">
                    <i class="fas fa-fw fa-bell"></i>
                    <span>Notifications</span>
                </a>
            </li>
            
            <hr class="sidebar-divider">
            
            <li class="nav-item">
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-fw fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>
    
    <!-- Content Wrapper -->
    <div class="content">
        <!-- Topbar -->
        <nav class="navbar navbar-expand-lg navbar-light mb-4">
            <div class="container-fluid">
                <button class="btn toggle-sidebar" id="toggleSidebar">
                    <i class="fas fa-bars"></i>
                </button>
                
                <!-- Search -->
                <form class="navbar-search">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Search for resources..." aria-label="Search">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                
                <div class="navbar-nav ms-auto">
                    <!-- Notifications Dropdown -->
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell fa-fw"></i>
                            <span class="notification-counter">3</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown">
                            <li><h6 class="dropdown-header">Notifications Center</h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item d-flex align-items-center" href="#">
                                <div class="me-3">
                                    <div class="icon-circle bg-primary text-white p-2 rounded-circle">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                </div>
                                <div>
                                    <div class="small text-muted">Today</div>
                                    <span>Your resource "Database Notes" was approved!</span>
                                </div>
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center small text-muted" href="notifications/index.php">Show All Notifications</a></li>
                        </ul>
                    </div>
                    
                    <!-- User Information -->
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle user-info" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="d-none d-lg-inline text-gray-600 small me-2"><?= htmlspecialchars($first_name . ' ' . $last_name) ?></span>
                            <img src="../assets/img/ui/default-profile.png" alt="Profile Image">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile/index.php"><i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="profile/edit.php"><i class="fas fa-cogs fa-sm fa-fw me-2 text-gray-400"></i> Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- Page Content -->
        <div class="container-fluid">
            <!-- Page Heading -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
                <a href="resources/upload.php" class="d-none d-sm-inline-block btn btn-primary shadow-sm">
                    <i class="fas fa-upload fa-sm text-white-50 me-1"></i> Upload New Resource
                </a>
            </div>
            
            <!-- Stats Cards Row -->
            <div class="row">
                <!-- Uploaded Resources Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card stat-card-primary h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col">
                                    <div class="stat-label">Uploaded Resources</div>
                                    <div class="stat-value"><?= $stats['uploaded_resources'] ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-file-upload stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Downloaded Resources Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card stat-card-success h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col">
                                    <div class="stat-label">Downloaded Resources</div>
                                    <div class="stat-value"><?= $stats['downloaded_resources'] ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-file-download stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Points Earned Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card stat-card-info h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col">
                                    <div class="stat-label">Points Earned</div>
                                    <div class="stat-value"><?= $stats['points_earned'] ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-star stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Leaderboard Rank Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card stat-card-warning h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col">
                                    <div class="stat-label">Leaderboard Rank</div>
                                    <div class="stat-value">#<?= $stats['rank'] ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-trophy stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Content Row -->
            <div class="row">
                <!-- Recent Activities -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold">Recent Activities</h6>
                            <a href="#" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_activities)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-history fa-3x mb-3"></i>
                                    <p>No recent activities to display</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon <?= $activity['type'] ?>">
                                        <i class="fas <?= $activity['icon'] ?> fa-lg"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h6><?= htmlspecialchars($activity['title']) ?></h6>
                                        <div class="activity-time">
                                            <i class="far fa-clock me-1"></i> 
                                            <?= timeAgo($activity['timestamp']) ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Recommended Resources -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold">Recommended Resources</h6>
                            <a href="resources/index.php" class="btn btn-sm btn-primary">Browse All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recommended_resources)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-lightbulb fa-3x mb-3"></i>
                                    <p>No recommendations available yet</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recommended_resources as $resource): ?>
                                <div class="resource-item">
                                    <div class="resource-icon">
                                        <i class="fas fa-file-alt fa-lg"></i>
                                    </div>
                                    <div class="resource-details">
                                        <h6><?= htmlspecialchars($resource['title']) ?></h6>
                                        <div class="resource-meta">
                                            <span><i class="fas fa-folder me-1"></i> <?= htmlspecialchars($resource['category']) ?></span>
                                            <span><i class="fas fa-user me-1"></i> <?= htmlspecialchars($resource['uploaded_by']) ?></span>
                                            <span><i class="fas fa-star me-1"></i> <?= number_format($resource['rating'], 1) ?></span>
                                            <span><i class="fas fa-download me-1"></i> <?= number_format($resource['downloads']) ?></span>
                                        </div>
                                    </div>
                                    <div class="resource-actions">
                                        <a href="resources/download.php?id=<?= $resource['id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- User Badge Card -->
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold">Your Achievements</h6>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-3 text-center">
                                    <div class="mb-3">
                                        <i class="fas fa-award fa-5x text-warning mb-3"></i>
                                        <h4>Contributor</h4>
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <div class="progress mb-4">
                                        <div class="progress-bar bg-warning" role="progressbar" style="width: 35%" aria-valuenow="35" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <p>You're making good progress as a contributor! Upload more resources to reach the next level and unlock additional rewards.</p>
                                    <div class="mt-3">
                                        <span class="badge bg-primary me-1">Resource Uploader</span>
                                        <span class="badge bg-success me-1">Frequent Learner</span>
                                        <span class="badge bg-info me-1">Helpful Reviewer</span>
                                        <a href="profile/badges.php" class="btn btn-sm btn-outline-primary float-end">View All Badges</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Upcoming Events -->
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold">Department Announcements</h6>
                            <span class="badge bg-primary"><?= htmlspecialchars($department) ?></span>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <h5><i class="fas fa-bullhorn me-2"></i> Mid-term Examination Schedule</h5>
                                <p>The mid-term examination for this semester will begin on March 15, 2025. Please check your department's notice board for the detailed schedule.</p>
                                <small class="text-muted">Posted: March 1, 2025</small>
                            </div>
                            <div class="alert alert-success">
                                <h5><i class="fas fa-calendar-alt me-2"></i> Workshop: Advanced Web Development</h5>
                                <p>Join us for a special workshop on Advanced Web Development techniques on March 10, 2025 at the Main Auditorium. Registration is mandatory.</p>
                                <small class="text-muted">Posted: February 28, 2025</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="sticky-footer bg-light mt-5">
            <div class="container my-auto">
                <div class="copyright text-center my-auto">
                    <span>Copyright &copy; PolyEduHub 2025</span>
                </div>
            </div>
        </footer>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle sidebar on mobile
            const toggleButton = document.getElementById('toggleSidebar');
            const sidebar = document.querySelector('.sidebar');
            const content = document.querySelector('.content');
            
            if (toggleButton) {
                toggleButton.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                    content.classList.toggle('pushed');
                });
            }
            
            // Close the sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                const isClickInsideSidebar = sidebar.contains(event.target);
                const isClickInsideToggle = toggleButton.contains(event.target);
                
                if (!isClickInsideSidebar && !isClickInsideToggle && sidebar.classList.contains('show')) {
                    sidebar.classList.remove('show');
                    content.classList.remove('pushed');
                }
            });
        });
    </script>
</body>
</html>