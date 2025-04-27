<?php
// File path: admin/resources/index.php

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

// Handle resource deletion if requested
if (isset($_POST['delete_resource']) && isset($_POST['resource_id'])) {
    $resource_id = intval($_POST['resource_id']);
    
    try {
        $pdo = getDbConnection();
        
        // Get resource info before deletion for logging
        $stmt = $pdo->prepare("
            SELECT title, file_path 
            FROM resources 
            WHERE id = ?
        ");
        $stmt->execute([$resource_id]);
        $resource = $stmt->fetch();
        
        if ($resource) {
            // Start transaction
            $pdo->beginTransaction();
            
            // Delete resource from database
            $stmt = $pdo->prepare("DELETE FROM resources WHERE id = ?");
            $stmt->execute([$resource_id]);
            
            // Delete resource tag relationships
            $stmt = $pdo->prepare("DELETE FROM resource_tag_relationship WHERE resource_id = ?");
            $stmt->execute([$resource_id]);
            
            // Delete resource comments
            $stmt = $pdo->prepare("DELETE FROM resource_comments WHERE resource_id = ?");
            $stmt->execute([$resource_id]);
            
            // Delete resource ratings
            $stmt = $pdo->prepare("DELETE FROM resource_ratings WHERE resource_id = ?");
            $stmt->execute([$resource_id]);
            
            // Delete actual file
            $file_path = RESOURCE_PATH . $resource['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Delete thumbnail if it exists
            $thumbnail_path = RESOURCE_PATH . 'thumbnails/' . pathinfo($resource['file_path'], PATHINFO_FILENAME) . '.jpg';
            if (file_exists($thumbnail_path)) {
                unlink($thumbnail_path);
            }
            
            // Log the action
            logAdminAction(
                $admin_id,
                "Deleted resource",
                "Resource: " . $resource['title']
            );
            
            // Commit transaction
            $pdo->commit();
            
            $_SESSION['success_message'] = "Resource deleted successfully";
        } else {
            $_SESSION['error_message'] = "Resource not found";
        }
    } catch (PDOException $e) {
        // Rollback transaction on error
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error deleting resource: " . $e->getMessage());
        $_SESSION['error_message'] = "Error deleting resource: " . $e->getMessage();
    }
    
    // Redirect to refresh page
    header("Location: index.php");
    exit();
}

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Get all resources with filters
try {
    $pdo = getDbConnection();
    
    // Build SQL where clause
    $where_clauses = [];
    $params = [];
    
    if (!empty($status)) {
        $where_clauses[] = "r.status = ?";
        $params[] = $status;
    }
    
    if ($category > 0) {
        $where_clauses[] = "r.category_id = ?";
        $params[] = $category;
    }
    
    if (!empty($search)) {
        $where_clauses[] = "(r.title LIKE ? OR r.description LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    // Build order clause
    $order_clause = "ORDER BY r.created_at DESC"; // Default: newest first
    
    if ($sort === 'oldest') {
        $order_clause = "ORDER BY r.created_at ASC";
    } elseif ($sort === 'title') {
        $order_clause = "ORDER BY r.title ASC";
    } elseif ($sort === 'downloads') {
        $order_clause = "ORDER BY r.download_count DESC";
    }
    
    // Get total count for pagination
    $count_sql = "
        SELECT COUNT(*) 
        FROM resources r
        JOIN users u ON r.user_id = u.id
        $where_sql
    ";
    
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_resources = $stmt->fetchColumn();
    $total_pages = ceil($total_resources / $limit);
    
    // Get resources with details
    $sql = "
        SELECT r.*, 
               u.first_name, u.last_name, 
               rc.name as category_name
        FROM resources r
        JOIN users u ON r.user_id = u.id
        JOIN resource_categories rc ON r.category_id = rc.id
        $where_sql
        $order_clause
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resources = $stmt->fetchAll();
    
    // Get categories for filter
    $stmt = $pdo->prepare("SELECT id, name FROM resource_categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
    // Get resource stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total, 
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(download_count) as downloads,
            SUM(view_count) as views
        FROM resources
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log("Error retrieving resources: " . $e->getMessage());
    $resources = [];
    $categories = [];
    $total_resources = 0;
    $total_pages = 0;
    $stats = [
        'total' => 0,
        'approved' => 0,
        'pending' => 0,
        'rejected' => 0,
        'downloads' => 0,
        'views' => 0
    ];
}

// Set page title and nested path variable
$page_title = "Manage Resources";
$nested = true;

// Include header
include_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">All Resources</h1>
        <div>
            <a href="approve.php" class="btn btn-sm btn-primary shadow-sm me-2">
                <i class="fas fa-check-circle fa-sm text-white-50"></i> Approvals
            </a>
            <a href="categories.php" class="btn btn-sm btn-secondary shadow-sm">
                <i class="fas fa-folder fa-sm text-white-50"></i> Categories
            </a>
        </div>
    </div>

    <!-- Success/Error Messages -->
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

    <!-- Stats Cards Row -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Resources</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['total']?? 0) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Approved</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['approved']?? 0) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['pending']?? 0) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Rejected</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['rejected']?? 0) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Downloads</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['downloads']?? 0) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-download fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-secondary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Views</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['views']?? 0) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-eye fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Resources</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="" <?= $status === '' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="0" <?= $category === 0 ? 'selected' : '' ?>>All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category === $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="sort" class="form-label">Sort By</label>
                    <select class="form-select" id="sort" name="sort">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                        <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Title (A-Z)</option>
                        <option value="downloads" <?= $sort === 'downloads' ? 'selected' : '' ?>>Most Downloads</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Search resources" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-12 mt-3">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="index.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Resources Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Resource Library</h6>
            <span>Showing <?= min($offset + 1, $total_resources) ?>-<?= min($offset + $limit, $total_resources) ?> of <?= $total_resources ?> resources</span>
        </div>
        <div class="card-body">
            <?php if (empty($resources)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-folder-open fa-3x text-gray-300 mb-3"></i>
                    <p class="mb-0">No resources found matching your criteria.</p>
                    <p>Try adjusting your filters or search terms.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Uploader</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Downloads</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resources as $resource): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php
                                            // Icon based on file type
                                            $icon_class = 'fa-file';
                                            switch ($resource['file_type']) {
                                                case 'pdf':
                                                    $icon_class = 'fa-file-pdf';
                                                    break;
                                                case 'doc':
                                                case 'docx':
                                                    $icon_class = 'fa-file-word';
                                                    break;
                                                case 'xls':
                                                case 'xlsx':
                                                    $icon_class = 'fa-file-excel';
                                                    break;
                                                case 'ppt':
                                                case 'pptx':
                                                    $icon_class = 'fa-file-powerpoint';
                                                    break;
                                                case 'jpg':
                                                case 'jpeg':
                                                case 'png':
                                                case 'gif':
                                                    $icon_class = 'fa-file-image';
                                                    break;
                                                case 'zip':
                                                case 'rar':
                                                    $icon_class = 'fa-file-archive';
                                                    break;
                                            }
                                            ?>
                                            <i class="fas <?= $icon_class ?> fa-lg text-primary me-2"></i>
                                            <div>
                                                <div class="font-weight-bold"><?= htmlspecialchars($resource['title']) ?></div>
                                                <div class="small text-muted"><?= strtoupper($resource['file_type']) ?> · <?= formatFileSize($resource['file_size']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($resource['category_name']) ?></td>
                                    <td><?= htmlspecialchars($resource['first_name'] . ' ' . $resource['last_name']) ?></td>
                                    <td><?= date('M d, Y', strtotime($resource['created_at'])) ?></td>
                                    <td>
                                        <?php if ($resource['status'] === 'approved'): ?>
                                            <span class="badge bg-success">Approved</span>
                                        <?php elseif ($resource['status'] === 'pending'): ?>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= number_format($resource['download_count']?? 0) ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view.php?id=<?= $resource['id'] ?>" class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="download.php?id=<?= $resource['id'] ?>" class="btn btn-sm btn-primary" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteResourceModal" 
                                                    data-id="<?= $resource['id'] ?>" 
                                                    data-title="<?= htmlspecialchars($resource['title']) ?>"
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
                    <div class="d-flex justify-content-center mt-4">
                        <nav>
                            <ul class="pagination">
                                <!-- Previous page link -->
                                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>&status=<?= urlencode($status) ?>&category=<?= $category ?>&search=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                
                                <!-- Page numbers -->
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1&status=<?= urlencode($status) ?>&category=<?= $category ?>&search=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>">1</a>
                                    </li>
                                    <?php if ($start_page > 2): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&status=<?= urlencode($status) ?>&category=<?= $category ?>&search=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $total_pages ?>&status=<?= urlencode($status) ?>&category=<?= $category ?>&search=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>"><?= $total_pages ?></a>
                                    </li>
                                <?php endif; ?>
                                
                                <!-- Next page link -->
                                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>&status=<?= urlencode($status) ?>&category=<?= $category ?>&search=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Resource Modal -->
<div class="modal fade" id="deleteResourceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Resource</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="resource_id" id="delete_resource_id">
                    <p>Are you sure you want to delete the resource: <strong id="delete_resource_title"></strong>?</p>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i> This action cannot be undone. 
                        The resource file and all associated data will be permanently deleted.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_resource" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle delete resource modal
    const deleteModal = document.getElementById('deleteResourceModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const resourceId = button.getAttribute('data-id');
            const resourceTitle = button.getAttribute('data-title');
            
            document.getElementById('delete_resource_id').value = resourceId;
            document.getElementById('delete_resource_title').textContent = resourceTitle;
        });
    }
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>