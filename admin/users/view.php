<?php
// File path: admin/users/view.php

// Include necessary files
require_once '../../includes/config.php';
require_once '../../includes/db-connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin-functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../admin-login.php");
    exit();
}

// Get admin user information
$admin_id = $_SESSION['id'];

// Get user ID from query string
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$user_id) {
    $_SESSION['error_message'] = "Invalid user ID";
    header("Location: index.php");
    exit();
}

// Fetch user data
try {
    $pdo = getDbConnection();
    
    // Get user details
    $stmt = $pdo->prepare("
        SELECT u.*, up.points, up.level 
        FROM users u
        LEFT JOIN user_points up ON u.id = up.user_id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error_message'] = "User not found";
        header("Location: index.php");
        exit();
    }
    
    // Get user activity
    $stmt = $pdo->prepare("
        -- Resources uploaded
        SELECT 'resource_upload' as activity_type, r.title as subject, r.created_at as activity_date
        FROM resources r
        WHERE r.user_id = ?
        
        UNION ALL
        
        -- Resource downloads
        SELECT 'resource_download' as activity_type, r.title as subject, rd.downloaded_at as activity_date
        FROM resource_downloads rd
        JOIN resources r ON rd.resource_id = r.id
        WHERE rd.user_id = ?
        
        UNION ALL
        
        -- Comments
        SELECT 'comment' as activity_type, r.title as subject, rc.created_at as activity_date
        FROM resource_comments rc
        JOIN resources r ON rc.resource_id = r.id
        WHERE rc.user_id = ?
        
        UNION ALL
        
        -- Points earned
        SELECT 'points' as activity_type, ph.action as subject, ph.created_at as activity_date
        FROM points_history ph
        WHERE ph.user_id = ?
        
        ORDER BY activity_date DESC
        LIMIT 15
    ");
    $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
    $activities = $stmt->fetchAll();
    
    // Get user resources
    $stmt = $pdo->prepare("
        SELECT r.id, r.title, r.status, r.file_type, r.download_count, r.created_at,
               rc.name as category_name
        FROM resources r
        JOIN resource_categories rc ON r.category_id = rc.id
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $resources = $stmt->fetchAll();
    
    // Get user badges
    $stmt = $pdo->prepare("
        SELECT b.id, b.name, b.description, b.icon, ub.earned_at
        FROM user_badges ub
        JOIN badges b ON ub.badge_id = b.id
        WHERE ub.user_id = ?
        ORDER BY ub.earned_at DESC
    ");
    $stmt->execute([$user_id]);
    $badges = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching user details: " . $e->getMessage());
    $_SESSION['error_message'] = "Error fetching user data";
    header("Location: index.php");
    exit();
}

// Set page title and nested path variable
$page_title = "User Profile: " . $user['first_name'] . " " . $user['last_name'];
$nested = true;

// Include header
include_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">User Profile</h1>
        <div>
            <a href="index.php" class="btn btn-sm btn-secondary shadow-sm me-2">
                <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back to Users
            </a>
            <a href="edit.php?id=<?= $user_id ?>" class="btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-edit fa-sm text-white-50"></i> Edit User
            </a>
        </div>
    </div>
    
    <!-- Success Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['success_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <div class="row">
        <!-- User Profile Card -->
        <div class="col-xl-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">User Information</h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <img class="img-profile rounded-circle" 
                             src="<?= !empty($user['profile_image']) ? '../../' . $user['profile_image'] : '../../assets/img/ui/default-profile.png' ?>" 
                             alt="Profile Image" style="width: 150px; height: 150px;">
                        <h4 class="mt-3"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h4>
                        <div>
                            <?php if ($user['role'] === 'admin'): ?>
                                <span class="badge bg-warning">Administrator</span>
                            <?php elseif ($user['role'] === 'teacher'): ?>
                                <span class="badge bg-info">Teacher</span>
                            <?php else: ?>
                                <span class="badge bg-primary">Student</span>
                            <?php endif; ?>
                            
                            <?php if ($user['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h5 class="font-weight-bold">Contact Information</h5>
                        <div class="mb-2">
                            <strong><i class="fas fa-envelope me-2"></i> Email:</strong>
                            <p><?= htmlspecialchars($user['email']) ?></p>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h5 class="font-weight-bold">Academic Information</h5>
                        <div class="mb-2">
                            <strong><i class="fas fa-building me-2"></i> Department:</strong>
                            <p><?= htmlspecialchars($user['department'] ?: 'Not specified') ?></p>
                        </div>
                        
                        <?php if (!empty($user['student_id'])): ?>
                        <div class="mb-2">
                            <strong><i class="fas fa-id-card me-2"></i> Student ID:</strong>
                            <p><?= htmlspecialchars($user['student_id']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <h5 class="font-weight-bold">Account Information</h5>
                        <div class="mb-2">
                            <strong><i class="fas fa-calendar me-2"></i> Joined:</strong>
                            <p><?= date('F j, Y', strtotime($user['created_at'])) ?></p>
                        </div>
                        <div class="mb-2">
                            <strong><i class="fas fa-sign-in-alt me-2"></i> Last Login:</strong>
                            <p><?= $user['last_login'] ? date('F j, Y, g:i a', strtotime($user['last_login'])) : 'Never' ?></p>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h5 class="font-weight-bold">Stats</h5>
                        <div class="mb-2">
                            <strong><i class="fas fa-star me-2"></i> Points:</strong>
                            <p><?= number_format($user['points'] ?? 0) ?></p>
                        </div>
                        <div class="mb-2">
                            <strong><i class="fas fa-level-up-alt me-2"></i> Level:</strong>
                            <p>Level <?= $user['level'] ?? 1 ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Activity and Resources Column -->
        <div class="col-xl-8">
            <!-- Badges Card -->
            <div class="card shadow mb-4" id="badges">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Badges</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($badges)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-award fa-3x text-gray-300 mb-3"></i>
                            <p>This user hasn't earned any badges yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($badges as $badge): ?>
                                <div class="col-xl-3 col-md-4 col-sm-6 mb-4">
                                    <div class="card border-0">
                                        <div class="card-body text-center">
                                            <img src="../../assets/img/badges/<?= htmlspecialchars($badge['icon']) ?>" 
                                                 alt="<?= htmlspecialchars($badge['name']) ?>" 
                                                 class="img-fluid mb-2" style="max-width: 80px;">
                                            <h6 class="card-title"><?= htmlspecialchars($badge['name']) ?></h6>
                                            <p class="small text-muted"><?= htmlspecialchars($badge['description']) ?></p>
                                            <div class="small">Earned: <?= date('M j, Y', strtotime($badge['earned_at'])) ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Resources Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Resources</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($resources)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-folder-open fa-3x text-gray-300 mb-3"></i>
                            <p>This user hasn't uploaded any resources yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Downloads</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resources as $resource): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($resource['title']) ?></td>
                                            <td><?= htmlspecialchars($resource['category_name']) ?></td>
                                            <td>
                                                <?php if ($resource['status'] === 'approved'): ?>
                                                    <span class="badge bg-success">Approved</span>
                                                <?php elseif ($resource['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= number_format($resource['download_count']) ?></td>
                                            <td><?= date('M j, Y', strtotime($resource['created_at'])) ?></td>
                                            <td>
                                                <a href="../resources/view.php?id=<?= $resource['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
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
                        <div class="text-center py-4">
                            <i class="fas fa-history fa-3x text-gray-300 mb-3"></i>
                            <p>No recent activities for this user.</p>
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($activities as $activity): ?>
                                <div class="d-flex mb-4">
                                    <div class="flex-shrink-0 me-3">
                                        <?php if ($activity['activity_type'] === 'resource_upload'): ?>
                                            <div class="p-2 rounded bg-primary text-white">
                                                <i class="fas fa-upload"></i>
                                            </div>
                                        <?php elseif ($activity['activity_type'] === 'resource_download'): ?>
                                            <div class="p-2 rounded bg-success text-white">
                                                <i class="fas fa-download"></i>
                                            </div>
                                        <?php elseif ($activity['activity_type'] === 'comment'): ?>
                                            <div class="p-2 rounded bg-info text-white">
                                                <i class="fas fa-comment"></i>
                                            </div>
                                        <?php elseif ($activity['activity_type'] === 'points'): ?>
                                            <div class="p-2 rounded bg-warning text-white">
                                                <i class="fas fa-star"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">
                                            <?php if ($activity['activity_type'] === 'resource_upload'): ?>
                                                Uploaded resource: "<?= htmlspecialchars($activity['subject']) ?>"
                                            <?php elseif ($activity['activity_type'] === 'resource_download'): ?>
                                                Downloaded resource: "<?= htmlspecialchars($activity['subject']) ?>"
                                            <?php elseif ($activity['activity_type'] === 'comment'): ?>
                                                Commented on resource: "<?= htmlspecialchars($activity['subject']) ?>"
                                            <?php elseif ($activity['activity_type'] === 'points'): ?>
                                                <?= htmlspecialchars($activity['subject']) ?>
                                            <?php endif; ?>
                                        </h6>
                                        <small class="text-muted"><?= time_elapsed_string($activity['activity_date']) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>