<?php
// Start session and include necessary files
session_start();

// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db-connection.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../../login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';

// Page title
$page_title = "Browse Resources";

// Get categories
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT id, name FROM resource_categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
    // Get resource counts for each category
    foreach ($categories as $key => $category) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM resources 
            WHERE category_id = ? AND status = 'approved'
        ");
        $stmt->execute([$category['id']]);
        $categories[$key]['count'] = $stmt->fetchColumn();
    }
    
    // Get total resources
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM resources WHERE status = 'approved'");
    $stmt->execute();
    $total_resources = $stmt->fetchColumn();
    
    // Get total downloads
    $stmt = $pdo->prepare("SELECT SUM(download_count) FROM resources WHERE status = 'approved'");
    $stmt->execute();
    $total_downloads = $stmt->fetchColumn() ?: 0;
    
    // Check if favorites table exists
    $checkTableStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
        AND table_name = 'resource_favorites'
    ");
    $checkTableStmt->execute();
    $tableExists = (bool)$checkTableStmt->fetchColumn();
    
    if ($tableExists) {
        // Get user's favorites
        $stmt = $pdo->prepare("
            SELECT r.id 
            FROM resources r
            JOIN resource_favorites rf ON r.id = rf.resource_id
            WHERE rf.user_id = ? AND r.status = 'approved'
        ");
        $stmt->execute([$user_id]);
        $favorite_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        // Favorites table doesn't exist, use empty array
        $favorite_ids = [];
    }
    
    // Get resources based on filters
    $category_filter = isset($_GET['category']) ? intval($_GET['category']) : null;
    $sort_filter = isset($_GET['sort']) ? $_GET['sort'] : 'latest';
    $search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Build query
    $query = "
        SELECT r.id, r.title, r.description, r.file_type, r.created_at, r.download_count, r.view_count,
               u.first_name, u.last_name, rc.name as category_name
        FROM resources r
        JOIN users u ON r.user_id = u.id
        JOIN resource_categories rc ON r.category_id = rc.id
        WHERE r.status = 'approved'
    ";
    
    $params = [];
    
    // Add category filter
    if ($category_filter) {
        $query .= " AND r.category_id = ?";
        $params[] = $category_filter;
    }
    
    // Add search filter
    if (!empty($search_term)) {
        $query .= " AND (r.title LIKE ? OR r.description LIKE ?)";
        $params[] = "%$search_term%";
        $params[] = "%$search_term%";
    }
    
    // Add sorting
    if ($sort_filter === 'latest') {
        $query .= " ORDER BY r.created_at DESC";
    } elseif ($sort_filter === 'oldest') {
        $query .= " ORDER BY r.created_at ASC";
    } elseif ($sort_filter === 'downloads') {
        $query .= " ORDER BY r.download_count DESC";
    } elseif ($sort_filter === 'rating') {
        $query .= " ORDER BY (SELECT AVG(rating) FROM resource_ratings WHERE resource_id = r.id) DESC";
    }
    
    // Execute query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $resources = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error in resources index: " . $e->getMessage());
    $categories = [];
    $resources = [];
    $total_resources = 0;
    $total_downloads = 0;
    $favorite_ids = [];
}

