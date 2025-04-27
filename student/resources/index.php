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
$user_id = $_SESSION['id'];

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

// Set page title
$page_title = "Browse Resources";

// Custom CSS for this page
$additional_styles = '
<style>
    /* Categories and Sort filters styling */
    .filter-card {
        background-color: #f8f9fa;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        margin-bottom: 20px;
        overflow: hidden;
        padding: 15px;
    }
    
    .filter-header {
        color: #4361ee;
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
    }
    
    .filter-header i {
        margin-right: 8px;
    }
    
    .category-item, .sort-option {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 15px;
        margin-bottom: 5px;
        border-radius: 6px;
        text-decoration: none;
        color: #333;
        font-weight: 500;
        transition: all 0.2s;
    }
    
    .category-item.active {
        background-color: #4361ee;
        color: white;
    }
    
    .category-item:not(.active):hover, .sort-option:not(.active):hover {
        background-color: #e9ecef;
    }
    
    .sort-option {
        color: #4361ee;
    }
    
    .sort-option.active {
        background-color: #4361ee;
        color: white;
    }
    
    .category-count {
        background-color: rgba(255, 255, 255, 0.2);
        border-radius: 50px;
        padding: 2px 8px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .category-item:not(.active) .category-count {
        background-color: #e9ecef;
        color: #4361ee;
    }
    
    /* Resource cards styling */
    .resource-card {
        transition: transform 0.2s, box-shadow 0.2s;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .resource-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }
    
    .resource-file-icon {
        width: 80px;
        height: 80px;
        margin: 20px auto;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        color: #4361ee;
    }
</style>
';

// Set nested variable to true to handle proper path in header/footer
$nested = true;

// Include the standard header (which includes the sidebar)
include_once '../includes/header.php';
?>

<!-- Page Content -->
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
            <div class="filter-card">
                <div class="filter-header">
                    <i class="fas fa-filter"></i> Categories
                </div>
                <div>
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
            <div class="filter-card">
                <div class="filter-header">
                    <i class="fas fa-sort"></i> Sort By
                </div>
                <div>
                    <a href="<?= buildSortUrl('latest') ?>" 
                       class="sort-option <?= (!isset($_GET['sort']) || $_GET['sort'] == 'latest') ? 'active' : '' ?>">
                       <i class="fas fa-calendar me-2"></i> Latest First
                    </a>
                    <a href="<?= buildSortUrl('oldest') ?>" 
                       class="sort-option <?= isset($_GET['sort']) && $_GET['sort'] == 'oldest' ? 'active' : '' ?>">
                       <i class="fas fa-calendar-alt me-2"></i> Oldest First
                    </a>
                    <a href="<?= buildSortUrl('rating') ?>" 
                       class="sort-option <?= isset($_GET['sort']) && $_GET['sort'] == 'rating' ? 'active' : '' ?>">
                       <i class="fas fa-star me-2"></i> Highest Rated
                    </a>
                    <a href="<?= buildSortUrl('downloads') ?>" 
                       class="sort-option <?= isset($_GET['sort']) && $_GET['sort'] == 'downloads' ? 'active' : '' ?>">
                       <i class="fas fa-download me-2"></i> Most Downloaded
                    </a>
                </div>
            </div>
            
            <!-- Resource Statistics Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
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
                                <div class="resource-file-icon">
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
                                <div class="card-body text-center">
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

<?php
// Include the standard footer
include_once '../includes/footer.php';
?>