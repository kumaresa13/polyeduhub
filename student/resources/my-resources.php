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

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'latest';

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query
$params = [$user_id];
$where_clauses = ["r.user_id = ?"];

if ($status !== 'all') {
    $where_clauses[] = "r.status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $where_clauses[] = "(r.title LIKE ? OR r.description LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_sql = implode(' AND ', $where_clauses);

// Sorting
switch ($sort) {
    case 'downloads':
        $order_sql = "r.download_count DESC";
        break;
    case 'rating':
        $order_sql = "avg_rating DESC";
        break;
    case 'oldest':
        $order_sql = "r.created_at ASC";
        break;
    default:
        $order_sql = "r.created_at DESC";
        break;
}

// Get total resource count
$count_sql = "
    SELECT COUNT(*) as total
    FROM resources r
    WHERE {$where_sql}
";

$pdo = getDbConnection();
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_resources = $stmt->fetchColumn();
$total_pages = ceil($total_resources / $per_page);

// Get resources
$sql = "
    SELECT r.id, r.title, r.description, r.file_path, r.file_type, r.file_size, 
           r.thumbnail, r.category_id, r.download_count, r.view_count, r.created_at, r.status,
           rc.name as category_name,
           (SELECT COUNT(*) FROM resource_comments WHERE resource_id = r.id) as comment_count,
           (SELECT COALESCE(AVG(rating), 0) FROM resource_ratings WHERE resource_id = r.id) as avg_rating,
           (SELECT COUNT(*) FROM resource_ratings WHERE resource_id = r.id) as rating_count
    FROM resources r
    JOIN resource_categories rc ON r.category_id = rc.id
    WHERE {$where_sql}
    ORDER BY {$order_sql}
    LIMIT {$per_page} OFFSET {$offset}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$resources = $stmt->fetchAll();

// Get statistics
$stats_sql = "
    SELECT
        COUNT(*) as total_resources,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_resources,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_resources,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_resources,
        SUM(download_count) as total_downloads,
        (SELECT COUNT(DISTINCT resource_id) FROM resource_ratings WHERE resource_id IN (SELECT id FROM resources WHERE user_id = ?)) as rated_resources
    FROM resources
    WHERE user_id = ?
";

$stmt = $pdo->prepare($stats_sql);
$stmt->execute([$user_id, $user_id]);
$stats = $stmt->fetch();

// Page title
$page_title = "My Resources";
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
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/css/styles.css">
    
    <style>
        .status-badge {
            font-size: 0.7rem;
            padding: 0.35em 0.65em;
            text-transform: uppercase;
        }
        
        .status-approved {
            background-color: #1cc88a;
        }
        
        .status-pending {
            background-color: #f6c23e;
        }
        
        .status-rejected {
            background-color: #e74a3b;
        }
        
        .resource-title {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .resource-type-icon {
            font-size: 1.5rem;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: #f8f9fc;
            margin-right: 10px;
        }
        
        .file-pdf {
            color: #e74a3b;
        }
        
        .file-word {
            color: #4e73df;
        }
        
        .file-excel {
            color: #1cc88a;
        }
        
        .file-powerpoint {
            color: #f6c23e;
        }
        
        .star-rating {
            color: #ffc107;
        }
        
        .star-rating .far.fa-star {
            color: #e4e5e9;
        }
        
        .file-size {
            font-size: 0.75rem;
        }
        
        .stats-card {
            border-left: 4px solid #4e73df;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .stats-icon {
            font-size: 2rem;
            color: #dddfeb;
        }
        
        .stats-card.primary {
            border-color: #4e73df;
        }
        
        .stats-card.success {
            border-color: #1cc88a;
        }
        
        .stats-card.warning {
            border-color: #f6c23e;
        }
        
        .stats-card.danger {
            border-color: #e74a3b;
        }
        
        .stats-card.info {
            border-color: #36b9cc;
        }
        
        .filterable {
            margin-top: 15px;
        }
        
        .filterable .panel-heading .pull-right {
            margin-top: -20px;
        }
        
        .filterable .filters input[disabled] {
            background-color: transparent;
            border: none;
            cursor: auto;
            box-shadow: none;
            padding: 0;
            height: auto;
        }
        
        .filterable .filters input[disabled]::-webkit-input-placeholder {
            color: #333;
        }
        
        .filterable .filters input[disabled]::-moz-placeholder {
            color: #333;
        }
        
        .filterable .filters input[disabled]:-ms-input-placeholder {
            color: #333;
        }
        
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 20px;
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
                <a class="nav-link" href="index.php">
                    <i class="fas fa-fw fa-folder"></i>
                    <span>Browse Resources</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="upload.php">
                    <i class="fas fa-fw fa-file-upload"></i>
                    <span>Upload Resource</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link active" href="my-resources.php">
                    <i class="fas fa-fw fa-list"></i>
                    <span>My Resources</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="favorites.php">
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
                <a class="nav-link" href="../leaderboard/index.php">
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
                <form class="navbar-search" method="GET" action="">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search my resources..." value="<?= htmlspecialchars($search) ?>">
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
                <h1 class="h3 mb-0 text-gray-800">My Resources</h1>
                <a href="upload.php" class="d-none d-sm-inline-block btn btn-primary shadow-sm">
                    <i class="fas fa-upload fa-sm text-white-50 me-1"></i> Upload New Resource
                </a>
            </div>
            
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card primary h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Resources
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['total_resources']) ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-folder fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card success h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Approved Resources
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['approved_resources']) ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card warning h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Total Downloads
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['total_downloads']) ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-download fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card info h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Rated Resources
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['rated_resources']) ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-star fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Resource List -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-wrap justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Manage My Resources</h6>
                    
                    <div class="d-flex">
                        <!-- Status Filter -->
                        <div class="btn-group me-2">
                            <a href="my-resources.php<?= !empty($search) ? '?search=' . urlencode($search) : '' ?><?= !empty($search) && $sort !== 'latest' ? '&sort=' . $sort : '' ?>" class="btn btn-sm <?= $status === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">
                                All
                            </a>
                            <a href="my-resources.php?status=approved<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $sort !== 'latest' ? '&sort=' . $sort : '' ?>" class="btn btn-sm <?= $status === 'approved' ? 'btn-success' : 'btn-outline-success' ?>">
                                Approved
                            </a>
                            <a href="my-resources.php?status=pending<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $sort !== 'latest' ? '&sort=' . $sort : '' ?>" class="btn btn-sm <?= $status === 'pending' ? 'btn-warning' : 'btn-outline-warning' ?>">
                                Pending
                            </a>
                            <a href="my-resources.php?status=rejected<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $sort !== 'latest' ? '&sort=' . $sort : '' ?>" class="btn btn-sm <?= $status === 'rejected' ? 'btn-danger' : 'btn-outline-danger' ?>">
                                Rejected
                            </a>
                        </div>
                        
                        <!-- Sort Dropdown -->
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="sortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-sort me-1"></i> 
                                <?php
                                    switch ($sort) {
                                        case 'downloads':
                                            echo 'Most Downloads';
                                            break;
                                        case 'rating':
                                            echo 'Highest Rated';
                                            break;
                                        case 'oldest':
                                            echo 'Oldest First';
                                            break;
                                        default:
                                            echo 'Latest First';
                                            break;
                                    }
                                ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="sortDropdown">
                                <li>
                                    <a class="dropdown-item <?= $sort === 'latest' ? 'active' : '' ?>" href="my-resources.php?<?= $status !== 'all' ? 'status=' . $status . '&' : '' ?><?= !empty($search) ? 'search=' . urlencode($search) . '&' : '' ?>sort=latest">
                                        Latest First
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?= $sort === 'oldest' ? 'active' : '' ?>" href="my-resources.php?<?= $status !== 'all' ? 'status=' . $status . '&' : '' ?><?= !empty($search) ? 'search=' . urlencode($search) . '&' : '' ?>sort=oldest">
                                        Oldest First
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?= $sort === 'downloads' ? 'active' : '' ?>" href="my-resources.php?<?= $status !== 'all' ? 'status=' . $status . '&' : '' ?><?= !empty($search) ? 'search=' . urlencode($search) . '&' : '' ?>sort=downloads">
                                        Most Downloads
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?= $sort === 'rating' ? 'active' : '' ?>" href="my-resources.php?<?= $status !== 'all' ? 'status=' . $status . '&' : '' ?><?= !empty($search) ? 'search=' . urlencode($search) . '&' : '' ?>sort=rating">
                                        Highest Rated
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($resources)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-folder-open fa-4x text-gray-300 mb-3"></i>
                        <p class="mb-0 text-gray-500">No resources found. Upload your first resource to get started!</p>
                        <a href="upload.php" class="btn btn-primary mt-3">
                            <i class="fas fa-upload me-1"></i> Upload New Resource
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered" id="resourcesTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Resource</th>
                                    <th>Category</th>
                                    <th>Uploaded</th>
                                    <th>Status</th>
                                    <th>Stats</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resources as $resource): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php
                                            // Select icon based on file type
                                            $icon_class = 'fa-file';
                                            $type_class = '';
                                            switch ($resource['file_type']) {
                                                case 'pdf':
                                                    $icon_class = 'fa-file-pdf';
                                                    $type_class = 'file-pdf';
                                                    break;
                                                case 'doc':
                                                case 'docx':
                                                    $icon_class = 'fa-file-word';
                                                    $type_class = 'file-word';
                                                    break;
                                                case 'xls':
                                                case 'xlsx':
                                                    $icon_class = 'fa-file-excel';
                                                    $type_class = 'file-excel';
                                                    break;
                                                case 'ppt':
                                                case 'pptx':
                                                    $icon_class = 'fa-file-powerpoint';
                                                    $type_class = 'file-powerpoint';
                                                    break;
                                                case 'zip':
                                                case 'rar':
                                                    $icon_class = 'fa-file-archive';
                                                    break;
                                                case 'jpg':
                                                case 'jpeg':
                                                case 'png':
                                                case 'gif':
                                                    $icon_class = 'fa-file-image';
                                                    break;
                                            }
                                            ?>
                                            <div class="resource-type-icon">
                                                <i class="fas <?= $icon_class ?> <?= $type_class ?>"></i>
                                            </div>
                                            <div>
                                                <div class="resource-title font-weight-bold">
                                                    <a href="view.php?id=<?= $resource['id'] ?>" class="text-decoration-none text-gray-800">
                                                        <?= htmlspecialchars($resource['title']) ?>
                                                    </a>
                                                </div>
                                                <div class="file-size text-muted">
                                                    <?= strtoupper($resource['file_type']) ?> • <?= formatFileSize($resource['file_size']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($resource['category_name']) ?></td>
                                    <td><?= date('M d, Y', strtotime($resource['created_at'])) ?></td>
                                    <td>
                                        <?php if ($resource['status'] === 'approved'): ?>
                                        <span class="badge status-badge status-approved">Approved</span>
                                        <?php elseif ($resource['status'] === 'pending'): ?>
                                        <span class="badge status-badge status-pending">Pending</span>
                                        <?php else: ?>
                                        <span class="badge status-badge status-rejected">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-3" title="Downloads">
                                                <i class="fas fa-download text-primary me-1"></i> <?= $resource['download_count'] ?>
                                            </div>
                                            <div title="Ratings">
                                                <div class="star-rating">
                                                    <?php
                                                    $rating = round($resource['avg_rating'] * 2) / 2; // Round to nearest 0.5
                                                    for ($i = 1; $i <= 5; $i++) {
                                                        if ($rating >= $i) {
                                                            echo '<i class="fas fa-star"></i>';
                                                        } elseif ($rating >= $i - 0.5) {
                                                            echo '<i class="fas fa-star-half-alt"></i>';
                                                        } else {
                                                            echo '<i class="far fa-star"></i>';
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                                <small class="text-muted">(<?= $resource['rating_count'] ?>)</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view.php?id=<?= $resource['id'] ?>" class="btn btn-sm btn-outline-primary" title="View Resource">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($resource['status'] === 'approved'): ?>
                                            <a href="download.php?id=<?= $resource['id'] ?>" class="btn btn-sm btn-outline-success" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <a href="edit.php?id=<?= $resource['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <button type="button" class="btn btn-sm btn-outline-danger delete-resource" 
                                                    data-resource-id="<?= $resource['id'] ?>" 
                                                    data-resource-title="<?= htmlspecialchars($resource['title']) ?>"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page - 1 ?><?= $status !== 'all' ? '&status=' . $status : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $sort !== 'latest' ? '&sort=' . $sort : '' ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php
                                // Show limited page numbers with ellipsis
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1' . ($status !== 'all' ? '&status=' . $status : '') . (!empty($search) ? '&search=' . urlencode($search) : '') . ($sort !== 'latest' ? '&sort=' . $sort : '') . '">1</a></li>';
                                    if ($start_page > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">
                                        <a class="page-link" href="?page=' . $i . ($status !== 'all' ? '&status=' . $status : '') . (!empty($search) ? '&search=' . urlencode($search) : '') . ($sort !== 'latest' ? '&sort=' . $sort : '') . '">' . $i . '</a>
                                    </li>';
                                }
                                
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . ($status !== 'all' ? '&status=' . $status : '') . (!empty($search) ? '&search=' . urlencode($search) : '') . ($sort !== 'latest' ? '&sort=' . $sort : '') . '">' . $total_pages . '</a></li>';
                                }
                                ?>
                                
                                <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1 ?><?= $status !== 'all' ? '&status=' . $status : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $sort !== 'latest' ? '&sort=' . $sort : '' ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Resource Tips Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Tips for Better Resources</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-4 mb-md-0">
                            <h5 class="h6 font-weight-bold"><i class="fas fa-lightbulb text-warning me-2"></i> Quality Content</h5>
                            <ul class="small">
                                <li>Ensure your content is accurate and up-to-date</li>
                                <li>Use clear, concise language and organization</li>
                                <li>Include examples, diagrams, or visual aids when possible</li>
                                <li>Proofread for grammar and spelling errors</li>
                            </ul>
                        </div>
                        <div class="col-md-4 mb-4 mb-md-0">
                            <h5 class="h6 font-weight-bold"><i class="fas fa-tag text-primary me-2"></i> Effective Descriptions</h5>
                            <ul class="small">
                                <li>Write detailed, informative descriptions</li>
                                <li>Include the course or subject the material relates to</li>
                                <li>Mention what problems the resource helps solve</li>
                                <li>Use relevant keywords for better searchability</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h5 class="h6 font-weight-bold"><i class="fas fa-award text-success me-2"></i> Earn More Points</h5>
                            <ul class="small">
                                <li>Upload high-quality resources that get downloaded often</li>
                                <li>Respond to comments and questions on your resources</li>
                                <li>Create resources for subjects with less content</li>
                                <li>Update resources regularly to keep them relevant</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Delete Resource Modal -->
        <div class="modal fade" id="deleteResourceModal" tabindex="-1" aria-labelledby="deleteResourceModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteResourceModalLabel">Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete the resource "<span id="resourceTitleToDelete"></span>"?</p>
                        <p class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i> This action cannot be undone!</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete Resource</a>
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
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Custom JS -->
    <script src="../../assets/js/scripts.js"></script>
    
    <script>
        // Toggle sidebar on mobile
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
            document.querySelector('.content').classList.toggle('pushed');
        });
        
        // Handle delete resource modal
        document.querySelectorAll('.delete-resource').forEach(btn => {
            btn.addEventListener('click', function() {
                const resourceId = this.getAttribute('data-resource-id');
                const resourceTitle = this.getAttribute('data-resource-title');
                
                document.getElementById('resourceTitleToDelete').textContent = resourceTitle;
                document.getElementById('confirmDeleteBtn').href = 'delete_resource.php?id=' + resourceId;
                
                const deleteModal = new bootstrap.Modal(document.getElementById('deleteResourceModal'));
                deleteModal.show();
            });
        });
        
        // Initialize DataTable if needed (optional, since we're handling pagination on the server)
        // $('#resourcesTable').DataTable({
        //     paging: false,
        //     searching: false,
        //     info: false
        // });
    </script>
</body>
</html>