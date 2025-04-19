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

// Get user resources from database
try {
    $pdo = getDbConnection();
    
    // Get counts for different statuses
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(download_count) as total_downloads
        FROM resources 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $counts = $stmt->fetch();
    
    // Apply filters if set
    $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
    $where_clause = "WHERE user_id = ?";
    $params = [$user_id];
    
    if ($status_filter !== 'all') {
        $where_clause .= " AND status = ?";
        $params[] = $status_filter;
    }
    
    // Get resources with pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $stmt = $pdo->prepare("
        SELECT r.*, rc.name as category_name 
        FROM resources r
        JOIN resource_categories rc ON r.category_id = rc.id
        $where_clause
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $resources = $stmt->fetchAll();
    
    // Get total for pagination
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM resources $where_clause");
    $stmt->execute(array_slice($params, 0, -2));
    $total_resources = $stmt->fetchColumn();
    $total_pages = ceil($total_resources / $limit);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $resources = [];
    $counts = [
        'total' => 0,
        'approved' => 0,
        'pending' => 0,
        'rejected' => 0,
        'total_downloads' => 0
    ];
    $total_pages = 0;
}

// Set page title
$page_title = "My Resources";
$nested = true;

// Include header
include_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">My Resources</h1>
        <a href="upload.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-upload fa-sm text-white-50 me-1"></i> Upload New Resource
        </a>
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
    
    <div class="row mb-4">
        <!-- Stats cards -->
        <div class="col-md-3 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Resources</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $counts['total'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-folder fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Approved</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $counts['approved'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Pending</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $counts['pending'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Downloads</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $counts['total_downloads'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-download fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter options -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Resources</h6>
        </div>
        <div class="card-body">
            <div class="btn-group" role="group">
                <a href="my-resources.php" class="btn <?= $status_filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    All Resources
                </a>
                <a href="my-resources.php?status=approved" class="btn <?= $status_filter === 'approved' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    Approved
                </a>
                <a href="my-resources.php?status=pending" class="btn <?= $status_filter === 'pending' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    Pending
                </a>
                <a href="my-resources.php?status=rejected" class="btn <?= $status_filter === 'rejected' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    Rejected
                </a>
            </div>
        </div>
    </div>
    
    <!-- Resources Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">My Uploaded Resources</h6>
        </div>
        <div class="card-body">
            <?php if (empty($resources)): ?>
                <div class="text-center py-4">
                    <div class="mb-3">
                        <i class="fas fa-folder-open fa-4x text-muted"></i>
                    </div>
                    <p class="mb-4">No resources found. Start by uploading your first educational resource!</p>
                    <a href="upload.php" class="btn btn-primary">
                        <i class="fas fa-upload me-1"></i> Upload New Resource
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Uploaded</th>
                                <th>Downloads</th>
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
                                        <?php elseif ($resource['status'] === 'rejected'): ?>
                                            <span class="badge bg-danger">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($resource['created_at'])) ?></td>
                                    <td><?= $resource['download_count'] ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view.php?id=<?= $resource['id'] ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($resource['status'] !== 'approved'): ?>
                                                <a href="edit.php?id=<?= $resource['id'] ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="download.php?id=<?= $resource['id'] ?>" class="btn btn-sm btn-success">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                   data-bs-toggle="modal" data-bs-target="#deleteResourceModal"
                                                   data-resource-id="<?= $resource['id'] ?>"
                                                   data-resource-title="<?= htmlspecialchars($resource['title']) ?>">
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
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="my-resources.php?page=<?= $i ?><?= $status_filter !== 'all' ? '&status=' . $status_filter : '' ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
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
                <p>Are you sure you want to delete the resource: <strong id="resource-title-text"></strong>?</p>
                <p class="text-danger">This action cannot be undone!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="delete-resource-link" class="btn btn-danger">Delete Resource</a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Set up delete modal
        const deleteModal = document.getElementById('deleteResourceModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const resourceId = button.getAttribute('data-resource-id');
                const resourceTitle = button.getAttribute('data-resource-title');
                
                document.getElementById('resource-title-text').textContent = resourceTitle;
                document.getElementById('delete-resource-link').href = 'delete_resource.php?id=' + resourceId;
            });
        }
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>