<?php
/**
 * Admin Resource Approval
 * Place this file in: polyeduhub/admin/resources/approve.php
 */

// Start session and include necessary files
session_start();

// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db-connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../includes/admin-functions.php';

// Check if admin is logged in
checkAdminLogin('../../admin-login.php');

// Get admin information from session
$admin_id = $_SESSION['id'];

// Process resource approval/rejection if requested
if (isset($_GET['id']) && isset($_GET['action'])) {
    $resource_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    // Validate action
    if ($action === 'approve' || $action === 'reject') {
        try {
            $pdo = getDbConnection();
            
            // Get resource details for notification
            $stmt = $pdo->prepare("
                SELECT r.title, r.user_id, u.first_name, u.email
                FROM resources r
                JOIN users u ON r.user_id = u.id
                WHERE r.id = ?
            ");
            $stmt->execute([$resource_id]);
            $resource = $stmt->fetch();
            
            if ($resource) {
                // Ensure the resource is in pending state
                $stmt = $pdo->prepare("SELECT status FROM resources WHERE id = ?");
                $stmt->execute([$resource_id]);
                $status = $stmt->fetchColumn();
                
                if ($status === 'pending') {
                    // Begin transaction
                    $pdo->beginTransaction();
                    
                    // Update resource status
                    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
                    $stmt = $pdo->prepare("UPDATE resources SET status = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$new_status, $resource_id]);
                    
                    // If approved, award points to the uploader
                    if ($action === 'approve') {
                        // Award points
                        $points = POINTS_UPLOAD; // Defined in config.php
                        $point_action = "Resource Approved";
                        $details = "Your resource '{$resource['title']}' has been approved";
                        
                        // Add points to history
                        $stmt = $pdo->prepare("
                            INSERT INTO points_history (user_id, points, action, description) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$resource['user_id'], $points, $point_action, $details]);
                        
                        // Update user points
                        $stmt = $pdo->prepare("
                            UPDATE user_points 
                            SET points = points + ?, last_updated = NOW() 
                            WHERE user_id = ?
                        ");
                        $stmt->execute([$points, $resource['user_id']]);
                        
                        // Create notification for the user
                        createNotification(
                            $resource['user_id'], 
                            "Your resource '{$resource['title']}' has been approved.",
                            "resources/view.php?id=" . $resource_id
                        );
                        
                        // Log the action
                        logAdminAction(
                            $admin_id, 
                            "Resource Approved", 
                            "Approved resource ID: {$resource_id}, Title: {$resource['title']}"
                        );
                        
                        // Send notification email (if email functionality is enabled)
                        try {
                            if (function_exists('sendNotificationEmail')) {
                                sendNotificationEmail('resource_approved', [
                                    'email' => $resource['email'],
                                    'first_name' => $resource['first_name'],
                                    'resource_title' => $resource['title'],
                                    'action_link' => APP_URL . "/student/resources/view.php?id=" . $resource_id,
                                    'action_text' => 'View Resource'
                                ]);
                            }
                        } catch (Exception $e) {
                            // Log error but continue
                            error_log("Failed to send approval email: " . $e->getMessage());
                        }
                    } else {
                        // For rejection, get the reason
                        $rejection_reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : 'Does not meet our guidelines';
                        
                        // Create notification for the user
                        createNotification(
                            $resource['user_id'], 
                            "Your resource '{$resource['title']}' was not approved. Reason: {$rejection_reason}",
                            "resources/my-resources.php"
                        );
                        
                        // Log the action
                        logAdminAction(
                            $admin_id, 
                            "Resource Rejected", 
                            "Rejected resource ID: {$resource_id}, Title: {$resource['title']}, Reason: {$rejection_reason}"
                        );
                        
                        // Send notification email (if email functionality is enabled)
                        try {
                            if (function_exists('sendNotificationEmail')) {
                                sendNotificationEmail('resource_rejected', [
                                    'email' => $resource['email'],
                                    'first_name' => $resource['first_name'],
                                    'resource_title' => $resource['title'],
                                    'rejection_reason' => $rejection_reason,
                                    'action_link' => APP_URL . "/student/resources/my-resources.php",
                                    'action_text' => 'View My Resources'
                                ]);
                            }
                        } catch (Exception $e) {
                            // Log error but continue
                            error_log("Failed to send rejection email: " . $e->getMessage());
                        }
                    }
                    
                    // Commit transaction
                    $pdo->commit();
                    
                    // Set success message
                    $_SESSION['success_message'] = "Resource has been " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully.";
                } else {
                    $_SESSION['error_message'] = "Resource is not in pending state.";
                }
            } else {
                $_SESSION['error_message'] = "Resource not found.";
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            error_log("Error in resource approval: " . $e->getMessage());
            $_SESSION['error_message'] = "An error occurred while processing the resource.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid action.";
    }
    
    // Redirect back to approval page
    header("Location: approve.php");
    exit();
}

// Get filter parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'pending';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get resources based on filter
try {
    $pdo = getDbConnection();
    
    // Build query based on filter
    $where_clauses = [];
    $params = [];
    
    if ($filter !== 'all') {
        $where_clauses[] = "r.status = ?";
        $params[] = $filter;
    }
    
    if (!empty($search)) {
        $where_clauses[] = "(r.title LIKE ? OR r.description LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_sql = empty($where_clauses) ? "" : "WHERE " . implode(' AND ', $where_clauses);
    
    // Get total count for pagination
    $count_sql = "
        SELECT COUNT(*) 
        FROM resources r
        JOIN users u ON r.user_id = u.id
        $where_sql
    ";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_count = $stmt->fetchColumn();
    $total_pages = ceil($total_count / $per_page);
    
    // Get resources for current page
    $sql = "
        SELECT r.id, r.title, r.description, r.file_type, r.file_size, r.status,
               r.created_at, r.download_count, r.view_count,
               u.id as user_id, u.first_name, u.last_name, u.email,
               rc.name as category_name
        FROM resources r
        JOIN users u ON r.user_id = u.id
        JOIN resource_categories rc ON r.category_id = rc.id
        $where_sql
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
    ";
    $stmt = $pdo->prepare($sql);
    $params[] = $per_page;
    $params[] = $offset;
    $stmt->execute($params);
    $resources = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error fetching resources: " . $e->getMessage());
    $resources = [];
    $total_count = 0;
    $total_pages = 0;
    $_SESSION['error_message'] = "An error occurred while fetching resources.";
}

// Set page title
$page_title = "Resource Approval";

// Include header
include_once '../includes/header.php';
?>

<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Resource Approval</h1>
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

<!-- Filter & Search Area -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary">Filter Resources</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="btn-group mb-3" role="group" aria-label="Filter options">
                    <a href="approve.php?filter=pending<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="btn btn-<?= $filter === 'pending' ? 'primary' : 'outline-primary' ?>">
                        Pending
                    </a>
                    <a href="approve.php?filter=approved<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="btn btn-<?= $filter === 'approved' ? 'success' : 'outline-success' ?>">
                        Approved
                    </a>
                    <a href="approve.php?filter=rejected<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="btn btn-<?= $filter === 'rejected' ? 'danger' : 'outline-danger' ?>">
                        Rejected
                    </a>
                    <a href="approve.php?filter=all<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="btn btn-<?= $filter === 'all' ? 'secondary' : 'outline-secondary' ?>">
                        All
                    </a>
                </div>
            </div>
            <div class="col-md-6">
                <form class="d-flex" action="approve.php" method="GET">
                    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                    <input type="text" class="form-control" name="search" placeholder="Search resources..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-primary ms-2">Search</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Resources Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <?php
            $filter_title = "All Resources";
            if ($filter === 'pending') $filter_title = "Resources Pending Approval";
            elseif ($filter === 'approved') $filter_title = "Approved Resources";
            elseif ($filter === 'rejected') $filter_title = "Rejected Resources";
            echo $filter_title . " (" . number_format($total_count) . ")";
            ?>
        </h6>
    </div>
    <div class="card-body">
        <?php if (empty($resources)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-folder-open fa-4x mb-3"></i>
                <p>No resources found matching your criteria.</p>
                <?php if (!empty($search) || $filter !== 'pending'): ?>
                    <a href="approve.php" class="btn btn-outline-primary">Reset Filters</a>
                <?php endif; ?>
    </div>
</div>

<!-- Reject Resource Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="approve.php" method="GET">
                <input type="hidden" name="id" id="reject-resource-id">
                <input type="hidden" name="action" value="reject">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectModalLabel">Reject Resource</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to reject the resource: <strong id="reject-resource-title"></strong>?</p>
                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label">Rejection Reason:</label>
                        <textarea class="form-control" name="rejection_reason" id="rejection_reason" rows="3" required></textarea>
                        <div class="form-text">This reason will be shared with the uploader.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Resource</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Additional JavaScript for this page -->
<script>
    // Set up reject modal
    document.addEventListener('DOMContentLoaded', function() {
        // Handle resource rejection modal
        const rejectModal = document.getElementById('rejectModal');
        if (rejectModal) {
            rejectModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const resourceId = button.getAttribute('data-resource-id');
                const resourceTitle = button.getAttribute('data-resource-title');
                
                document.getElementById('reject-resource-id').value = resourceId;
                document.getElementById('reject-resource-title').textContent = resourceTitle;
            });
        }
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Uploaded By</th>
                            <th>File</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resources as $resource): ?>
                            <tr>
                                <td><?= htmlspecialchars($resource['title']) ?></td>
                                <td><?= htmlspecialchars($resource['category_name']) ?></td>
                                <td>
                                    <a href="../users/view.php?id=<?= $resource['user_id'] ?>">
                                        <?= htmlspecialchars($resource['first_name'] . ' ' . $resource['last_name']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?= strtoupper($resource['file_type']) ?> 
                                    (<?= formatFileSize($resource['file_size']) ?>)
                                </td>
                                <td><?= date('M d, Y', strtotime($resource['created_at'])) ?></td>
                                <td><?= getStatusBadge($resource['status']) ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="view.php?id=<?= $resource['id'] ?>" class="btn btn-sm btn-info" title="View Resource">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($resource['status'] === 'pending'): ?>
                                            <a href="approve.php?id=<?= $resource['id'] ?>&action=approve" class="btn btn-sm btn-success" title="Approve Resource">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" title="Reject Resource" 
                                                data-bs-toggle="modal" data-bs-target="#rejectModal" 
                                                data-resource-id="<?= $resource['id'] ?>" 
                                                data-resource-title="<?= htmlspecialchars($resource['title']) ?>">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php elseif ($resource['status'] === 'approved'): ?>
                                            <a href="../../resources/<?= $resource['id'] ?>/download" class="btn btn-sm btn-secondary" title="Download Resource">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        <?php elseif ($resource['status'] === 'rejected'): ?>
                                            <a href="approve.php?id=<?= $resource['id'] ?>&action=approve" class="btn btn-sm btn-outline-success" title="Approve Resource">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
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
                    <nav aria-label="Page navigation">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="approve.php?filter=<?= $filter ?>&page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link">&laquo;</span>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            // Calculate range of page numbers to display
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            // Display first page and ellipsis if necessary
                            if ($start_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="approve.php?filter=' . $filter . '&page=1' . (!empty($search) ? '&search=' . urlencode($search) : '') . '">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            // Display page numbers
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">
                                    <a class="page-link" href="approve.php?filter=' . $filter . '&page=' . $i . (!empty($search) ? '&search=' . urlencode($search) : '') . '">' . $i . '</a>
                                </li>';
                            }
                            
                            // Display last page and ellipsis if necessary
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="approve.php?filter=' . $filter . '&page=' . $total_pages . (!empty($search) ? '&search=' . urlencode($search) : '') . '">' . $total_pages . '</a></li>';
                            }
                            ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="approve.php?filter=<?= $filter ?>&page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link">&raquo;</span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>