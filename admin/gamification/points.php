<?php
// File path: admin/gamification/points.php

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

// Handle point system setting updates
$message = '';
$message_type = '';

if (isset($_POST['update_points'])) {
    $points_upload = intval($_POST['points_upload']);
    $points_download = intval($_POST['points_download']);
    $points_comment = intval($_POST['points_comment']);
    $points_rating = intval($_POST['points_rating']);
    $points_answer = intval($_POST['points_answer']);
    
    try {
        $pdo = getDbConnection();
        
        // Update points in config file
        $config_file = '../../includes/config.php';
        $config_content = file_get_contents($config_file);
        
        // Replace values
        $config_content = preg_replace("/define\('POINTS_UPLOAD', \d+\);/", "define('POINTS_UPLOAD', $points_upload);", $config_content);
        $config_content = preg_replace("/define\('POINTS_DOWNLOAD', \d+\);/", "define('POINTS_DOWNLOAD', $points_download);", $config_content);
        $config_content = preg_replace("/define\('POINTS_COMMENT', \d+\);/", "define('POINTS_COMMENT', $points_comment);", $config_content);
        $config_content = preg_replace("/define\('POINTS_RATING', \d+\);/", "define('POINTS_RATING', $points_rating);", $config_content);
        $config_content = preg_replace("/define\('POINTS_ANSWER', \d+\);/", "define('POINTS_ANSWER', $points_answer);", $config_content);
        
        // Write updated content
        file_put_contents($config_file, $config_content);
        
        // Log action
        logAdminAction($admin_id, "Updated point system", "Updated point system values");
        
        // Update constants in current session
        define('POINTS_UPLOAD', $points_upload, true);
        define('POINTS_DOWNLOAD', $points_download, true);
        define('POINTS_COMMENT', $points_comment, true);
        define('POINTS_RATING', $points_rating, true);
        define('POINTS_ANSWER', $points_answer, true);
        
        $message = "Point system settings updated successfully";
        $message_type = "success";
    } catch (Exception $e) {
        error_log("Error updating point system: " . $e->getMessage());
        $message = "Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Handle manual point adjustment
if (isset($_POST['adjust_points'])) {
    $user_id = intval($_POST['user_id']);
    $points = intval($_POST['points']);
    $action = filter_var($_POST['action_description'], FILTER_SANITIZE_STRING);
    $details = filter_var($_POST['action_details'], FILTER_SANITIZE_STRING);
    
    if (!$user_id) {
        $message = "Invalid user";
        $message_type = "danger";
    } else {
        $result = awardPoints($user_id, $points, $action, $details);
        
        if ($result) {
            // Log admin action
            logAdminAction(
                $admin_id, 
                "Manual point adjustment", 
                "Adjusted points for user ID: $user_id, Points: $points, Action: $action"
            );
            
            $message = "Points adjusted successfully";
            $message_type = "success";
        } else {
            $message = "Error adjusting points";
            $message_type = "danger";
        }
    }
}

// Get top users by points
try {
    $pdo = getDbConnection();
    
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.department, 
               up.points, up.level,
               (SELECT COUNT(*) FROM user_badges WHERE user_id = u.id) as badge_count
        FROM users u
        LEFT JOIN user_points up ON u.id = up.user_id
        WHERE u.role = 'student'
        ORDER BY up.points DESC
        LIMIT 10
    ");
    $stmt->execute();
    $top_users = $stmt->fetchAll();
    
    // Get recent point history
    $stmt = $pdo->prepare("
        SELECT ph.id, ph.user_id, u.first_name, u.last_name, 
               ph.points, ph.action, ph.description, ph.created_at
        FROM points_history ph
        JOIN users u ON ph.user_id = u.id
        ORDER BY ph.created_at DESC
        LIMIT 15
    ");
    $stmt->execute();
    $point_history = $stmt->fetchAll();
    
    // Get all students for point adjustment
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, up.points
        FROM users u
        LEFT JOIN user_points up ON u.id = up.user_id
        WHERE u.role = 'student'
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute();
    $all_students = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching point data: " . $e->getMessage());
    $top_users = [];
    $point_history = [];
    $all_students = [];
}

// Set page title and nested path variable
$page_title = "Points System";
$nested = true;

// Include header
include_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Points System Management</h1>
    </div>
    
    <!-- Display Messages -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Point Settings Card -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Point System Settings</h6>
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <div class="mb-3 row">
                            <label for="points_upload" class="col-sm-6 col-form-label">Points for Resource Upload</label>
                            <div class="col-sm-6">
                                <input type="number" class="form-control" id="points_upload" name="points_upload" value="<?= POINTS_UPLOAD ?>" min="0">
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="points_download" class="col-sm-6 col-form-label">Points for Each Download</label>
                            <div class="col-sm-6">
                                <input type="number" class="form-control" id="points_download" name="points_download" value="<?= POINTS_DOWNLOAD ?>" min="0">
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="points_comment" class="col-sm-6 col-form-label">Points for Each Comment</label>
                            <div class="col-sm-6">
                                <input type="number" class="form-control" id="points_comment" name="points_comment" value="<?= POINTS_COMMENT ?>" min="0">
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="points_rating" class="col-sm-6 col-form-label">Points for Each Rating</label>
                            <div class="col-sm-6">
                                <input type="number" class="form-control" id="points_rating" name="points_rating" value="<?= POINTS_RATING ?>" min="0">
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="points_answer" class="col-sm-6 col-form-label">Points for Each Answer</label>
                            <div class="col-sm-6">
                            <input type="number" class="form-control" id="points_answer" name="points_answer" value="<?= POINTS_ANSWER ?>" min="0">
                            </div>
                        </div>
                        <div class="text-center">
                            <button type="submit" name="update_points" class="btn btn-primary">Update Point System</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Level Thresholds Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Level Thresholds</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Level</th>
                                    <th>Points Required</th>
                                    <th>Badge Color</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Level 1</td>
                                    <td>0 points</td>
                                    <td><span class="badge rounded-pill" style="background-color: #cd7f32;">Bronze</span></td>
                                </tr>
                                <tr>
                                    <td>Level 2</td>
                                    <td>100 points</td>
                                    <td><span class="badge rounded-pill" style="background-color: #cd7f32;">Bronze</span></td>
                                </tr>
                                <tr>
                                    <td>Level 3</td>
                                    <td>500 points</td>
                                    <td><span class="badge rounded-pill" style="background-color: #c0c0c0;">Silver</span></td>
                                </tr>
                                <tr>
                                    <td>Level 4</td>
                                    <td>1000 points</td>
                                    <td><span class="badge rounded-pill" style="background-color: #c0c0c0;">Silver</span></td>
                                </tr>
                                <tr>
                                    <td>Level 5</td>
                                    <td>5000 points</td>
                                    <td><span class="badge rounded-pill" style="background-color: #ffd700;">Gold</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Manual Point Adjustment Card -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Manual Point Adjustment</h6>
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <div class="mb-3">
                            <label for="user_id" class="form-label">Select Student</label>
                            <select class="form-select" id="user_id" name="user_id" required>
                                <option value="">Select a student</option>
                                <?php foreach ($all_students as $student): ?>
                                <option value="<?= $student['id'] ?>">
                                    <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?> 
                                    (<?= htmlspecialchars($student['email']) ?>) - 
                                    Current Points: <?= number_format($student['points'] ?? 0) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="points" class="form-label">Points to Add/Subtract</label>
                            <input type="number" class="form-control" id="points" name="points" required>
                            <div class="form-text">Use positive number to add points, negative to subtract.</div>
                        </div>
                        <div class="mb-3">
                            <label for="action_description" class="form-label">Action Description</label>
                            <input type="text" class="form-control" id="action_description" name="action_description" required 
                                   placeholder="E.g., Manual Adjustment, Contest Winner">
                        </div>
                        <div class="mb-3">
                            <label for="action_details" class="form-label">Details (Optional)</label>
                            <textarea class="form-control" id="action_details" name="action_details" rows="2"></textarea>
                        </div>
                        <div class="text-center">
                            <button type="submit" name="adjust_points" class="btn btn-primary">Adjust Points</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Top Users Card -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top 10 Users by Points</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Student</th>
                                    <th>Level</th>
                                    <th>Points</th>
                                    <th>Badges</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_users as $key => $user): ?>
                                <tr>
                                    <td><?= $key + 1 ?></td>
                                    <td>
                                        <a href="../users/view.php?id=<?= $user['id'] ?>">
                                            <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                        </a>
                                        <div class="small text-muted"><?= htmlspecialchars($user['department'] ?: 'N/A') ?></div>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill bg-primary">Level <?= $user['level'] ?? 1 ?></span>
                                    </td>
                                    <td><?= number_format($user['points'] ?? 0) ?></td>
                                    <td>
                                        <?= $user['badge_count'] ?> 
                                        <a href="../users/view.php?id=<?= $user['id'] ?>#badges" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-award"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($top_users)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No users found with points</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Point History Card -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Point Activity</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Points</th>
                                    <th>Action</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($point_history as $history): ?>
                                <tr>
                                    <td>
                                        <a href="../users/view.php?id=<?= $history['user_id'] ?>">
                                            <?= htmlspecialchars($history['first_name'] . ' ' . $history['last_name']) ?>
                                        </a>
                                    </td>
                                    <td class="<?= $history['points'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= $history['points'] > 0 ? '+' : '' ?><?= $history['points'] ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($history['action']) ?>
                                        <?php if ($history['description']): ?>
                                        <span class="d-inline-block" data-bs-toggle="tooltip" title="<?= htmlspecialchars($history['description']) ?>">
                                            <i class="fas fa-info-circle"></i>
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M j, Y g:i A', strtotime($history['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($point_history)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">No point history available</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>