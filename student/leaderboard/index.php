<?php
// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db-connection.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    // Redirect to login page if not logged in
    header("Location: ../../login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['id'];

// Get leaderboard type
$type = isset($_GET['type']) ? filter_var($_GET['type'], FILTER_SANITIZE_STRING) : 'overall';

// Get leaderboard data
$pdo = getDbConnection();

switch ($type) {
    case 'monthly':
        // Monthly leaderboard - current month
        $sql = "
            SELECT u.id, u.first_name, u.last_name, u.profile_image, u.department,
                   SUM(ph.points) as total_points,
                   up.level,
                   COUNT(DISTINCT r.id) as uploaded_resources,
                   (SELECT COUNT(*) FROM resource_comments WHERE user_id = u.id) as comments,
                   (u.id = ?) as is_current_user
            FROM users u
            JOIN user_points up ON u.id = up.user_id
            LEFT JOIN points_history ph ON u.id = ph.user_id AND MONTH(ph.created_at) = MONTH(CURRENT_DATE()) AND YEAR(ph.created_at) = YEAR(CURRENT_DATE())
            LEFT JOIN resources r ON u.id = r.user_id AND r.status = 'approved' AND MONTH(r.created_at) = MONTH(CURRENT_DATE()) AND YEAR(r.created_at) = YEAR(CURRENT_DATE())
            WHERE u.role = 'student'
            GROUP BY u.id
            ORDER BY total_points DESC, uploaded_resources DESC, u.first_name
            LIMIT 50
        ";
        $title = "Monthly Leaderboard - " . date('F Y');
        break;

    case 'department':
        // Department leaderboard - by user's department
        $sql = "
            SELECT u.id, u.first_name, u.last_name, u.profile_image, u.department,
                   up.points as total_points,
                   up.level,
                   (SELECT COUNT(*) FROM resources WHERE user_id = u.id AND status = 'approved') as uploaded_resources,
                   (SELECT COUNT(*) FROM resource_comments WHERE user_id = u.id) as comments,
                   (u.id = ?) as is_current_user
            FROM users u
            JOIN user_points up ON u.id = up.user_id
            WHERE u.role = 'student' AND u.department = (SELECT department FROM users WHERE id = ?)
            ORDER BY total_points DESC, uploaded_resources DESC, u.first_name
            LIMIT 50
        ";
        $title = "Department Leaderboard - " . ($_SESSION['department'] ?? 'Your Department');
        break;

    default:
        // Overall leaderboard
        $sql = "
            SELECT u.id, u.first_name, u.last_name, u.profile_image, u.department,
                   up.points as total_points,
                   up.level,
                   (SELECT COUNT(*) FROM resources WHERE user_id = u.id AND status = 'approved') as uploaded_resources,
                   (SELECT COUNT(*) FROM resource_comments WHERE user_id = u.id) as comments,
                   (u.id = ?) as is_current_user
            FROM users u
            JOIN user_points up ON u.id = up.user_id
            WHERE u.role = 'student'
            ORDER BY total_points DESC, uploaded_resources DESC, u.first_name
            LIMIT 50
        ";
        $title = "Overall Leaderboard";
        break;
}

// Execute query
if ($type === 'department') {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $user_id]);
} else {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
}
$leaderboard = $stmt->fetchAll();

// Get user's rank
if ($type === 'overall') {
    $rank_sql = "
        SELECT user_rank
        FROM (
            SELECT u.id, RANK() OVER (ORDER BY up.points DESC, 
                   (SELECT COUNT(*) FROM resources WHERE user_id = u.id AND status = 'approved') DESC) as user_rank
            FROM users u
            JOIN user_points up ON u.id = up.user_id
            WHERE u.role = 'student'
        ) ranked
        WHERE id = ?
    ";
} elseif ($type === 'monthly') {
    $rank_sql = "
        SELECT user_rank
        FROM (
            SELECT u.id, RANK() OVER (ORDER BY SUM(ph.points) DESC, 
                   COUNT(DISTINCT r.id) DESC) as user_rank
            FROM users u
            JOIN user_points up ON u.id = up.user_id
            LEFT JOIN points_history ph ON u.id = ph.user_id AND MONTH(ph.created_at) = MONTH(CURRENT_DATE()) AND YEAR(ph.created_at) = YEAR(CURRENT_DATE())
            LEFT JOIN resources r ON u.id = r.user_id AND r.status = 'approved' AND MONTH(r.created_at) = MONTH(CURRENT_DATE()) AND YEAR(r.created_at) = YEAR(CURRENT_DATE())
            WHERE u.role = 'student'
            GROUP BY u.id
        ) ranked
        WHERE id = ?
    ";
} else {
    $rank_sql = "
        SELECT user_rank
        FROM (
            SELECT u.id, RANK() OVER (ORDER BY up.points DESC, 
                   (SELECT COUNT(*) FROM resources WHERE user_id = u.id AND status = 'approved') DESC) as user_rank
            FROM users u
            JOIN user_points up ON u.id = up.user_id
            WHERE u.role = 'student' AND u.department = (SELECT department FROM users WHERE id = ?)
        ) ranked
        WHERE id = ?
    ";
}

