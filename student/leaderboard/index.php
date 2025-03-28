<?php
// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db-connection.php';
require_once '../../includes/functions.php';

// Start session and check if user is logged in
session_start();
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

// Page title
$page_title = $title;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - <?= $page_title ?></title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="../../assets/img/favicon.png" type="image/x-icon">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/css/styles.css">
    
    <style>
        .leaderboard-item {
            transition: transform 0.2s;
            border-left: 4px solid transparent;
        }
        
        .leaderboard-item:hover {
            transform: translateX(5px);
        }
        
        .leaderboard-item.current-user {
            border-left-color: #4e73df;
            background-color: rgba(78, 115, 223, 0.05);
        }
        
        .rank-badge {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .rank-badge.rank-1 {
            background-color: #ffd700;
            color: #212529;
        }
        
        .rank-badge.rank-2 {
            background-color: #c0c0c0;
            color: #212529;
        }
        
        .rank-badge.rank-3 {
            background-color: #cd7f32;
            color: #fff;
        }
        
        .rank-badge.other-rank {
            background-color: #f8f9fc;
            color: #4e73df;
            border: 1px solid #4e73df;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .level-badge {
            display: inline-block;
            width: 24px;
            height: 24px;
            line-height: 24px;
            text-align: center;
            border-radius: 50%;
            background-color: #4e73df;
            color: white;
            font-size: 0.7rem;
            font-weight: bold;
            margin-left: 5px;
        }
        
        .nav-tabs .nav-link.active {
            font-weight: bold;
            color: #4e73df;
            border-color: #4e73df #dee2e6 #fff;
        }
        
        .user-stats-card {
            border-left: 4px solid #4e73df;
        }
        
        .user-stats-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            color: #6c757d;
        }
        
        .user-stats-value {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .level-progress {
            height: 10px;
            border-radius: 5px;
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
                <a class="nav-link" href="../dashboard.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <hr class="sidebar-divider">
            
            <div class="sidebar-heading">
                Resources
            </div>
            
            <li class="nav-item">
                <a class="nav-link" href="../resources/index.php">
                    <i class="fas fa-fw fa-folder"></i>
                    <span>Browse Resources</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="../resources/upload.php">
                    <i class="fas fa-fw fa-file-upload"></i>
                    <span>Upload Resource</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="../resources/my-resources.php">
                    <i class="fas fa-fw fa-list"></i>
                    <span>My Resources</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="../resources/favorites.php">
                    <i class="fas fa-fw fa-star"></i>
                    <span>Favorites</span>
                </a>
            </li>
            
            <hr class="sidebar-divider">
            
            <div class="sidebar-heading">
                Community
            </div>
            
            <li class="nav-item">
                <a class="nav-link" href="../chat/index.php">
                    <i class="fas fa-fw fa-comments"></i>
                    <span>Chat Rooms</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link active" href="index.php">
                    <i class="fas fa-fw fa-trophy"></i>
                    <span>Leaderboard</span>
                </a>
            </li>
            
            <hr class="sidebar-divider">
            
            <div class="sidebar-heading">
                Account
            </div>
            
            <li class="nav-item">
                <a class="nav-link" href="../profile/index.php">
                    <i class="fas fa-fw fa-user"></i>
                    <span>My Profile</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="../profile/badges.php">
                    <i class="fas fa-fw fa-award"></i>
                    <span>My Badges</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="../notifications/index.php">
                    <i class="fas fa-fw fa-bell"></i>
                    <span>Notifications</span>
                </a>
            </li>
            
            <hr class="sidebar-divider">
            
            <li class="nav-item">
                <a class="nav-link" href="../../logout.php">
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
                        <input type="text" class="form-control" placeholder="Search for users..." aria-label="Search">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                
                <div class="navbar-nav ms-auto">
                    <!-- User Information -->
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle user-info" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="d-none d-lg-inline text-gray-600 small me-2"><?= htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) ?></span>
                            <img src="../../assets/img/ui/default-profile.png" alt="Profile Image">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="../profile/index.php"><i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="../profile/edit.php"><i class="fas fa-cogs fa-sm fa-fw me-2 text-gray-400"></i> Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../logout.php"><i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
                </div>
        </nav>
        
        <!-- Page Content -->
        <div class="container-fluid">
            <!-- Page Heading -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800"><?= $title ?></h1>
            </div>
            
            <!-- User Stats Card -->
            <div class="row mb-4">
                <div class="col-lg-12">
                    <div class="card user-stats-card shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
                                    <div class="text-center">
                                        <div class="user-stats-label">Your Rank</div>
                                        <div class="user-stats-value">#<?= htmlspecialchars($user_rank) ?></div>
                                    </div>
                                </div>
                                <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
                                    <div class="text-center">
                                        <div class="user-stats-label">Total Points</div>
                                        <div class="user-stats-value"><?= number_format($user_stats['points']) ?></div>
                                    </div>
                                </div>
                                <div class="col-xl-6">
                                    <div class="user-stats-label">Level <?= $user_stats['level'] ?></div>
                                    <div class="progress level-progress mb-2">
                                        <?php
                                        // Calculate progress to next level (example formula)
                                        $points_for_current_level = pow($user_stats['level'], 2) * 100;
                                        $points_for_next_level = pow($user_stats['level'] + 1, 2) * 100;
                                        $points_needed = $points_for_next_level - $points_for_current_level;
                                        $current_progress = $user_stats['points'] - $points_for_current_level;
                                        $progress_percentage = min(100, ($current_progress / $points_needed) * 100);
                                        ?>
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $progress_percentage ?>%" 
                                             aria-valuenow="<?= $progress_percentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <div class="d-flex justify-content-between small text-muted">
                                        <span>Level <?= $user_stats['level'] ?></span>
                                        <span><?= number_format($current_progress) ?>/<?= number_format($points_needed) ?> points</span>
                                        <span>Level <?= $user_stats['level'] + 1 ?></span>
                                    </div>
                                </div>
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
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 80px;" class="text-center">Rank</th>
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
                                    <tr class="leaderboard-item <?= $user['is_current_user'] ? 'current-user' : '' ?>">
                                        <td class="text-center align-middle">
                                            <?php if ($index < 3): ?>
                                            <div class="rank-badge rank-<?= $index + 1 ?>">
                                                <?= $index + 1 ?>
                                            </div>
                                            <?php else: ?>
                                            <div class="rank-badge other-rank">
                                                <?= $index + 1 ?>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle">
                                            <div class="d-flex align-items-center">
                                                <img src="<?= $user['profile_image'] ? htmlspecialchars($user['profile_image']) : '../../assets/img/ui/default-profile.png' ?>" alt="<?= htmlspecialchars($user['first_name']) ?>" class="user-avatar me-3">
                                                <div>
                                                    <div class="font-weight-bold"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                                                    <div class="small text-muted"><?= htmlspecialchars($user['department']) ?></div>
                                                </div>
                                                <?php if ($user['is_current_user']): ?>
                                                <span class="badge bg-primary ms-2">You</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-center align-middle">
                                            <span class="level-badge"><?= $user['level'] ?></span>
                                        </td>
                                        <td class="text-center align-middle font-weight-bold">
                                            <?= number_format($user['total_points']) ?>
                                        </td>
                                        <td class="text-center align-middle">
                                            <?= $user['uploaded_resources'] ?>
                                        </td>
                                        <td class="text-center align-middle">
                                            <?= $user['comments'] ?>
                                        </td>
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
                            <h5 class="h6 font-weight-bold"><i class="fas fa-upload text-primary me-2"></i> Resource Contributions</h5>
                            <ul class="small">
                                <li>Upload a resource: <strong><?= POINTS_UPLOAD ?> points</strong></li>
                                <li>Resource download: <strong><?= POINTS_DOWNLOAD ?> point per download</strong></li>
                                <li>Resource rating: <strong>1-5 points per rating</strong></li>
                            </ul>
                        </div>
                        <div class="col-md-4 mb-4 mb-md-0">
                            <h5 class="h6 font-weight-bold"><i class="fas fa-comment-dots text-primary me-2"></i> Community Engagement</h5>
                            <ul class="small">
                                <li>Post a comment: <strong><?= POINTS_COMMENT ?> points</strong></li>
                                <li>Answer a question: <strong><?= POINTS_ANSWER ?> points</strong></li>
                                <li>Receive a like on comment: <strong>1 point</strong></li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h5 class="h6 font-weight-bold"><i class="fas fa-level-up-alt text-primary me-2"></i> Leveling Up</h5>
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
        
        <!-- Footer -->
        <footer class="sticky-footer bg-white mt-4">
            <div class="container my-auto">
                <div class="copyright text-center my-auto">
                    <span>&copy; <?= date('Y') ?> PolyEduHub. All rights reserved.</span>
                </div>
            </div>
        </footer>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="../../assets/js/scripts.js"></script>
    
    <script>
        // Toggle sidebar on mobile
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
            document.querySelector('.content').classList.toggle('pushed');
        });
    </script>
</body>
</html>