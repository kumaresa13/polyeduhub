<?php
// Start session and include necessary files
session_start();

// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db-connection.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['id'];
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];
$email = $_SESSION['email'];
$department = $_SESSION['department'] ?? 'General';

// Initialize database connection
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
        WHERE points > (SELECT COALESCE(points, 0) FROM user_points WHERE user_id = ?)
    ");
    $stmt->execute([$user_id]);
    $stats['rank'] = $stmt->fetchColumn();

    // Fetch recent activities
    $stmt = $pdo->prepare("
        SELECT 'upload' as type, title, created_at as timestamp
        FROM resources 
        WHERE user_id = ?
        
        UNION ALL
        
        SELECT 'download' as type, r.title, rd.downloaded_at as timestamp
        FROM resource_downloads rd
        JOIN resources r ON rd.resource_id = r.id
        WHERE rd.user_id = ?
        
        UNION ALL
        
        SELECT 'comment' as type, r.title, rc.created_at as timestamp
        FROM resource_comments rc
        JOIN resources r ON rc.resource_id = r.id
        WHERE rc.user_id = ?
        
        ORDER BY timestamp DESC
        LIMIT 4
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $recent_activities = $stmt->fetchAll();

    // Fetch recommended resources
    $stmt = $pdo->prepare("
        SELECT 
            r.id, 
            r.title, 
            rc.name as category, 
            CONCAT(u.first_name, ' ', u.last_name) as uploaded_by,
            (SELECT COALESCE(AVG(rating), 0) FROM resource_ratings WHERE resource_id = r.id) as rating,
            r.download_count as downloads
        FROM resources r
        JOIN resource_categories rc ON r.category_id = rc.id
        JOIN users u ON r.user_id = u.id
        WHERE r.status = 'approved' AND r.user_id != ?
        ORDER BY r.download_count DESC
        LIMIT 3
    ");
    $stmt->execute([$user_id]);
    $recommended_resources = $stmt->fetchAll();

} catch (PDOException $e) {
    // Set default values in case of error
    error_log("Dashboard error: " . $e->getMessage());
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

// Include header (this will handle the sidebar as well)
include_once 'includes/header.php';
?>

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
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Uploaded Resources</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['uploaded_resources'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-upload fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Downloaded Resources Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Downloaded Resources</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['downloaded_resources'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-download fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Points Earned Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Points Earned</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['points_earned'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-star fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Leaderboard Rank Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Leaderboard Rank</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">#<?= $stats['rank'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-trophy fa-2x text-gray-300"></i>
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
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Activities</h6>
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
                        <div class="d-flex mb-4">
                            <div class="flex-shrink-0">
                                <?php if ($activity['type'] === 'upload'): ?>
                                <div class="bg-primary text-white p-2 rounded">
                                    <i class="fas fa-file-upload"></i>
                                </div>
                                <?php elseif ($activity['type'] === 'download'): ?>
                                <div class="bg-success text-white p-2 rounded">
                                    <i class="fas fa-file-download"></i>
                                </div>
                                <?php else: ?>
                                <div class="bg-warning text-white p-2 rounded">
                                    <i class="fas fa-comment"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="ms-3">
                                <h6 class="mb-1"><?= htmlspecialchars($activity['title']) ?></h6>
                                <div class="small text-muted">
                                    <?= time_elapsed_string($activity['timestamp']) ?>
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
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Recommended Resources</h6>
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
                        <div class="d-flex align-items-center mb-4">
                            <div class="flex-shrink-0">
                                <div class="bg-light p-3 rounded">
                                    <i class="fas fa-file-alt fa-2x text-primary"></i>
                                </div>
                            </div>
                            <div class="ms-3 flex-grow-1">
                                <h6 class="mb-1"><?= htmlspecialchars($resource['title']) ?></h6>
                                <div class="small d-flex flex-wrap">
                                    <span class="me-3"><i class="fas fa-folder me-1"></i> <?= htmlspecialchars($resource['category']) ?></span>
                                    <span class="me-3"><i class="fas fa-user me-1"></i> <?= htmlspecialchars($resource['uploaded_by']) ?></span>
                                    <span class="me-3"><i class="fas fa-download me-1"></i> <?= $resource['downloads'] ?></span>
                                </div>
                            </div>
                            <a href="resources/download.php?id=<?= $resource['id'] ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-download"></i>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>    


<?php include_once 'includes/footer.php'; ?>