// Get user's rank
try {
    if ($type === 'department') {
        $stmt = $pdo->prepare($rank_sql);
        $stmt->execute([$user_id, $user_id]);
    } else {
        $stmt = $pdo->prepare($rank_sql);
        $stmt->execute([$user_id]);
    }
    $user_rank = $stmt->fetchColumn();
} catch (PDOException $e) {
    $user_rank = 'N/A';
}

// Get user's points and level
$stmt = $pdo->prepare("
    SELECT points, level FROM user_points WHERE user_id = ?
");
$stmt->execute([$user_id]);
$user_stats = $stmt->fetch();

// Set the nested variable to true for header/footer path resolution
$nested = true;

// Set page title
$page_title = $title;

// Include header (which will include the sidebar)
include_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= $title ?></h1>
    </div>

    <!-- User Stats Card -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-xl-4 col-md-4 mb-4 mb-xl-0">
                    <div class="text-center">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Your Rank</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">#<?= htmlspecialchars($user_rank) ?></div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-4 mb-4 mb-xl-0">
                    <div class="text-center">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Total Points</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($user_stats['points']) ?>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-4">
                    <div class="text-center">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Level <?= $user_stats['level'] ?>
                        </div>
                        <?php
                        // Calculate progress to next level
                        $points_for_current_level = pow($user_stats['level'], 2) * 100;
                        $points_for_next_level = pow($user_stats['level'] + 1, 2) * 100;
                        $points_needed = $points_for_next_level - $points_for_current_level;
                        $current_progress = $user_stats['points'] - $points_for_current_level;
                        $points_remaining = $points_needed - $current_progress;
                        $progress_percentage = min(100, ($current_progress / $points_needed) * 100);
                        ?>
                        <div class="progress progress-sm mx-auto mb-2" style="max-width: 300px; height: 8px;">
                            <div class="progress-bar bg-success" role="progressbar"
                                style="width: <?= $progress_percentage ?>%" aria-valuenow="<?= $progress_percentage ?>"
                                aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="small text-muted"><?= number_format($points_remaining) ?> more points needed for
                            Level <?= $user_stats['level'] + 1 ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaderboard Nav Tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $type === 'overall' ? 'active' : '' ?>" href="index.php">
                <i class="fas fa-globe-asia me-1"></i> Overall
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $type === 'monthly' ? 'active' : '' ?>" href="index.php?type=monthly">
                <i class="fas fa-calendar-alt me-1"></i> Monthly
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $type === 'department' ? 'active' : '' ?>" href="index.php?type=department">
                <i class="fas fa-building me-1"></i> Department
            </a>
        </li>
    </ul>

    <!-- Leaderboard List -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="80">Rank</th>
                            <th>Student</th>
                            <th class="text-center">Level</th>
                            <th class="text-center">Points</th>
                            <th class="text-center">Resources</th>
                            <th class="text-center">Comments</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($leaderboard)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="fas fa-trophy fa-3x mb-3"></i>
                                    <p>No data available for this leaderboard.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($leaderboard as $index => $user): ?>
                                <tr class="<?= $user['is_current_user'] ? 'table-primary' : '' ?>">
                                    <td class="text-center"><?= $index + 1 ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?= $user['profile_image'] ? htmlspecialchars($user['profile_image']) : '../../assets/img/ui/default-profile.png' ?>"
                                                alt="<?= htmlspecialchars($user['first_name']) ?>" class="rounded-circle me-3"
                                                width="40" height="40">
                                            <div>
                                                <div><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                                </div>
                                                <div class="small text-muted"><?= htmlspecialchars($user['department']) ?></div>
                                            </div>
                                            <?php if ($user['is_current_user']): ?>
                                                <span class="badge bg-primary ms-2">You</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-center"><?= $user['level'] ?></td>
                                    <td class="text-center"><?= number_format($user['total_points']) ?></td>
                                    <td class="text-center"><?= $user['uploaded_resources'] ?></td>
                                    <td class="text-center"><?= $user['comments'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- How Points Work Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">How Points Work</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 class="h6 font-weight-bold"><i class="fas fa-upload text-primary me-2"></i> Resource
                        Contributions</h5>
                    <ul class="small">
                        <li>Upload a resource: <strong><?= POINTS_UPLOAD ?> points</strong></li>
                        <li>Resource download: <strong><?= POINTS_DOWNLOAD ?> point per download</strong></li>
                        <li>Resource rating: <strong>1-5 points per rating</strong></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 class="h6 font-weight-bold"><i class="fas fa-comment-dots text-primary me-2"></i> Community
                        Engagement</h5>
                    <ul class="small">
                        <li>Post a comment: <strong><?= POINTS_COMMENT ?> points</strong></li>
                        <li>Answer a question: <strong><?= POINTS_ANSWER ?> points</strong></li>
                        <li>Receive a like on comment: <strong>1 point</strong></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5 class="h6 font-weight-bold"><i class="fas fa-level-up-alt text-primary me-2"></i> Leveling Up
                    </h5>
                    <ul class="small">
                        <li>Level 1: <strong>0-100 points</strong></li>
                        <li>Level 2: <strong>101-400 points</strong></li>
                        <li>Level 3: <strong>401-900 points</strong></li>
                        <li>Level 4: <strong>901-1600 points</strong></li>
                        <li>Level 5: <strong>1601+ points</strong></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Real-time progress update script -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Function to update the progress bar
        function updateProgressBar() {
            fetch('check_progress.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update points display
                        document.querySelector('.col-xl-4:nth-child(2) .h5').textContent = data.formatted_points;

                        // Update progress bar
                        const progressBar = document.querySelector('.progress-bar');
                        progressBar.style.width = data.progress_percentage + '%';
                        progressBar.setAttribute('aria-valuenow', data.progress_percentage);

                        // Update points needed text
                        document.querySelector('.progress + .small').textContent =
                            data.points_remaining + ' more points needed for Level ' + data.next_level;

                        // Check if level increased
                        const levelText = document.querySelector('.col-xl-4:nth-child(3) .text-xs');
                        if (data.current_level !== parseInt(levelText.textContent.replace('Level ', ''))) {
                            levelText.textContent = 'Level ' + data.current_level;
                            // Add a celebration effect
                            showLevelUpCelebration(data.current_level);
                        }
                    }
                })
                .catch(error => console.error('Error updating progress:', error));
        }

        // Level up celebration function
        function showLevelUpCelebration(level) {
            // Create a temporary celebration div
            const celebration = document.createElement('div');
            celebration.classList.add('level-up-celebration');
            celebration.innerHTML = `
            <div class="level-up-content">
                <i class="fas fa-medal fa-3x text-warning mb-3"></i>
                <h3>Congratulations!</h3>
                <p>You've reached Level ${level}!</p>
            </div>
        `;
            document.body.appendChild(celebration);

            // Remove after animation
            setTimeout(() => {
                celebration.classList.add('fade-out');
                setTimeout(() => celebration.remove(), 1000);
            }, 3000);
        }

        // Update every 30 seconds
        setInterval(updateProgressBar, 30000);

        // Add styles for the celebration
        const style = document.createElement('style');
        style.textContent = `
        .level-up-celebration {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0,0,0,0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            animation: fadeIn 0.5s;
        }
        .level-up-content {
            background-color: white;
            padding: 2rem;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 0 20px rgba(0,0,0,0.3);
        }
        .fade-out {
            animation: fadeOut 1s;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
    `;
        document.head.appendChild(style);
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>