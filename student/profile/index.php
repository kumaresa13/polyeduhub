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
$user_id = isset($_GET['id']) ? intval($_GET['id']) : $_SESSION['id'];

// Initialize variables to prevent null reference errors
$resources_count = 0;
$comments_count = 0;
$user = null;
$badges = [];
$resources = [];
$activities = [];

// Get user profile data
try {
    $pdo = getDbConnection();


    $stmt = $pdo->prepare("
SELECT 
    u.*, 
    up.points, 
    up.level,
    (SELECT COUNT(*) FROM resources WHERE user_id = u.id AND status = 'approved') as resources_count,
    (SELECT COUNT(*) FROM resource_comments WHERE user_id = u.id) as comments_count
FROM users u
LEFT JOIN user_points up ON u.id = up.user_id
WHERE u.id = ?
");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // User not found
        $_SESSION['error_message'] = "User not found.";
        header("Location: ../dashboard.php");
        exit();
    }

    // Safely get counts from the user array
    $resources_count = isset($user['resources_count']) ? $user['resources_count'] : 0;
    $comments_count = isset($user['comments_count']) ? $user['comments_count'] : 0;

    // Get user badges
    $stmt = $pdo->prepare("
        SELECT b.* 
        FROM badges b
        JOIN user_badges ub ON b.id = ub.badge_id
        WHERE ub.user_id = ?
        ORDER BY ub.earned_at DESC
    ");
    $stmt->execute([$user_id]);
    $badges = $stmt->fetchAll();

    // Get recent resources uploaded by user
    $stmt = $pdo->prepare("
        SELECT r.id, r.title, r.description, r.file_type, r.download_count, r.created_at,
               rc.name as category_name
        FROM resources r
        JOIN resource_categories rc ON r.category_id = rc.id
        WHERE r.user_id = ? AND r.status = 'approved'
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $resources = $stmt->fetchAll();

    // Get recent activities by user
    $stmt = $pdo->prepare("
        SELECT al.action, al.details, al.created_at
        FROM activity_log al
        WHERE al.user_id = ?
        ORDER BY al.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $activities = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error in profile page: " . $e->getMessage());
    $user = null;
    $badges = [];
    $resources = [];
    $activities = [];
}

// Page title
$page_title = "User Profile";
$nested = true;

// Include header
include_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <?= isset($_SESSION['first_name']) ? htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) : '' ?>'s
            Profile
        </h1>
        <?php if ($user_id === $_SESSION['id']): ?>
            <a href="edit.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-user-edit fa-sm text-white-50"></i> Edit Profile
            </a>
        <?php endif; ?>
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

    <div class="row">
        <!-- User Profile Card -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Profile Information</h6>
                </div>
                <div class="card-body text-center">
                    <img class="img-profile rounded-circle mb-3"
                        src="<?= isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image']) ? '../../' . $_SESSION['profile_image'] : '../../assets/img/ui/default-profile.png' ?>"
                        alt="Profile Image" style="width: 150px; height: 150px;">
                    <h4 class="font-weight-bold">
                        <?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?>
                    </h4>
                    <p class="text-gray-600 mb-4">
                        <i class="fas fa-graduation-cap mr-2"></i>
                        <?= isset($_SESSION['department']) && !empty($_SESSION['department']) ? htmlspecialchars($_SESSION['department']) : 'Department not specified' ?>
                    </p>

                    <?php if ($user_id === $_SESSION['id'] && isset($user['email'])): ?>
                        <p class="text-gray-600 mb-4">
                            <i class="fas fa-envelope me-2"></i> <?= htmlspecialchars($user['email']) ?>
                        </p>
                    <?php endif; ?>

                    <div class="mb-4">
                        <div class="badge bg-primary p-2 mb-2">
                            <i class="fas fa-award me-1"></i> Level <?= isset($user['level']) ? $user['level'] : 1 ?>
                        </div>
                        <div class="badge bg-success p-2">
                            <i class="fas fa-star me-1"></i>
                            <?= number_format(isset($user['points']) ? $user['points'] : 0) ?> Points
                        </div>
                    </div>

                    <?php if (isset($user['bio']) && !empty($user['bio'])): ?>
                        <div class="mb-4">
                            <h6 class="font-weight-bold">About Me</h6>
                            <p class="text-gray-600"><?= nl2br(htmlspecialchars($user['bio'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="row text-center mb-3">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="font-weight-bold"><?= number_format($resources_count) ?></div>
                            <div class="small text-gray-600">Resources</div>
                        </div>
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="font-weight-bold"><?= number_format($comments_count) ?></div>
                            <div class="small text-gray-600">Comments</div>
                        </div>
                        <div class="col-md-4">
                            <div class="font-weight-bold"><?= count($badges) ?></div>
                            <div class="small text-gray-600">Badges</div>
                        </div>
                    </div>

                    <?php if ($user_id === $_SESSION['id']): ?>
                        <a href="edit.php" class="btn btn-outline-primary btn-block">
                            <i class="fas fa-user-edit me-1"></i> Edit Profile
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- User Badges Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Badges Earned</h6>
                    <a href="badges.php" class="small">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($badges)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-award fa-3x mb-3"></i>
                            <p>No badges earned yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach (array_slice($badges, 0, 6) as $badge): ?>
                                <div class="col-md-4 col-6 text-center mb-3">
                                    <div class="badge-icon mb-2">
                                        <img src="../../assets/img/badges/<?= htmlspecialchars($badge['icon'] ?? 'default.png') ?>"
                                            alt="<?= htmlspecialchars($badge['name'] ?? 'Badge') ?>" width="50" height="50">
                                    </div>
                                    <div class="badge-name small font-weight-bold">
                                        <?= htmlspecialchars($badge['name'] ?? 'Badge') ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($badges) > 6): ?>
                            <div class="text-center mt-2">
                                <a href="badges.php" class="btn btn-sm btn-outline-primary">View All Badges</a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- User Activity Column -->
        <div class="col-xl-8 col-lg-7">
            <!-- Recent Resources Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Resources</h6>
                    <a href="../resources/index.php?user=<?= $user_id ?>" class="small">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($resources)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-folder-open fa-3x mb-3"></i>
                            <p>No resources uploaded yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Downloads</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resources as $resource): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($resource['title'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($resource['category_name'] ?? '') ?></td>
                                            <td class="text-center"><?= $resource['download_count'] ?? 0 ?></td>
                                            <td><?= isset($resource['created_at']) ? date('M d, Y', strtotime($resource['created_at'])) : 'N/A' ?>
                                            </td>
                                            <td>
                                                <a href="../resources/view.php?id=<?= $resource['id'] ?? 0 ?>"
                                                    class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="../resources/download.php?id=<?= $resource['id'] ?? 0 ?>"
                                                    class="btn btn-sm btn-success">
                                                    <i class="fas fa-download"></i>
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

            <!-- Recent Activity Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Activity</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($activities)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-history fa-3x mb-3"></i>
                            <p>No recent activity.</p>
                        </div>
                    <?php else: ?>
                        <div class="activity-timeline">
                            <?php foreach ($activities as $activity): ?>
                                <div class="activity-item d-flex mb-3">
                                    <div class="activity-icon bg-primary text-white rounded-circle p-2 me-3">
                                        <i class="fas fa-history"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="font-weight-bold"><?= htmlspecialchars($activity['action'] ?? '') ?></div>
                                        <div class="text-gray-600 small"><?= htmlspecialchars($activity['details'] ?? '') ?>
                                        </div>
                                        <div class="text-gray-500 small">
                                            <?= isset($activity['created_at']) ? time_elapsed_string($activity['created_at']) : 'N/A' ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistics Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Contribution Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card rounded shadow-sm">
                                <div class="card-body text-center">
                                    <h5 class="card-title">
                                        <i class="fas fa-file-upload text-primary me-2"></i> Resources Uploaded
                                    </h5>
                                    <h2 class="display-4"><?= number_format($resources_count) ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card rounded shadow-sm">
                                <div class="card-body text-center">
                                    <h5 class="card-title">
                                        <i class="fas fa-comment-dots text-primary me-2"></i> Comments Made
                                    </h5>
                                    <h2 class="display-4"><?= number_format($comments_count) ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card rounded shadow-sm">
                                <div class="card-body text-center">
                                    <h5 class="card-title">
                                        <i class="fas fa-award text-primary me-2"></i> Badges Earned
                                    </h5>
                                    <h2 class="display-4"><?= count($badges) ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card rounded shadow-sm">
                                <div class="card-body text-center">
                                    <h5 class="card-title">
                                        <i class="fas fa-star text-primary me-2"></i> Points Earned
                                    </h5>
                                    <h2 class="display-4">
                                        <?= number_format(isset($user['points']) ? $user['points'] : 0) ?>
                                    </h2>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>