// Helper function to build sort URLs while preserving other parameters
function buildSortUrl($sort) {
    $params = $_GET;
    $params['sort'] = $sort;
    return 'index.php?' . http_build_query($params);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - PolyEduHub</title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="../../assets/img/favicon.png" type="image/x-icon">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #4e73df;
            --secondary: #858796;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --light: #f8f9fc;
            --dark: #5a5c69;
        }
        
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f8f9fc;
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 14rem;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 100;
            background-color: var(--primary);
            background-image: linear-gradient(180deg, var(--primary) 10%, #224abe 100%);
            overflow-y: auto;
            transition: width 0.15s ease-in-out;
        }
        
        .sidebar-brand {
            padding: 1.5rem 1rem;
            text-align: center;
            color: white;
        }
        
        .sidebar-divider {
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            margin: 0 1rem;
        }
        
        .sidebar-heading {
            padding: 0.5rem 1rem;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.4);
        }
        
        .nav-item {
            padding: 0 0.75rem;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: rgba(255, 255, 255, 0.8) !important;
            font-weight: 600;
            transition: all 0.15s ease-in-out;
            border-radius: 0.35rem;
        }
        
        .nav-link:hover {
            color: #fff !important;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .nav-link.active {
            color: #fff !important;
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .nav-link i {
            margin-right: 0.5rem;
            opacity: 0.85;
            width: 1.2rem;
            text-align: center;
        }
        
        /* Main content */
        .content-wrapper {
            flex: 1;
            margin-left: 14rem;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .content {
            flex: 1;
            padding: 1.5rem;
        }
        
        /* Topbar */
        .topbar {
            background-color: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .topbar .navbar-search {
            width: 25rem;
        }
        
        .topbar .user-dropdown img {
            height: 2rem;
            width: 2rem;
        }
        
        /* Card styles */
        .card {
            margin-bottom: 1.5rem;
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border-radius: 0.35rem;
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 0.75rem 1.25rem;
        }
        
        .category-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: #3a3b45;
            border-radius: 0.35rem;
            transition: all 0.15s ease;
            margin-bottom: 0.5rem;
        }
        
        .category-item:hover {
            background-color: #eaecf4;
            text-decoration: none;
        }
        
        .category-item.active {
            background-color: var(--primary);
            color: white;
        }
        
        .category-count {
            margin-left: auto;
            background-color: #eaecf4;
            color: var(--primary);
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.35rem;
        }
        
        .category-item.active .category-count {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        /* Resource card */
        .resource-card {
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            height: 100%;
        }
        
        .resource-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 0.25rem 2rem 0 rgba(58, 59, 69, 0.25);
        }
        
        .resource-file-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            width: 4rem;
            height: 4rem;
            background-color: #f8f9fc;
            color: var(--primary);
            border-radius: 0.35rem;
            margin-bottom: 1rem;
        }
        
        /* Sort options */
        .sort-option {
            cursor: pointer;
            padding: 0.5rem 1rem;
            border-radius: 0.35rem;
        }
        
        .sort-option:hover {
            background-color: #eaecf4;
        }
        
        .sort-option.active {
            background-color: var(--primary);
            color: white;
        }
        
        /* Footer */
        footer {
            padding: 1.5rem;
            background-color: white;
            border-top: 1px solid #e3e6f0;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.toggled {
                transform: translateX(0);
                width: 100%;
            }
            
            .content-wrapper {
                margin-left: 0;
            }
            
            .topbar .navbar-search {
                width: auto;
            }
            
            .collapse-inner {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <ul class="sidebar navbar-nav">
        <div class="sidebar-brand">
            <i class="fas fa-graduation-cap fa-2x mb-2"></i>
            <div>PolyEduHub</div>
        </div>
        
        <hr class="sidebar-divider">
        
        <div class="sidebar-heading">Navigation</div>
        <li class="nav-item">
            <a class="nav-link" href="../dashboard.php">
                <i class="fas fa-fw fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>
        
        <hr class="sidebar-divider">
        
        <div class="sidebar-heading">Resources</div>
        <li class="nav-item">
            <a class="nav-link active" href="index.php">
                <i class="fas fa-fw fa-folder"></i>
                <span>Browse Resources</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="upload.php">
                <i class="fas fa-fw fa-upload"></i>
                <span>Upload Resource</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="my-resources.php">
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
        
        <div class="sidebar-heading">Community</div>
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
        
        <div class="sidebar-heading">Account</div>
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
        <li class="nav-item">
            <a class="nav-link" href="../../logout.php">
                <i class="fas fa-fw fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Topbar -->
        <nav class="navbar navbar-expand navbar-light topbar mb-4 static-top shadow">
            <!-- Sidebar Toggle (Topbar) -->
            <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                <i class="fa fa-bars"></i>
            </button>

            <!-- Topbar Search -->
            <form class="d-none d-sm-inline-block form-inline navbar-search" action="index.php" method="GET">
                <div class="input-group">
                    <input type="text" class="form-control bg-light border-0 small" name="search" placeholder="Search for resources..." 
                           value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                    <div class="input-group-append">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search fa-sm"></i>
                        </button>
                    </div>
                </div>
            </form>

            <!-- Topbar Navbar -->
            <ul class="navbar-nav ml-auto">
                <div class="topbar-divider d-none d-sm-block"></div>

                <!-- Nav Item - User Information -->
                <li class="nav-item dropdown no-arrow">
                    <a class="nav-link dropdown-toggle user-dropdown" href="#" id="userDropdown" role="button"
                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="mr-2 d-none d-lg-inline text-gray-600 small me-2"><?= htmlspecialchars("$first_name $last_name") ?></span>
                        <img class="img-profile rounded-circle" src="../../assets/img/ui/default-profile.png">
                    </a>
                    <!-- Dropdown - User Information -->
                    <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in" aria-labelledby="userDropdown">
                        <a class="dropdown-item" href="../profile/index.php">
                            <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                            Profile
                        </a>
                        <a class="dropdown-item" href="../profile/edit.php">
                            <i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i>
                            Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="../../logout.php">
                            <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                            Logout
                        </a>
                    </div>
                </li>
            </ul>
        </nav>
        <!-- End of Topbar -->

        <!-- Begin Page Content -->
        <div class="content">
            <div class="container-fluid">
                <!-- Page Heading -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Browse Resources</h1>
                    <a href="upload.php" class="d-none d-sm-inline-block btn btn-primary shadow-sm">
                        <i class="fas fa-upload fa-sm text-white-50"></i> Upload New Resource
                    </a>
                </div>

                <div class="row">
                    <!-- Categories Column -->
                    <div class="col-lg-3">
                        <!-- Categories Card -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-filter me-2"></i> Categories
                                </h6>
                            </div>
                            <div class="card-body">
                                <a href="index.php" class="category-item <?= !isset($_GET['category']) ? 'active' : '' ?>">
                                    All Categories
                                    <span class="category-count"><?= $total_resources ?></span>
                                </a>
                                
                                <?php foreach ($categories as $category): ?>
                                <a href="index.php?category=<?= $category['id'] ?>" 
                                   class="category-item <?= isset($_GET['category']) && $_GET['category'] == $category['id'] ? 'active' : '' ?>">
                                    <?= htmlspecialchars($category['name']) ?>
                                    <span class="category-count"><?= $category['count'] ?></span>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Sort By Card -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-sort me-2"></i> Sort By
                                </h6>
                            </div>
                            <div class="card-body">
                                <a href="<?= buildSortUrl('latest') ?>" 
                                   class="sort-option d-block mb-2 <?= (!isset($_GET['sort']) || $_GET['sort'] == 'latest') ? 'active' : '' ?>">
                                   <i class="fas fa-calendar me-2"></i> Latest First
                                </a>
                                <a href="<?= buildSortUrl('oldest') ?>" 
                                   class="sort-option d-block mb-2 <?= isset($_GET['sort']) && $_GET['sort'] == 'oldest' ? 'active' : '' ?>">
                                   <i class="fas fa-calendar-alt me-2"></i> Oldest First
                                </a>
                                <a href="<?= buildSortUrl('rating') ?>" 
                                   class="sort-option d-block mb-2 <?= isset($_GET['sort']) && $_GET['sort'] == 'rating' ? 'active' : '' ?>">
                                   <i class="fas fa-star me-2"></i> Highest Rated
                                </a>
                                <a href="<?= buildSortUrl('downloads') ?>" 
                                   class="sort-option d-block <?= isset($_GET['sort']) && $_GET['sort'] == 'downloads' ? 'active' : '' ?>">
                                   <i class="fas fa-download me-2"></i> Most Downloaded
                                </a>
                            </div>
                        </div>
                        
                        <!-- Resource Statistics Card -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-chart-pie me-2"></i> Resource Statistics
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6 border-right">
                                        <div class="h3 font-weight-bold text-primary"><?= number_format($total_resources) ?></div>
                                        <div class="small text-gray-500">Total Resources</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="h3 font-weight-bold text-success"><?= number_format($total_downloads) ?></div>
                                        <div class="small text-gray-500">Total Downloads</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Resources Column -->
                    <div class="col-lg-9">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <?php
                                    if (isset($_GET['category'])) {
                                        foreach ($categories as $category) {
                                            if ($category['id'] == $_GET['category']) {
                                                echo htmlspecialchars($category['name'] . ' Resources');
                                                break;
                                            }
                                        }
                                    } else {
                                        echo 'All Resources';
                                    }
                                    ?>
                                </h6>
                                <div class="small text-gray-600">
                                    Showing <?= count($resources) ?> of <?= $total_resources ?> resources
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($resources)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-search fa-4x text-gray-300 mb-3"></i>
                                    <p class="text-gray-600">No resources found. Try adjusting your search criteria.</p>
                                    <?php if (!empty($_GET['search']) || isset($_GET['category']) || isset($_GET['sort'])): ?>
                                    <a href="index.php" class="btn btn-outline-primary">Clear filters</a>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <div class="row">
                                    <?php foreach ($resources as $resource): ?>
                                    <div class="col-lg-4 col-md-6 mb-4">
                                        <div class="card resource-card h-100">
                                            <div class="card-body text-center">
                                                <div class="resource-file-icon mx-auto">
                                                    <?php 
                                                    $icon_class = "fas fa-file";
                                                    switch (strtolower($resource['file_type'])) {
                                                        case 'pdf': $icon_class = "fas fa-file-pdf"; break;
                                                        case 'doc': case 'docx': $icon_class = "fas fa-file-word"; break;
                                                        case 'xls': case 'xlsx': $icon_class = "fas fa-file-excel"; break;
                                                        case 'ppt': case 'pptx': $icon_class = "fas fa-file-powerpoint"; break;
                                                        case 'jpg': case 'jpeg': case 'png': $icon_class = "fas fa-file-image"; break;
                                                        case 'zip': case 'rar': $icon_class = "fas fa-file-archive"; break;
                                                        case 'txt': $icon_class = "fas fa-file-alt"; break;
                                                    }
                                                    ?>
                                                    <i class="<?= $icon_class ?>"></i>
                                                </div>
                                                <h5 class="card-title"><?= htmlspecialchars($resource['title']) ?></h5>
                                                <p class="small text-gray-600 mb-2">
                                                    <i class="fas fa-tag me-1"></i> <?= htmlspecialchars($resource['category_name']) ?>
                                                </p>
                                                <p class="small text-gray-600 mb-2">
                                                    <i class="fas fa-user me-1"></i> <?= htmlspecialchars($resource['first_name'] . ' ' . $resource['last_name']) ?>
                                                </p>
                                                <div class="d-flex justify-content-between text-gray-600 small mb-3">
                                                    <span><i class="fas fa-download me-1"></i> <?= $resource['download_count'] ?></span>
                                                    <span><i class="fas fa-calendar me-1"></i> <?= date('M j, Y', strtotime($resource['created_at'])) ?></span>
                                                </div>
                                                <a href="view.php?id=<?= $resource['id'] ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye me-1"></i> View Details
                                                </a>
                                                <a href="download.php?id=<?= $resource['id'] ?>" class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-download me-1"></i> Download
                                                </a>
                                            </div>
                                            <div class="card-footer py-3 d-flex justify-content-center">
                                                <a href="#" class="text-warning mx-2" title="Rate Resource">
                                                    <i class="fas fa-star"></i>
                                                </a>
                                                <a href="toggle_favorite.php?id=<?= $resource['id'] ?>" class="text-danger mx-2" title="Add to Favorites">
                                                    <i class="<?= in_array($resource['id'], $favorite_ids) ? 'fas' : 'far' ?> fa-heart"></i>
                                                </a>
                                                <a href="view.php?id=<?= $resource['id'] ?>#comments" class="text-primary mx-2" title="Add Comment">
                                                    <i class="far fa-comment"></i>
                                                </a>
                                            </div>
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
        </div>
        <!-- End of Page Content -->

        <!-- Footer -->
        <footer class="sticky-footer bg-white">
            <div class="container my-auto">
                <div class="copyright text-center my-auto">
                    <span>&copy; <?= date('Y') ?> PolyEduHub. All rights reserved.</span>
                </div>
            </div>
        </footer>
        <!-- End of Footer -->
    </div>
    <!-- End of Content Wrapper -->

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Toggle sidebar on mobile
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggleTop = document.getElementById('sidebarToggleTop');
            const sidebar = document.querySelector('.sidebar');
            
            if (sidebarToggleTop) {
                sidebarToggleTop.addEventListener('click', function() {
                    sidebar.classList.toggle('toggled');
                });
            }
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth < 768 && 
                    sidebar.classList.contains('toggled') && 
                    !sidebar.contains(event.target) && 
                    event.target !== sidebarToggleTop) {
                    sidebar.classList.remove('toggled');
                }
            });
            
            // Handle sorting and filtering
            const sortLinks = document.querySelectorAll('.sort-option');
            sortLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.location.href = this.getAttribute('href');
                });
            });
        });
        
        // Helper function to build sort URLs
        function buildSortUrl(sort) {
            const url = new URL(window.location.href);
            url.searchParams.set('sort', sort);
            return url.toString();
        }
    </script>
</body>
</html>