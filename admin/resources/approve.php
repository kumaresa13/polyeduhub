<?php
// File path: admin/resources/approve.php

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

// Process resource approval/rejection if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['resource_id'])) {
    $resource_id = intval($_POST['resource_id']);
    $action = $_POST['action'];
    $feedback = isset($_POST['feedback']) ? trim($_POST['feedback']) : '';

    try {
        $pdo = getDbConnection();
        $pdo->beginTransaction();

        if ($action === 'approve') {
            // Update resource status to approved
            $stmt = $pdo->prepare("UPDATE resources SET status = 'approved', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$resource_id]);

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
                // Create notification for the user
                createNotification(
                    $resource['user_id'],
                    "Your resource '{$resource['title']}' has been approved",
                    "resources/view.php?id=$resource_id"
                );

                // Log the action
                logAdminAction(
                    $admin_id,
                    "Resource approved",
                    "Approved resource ID: $resource_id, Title: {$resource['title']}"
                );

                // Award points to the user
                awardPoints(
                    $resource['user_id'],
                    POINTS_UPLOAD,
                    'Resource Approved',
                    "Resource '{$resource['title']}' was approved by admin"
                );

                // Optional: Send notification email
                if (function_exists('sendNotificationEmail')) {
                    $notification_data = [
                        'email' => $resource['email'],
                        'first_name' => $resource['first_name'],
                        'resource_title' => $resource['title'],
                        'action_link' => APP_URL . "/student/resources/view.php?id=$resource_id"
                    ];

                    sendNotificationEmail('resource_approved', $notification_data);
                }
            }

            $_SESSION['success_message'] = "Resource has been approved successfully.";
        } elseif ($action === 'reject') {
            // Update resource status to rejected with feedback
            $stmt = $pdo->prepare("
                UPDATE resources 
                SET status = 'rejected', 
                    admin_feedback = ?, 
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$feedback, $resource_id]);

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
                // Create notification for the user
                createNotification(
                    $resource['user_id'],
                    "Your resource '{$resource['title']}' needs revisions: $feedback",
                    "resources/edit.php?id=$resource_id"
                );

                // Log the action
                logAdminAction(
                    $admin_id,
                    "Resource rejected",
                    "Rejected resource ID: $resource_id, Title: {$resource['title']}, Feedback: $feedback"
                );

                // Optional: Send notification email
                if (function_exists('sendNotificationEmail')) {
                    $notification_data = [
                        'email' => $resource['email'],
                        'first_name' => $resource['first_name'],
                        'resource_title' => $resource['title'],
                        'rejection_reason' => $feedback,
                        'action_link' => APP_URL . "/student/resources/edit.php?id=$resource_id"
                    ];

                    sendNotificationEmail('resource_rejected', $notification_data);
                }
            }

            $_SESSION['success_message'] = "Resource has been rejected with feedback.";
        }

        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error processing resource: " . $e->getMessage());
        $_SESSION['error_message'] = "An error occurred while processing the resource.";
    }

    // Redirect to refresh page
    header("Location: approve.php");
    exit();
}

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : 'pending';
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Fetch pending resources
try {
    $pdo = getDbConnection();

    // Build WHERE clause
    $where_clauses = [];
    $params = [];

    if ($status) {
        $where_clauses[] = "r.status = ?";
        $params[] = $status;
    }

    if ($category) {
        $where_clauses[] = "r.category_id = ?";
        $params[] = $category;
    }

    if (!empty($search)) {
        $where_clauses[] = "(r.title LIKE ? OR r.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

    // Get total count
    $count_sql = "
        SELECT COUNT(*) 
        FROM resources r 
        $where_sql
    ";

    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_resources = $stmt->fetchColumn();
    $total_pages = ceil($total_resources / $limit);

    // Get resources with details
    $sql = "
        SELECT r.id, r.title, r.description, r.file_path, r.file_type, r.file_size, 
               r.status, r.created_at, r.updated_at, r.download_count, r.admin_feedback,
               u.id as user_id, u.first_name, u.last_name, u.email,
               rc.name as category_name
        FROM resources r
        JOIN users u ON r.user_id = u.id
        JOIN resource_categories rc ON r.category_id = rc.id
        $where_sql
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $all_params = array_merge($params, [$limit, $offset]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($all_params);
    $resources = $stmt->fetchAll();

    // Get categories for filtering
    $stmt = $pdo->prepare("SELECT id, name FROM resource_categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $resources = [];
    $total_resources = 0;
    $total_pages = 0;
    $categories = [];
}

// Set page title and nested path variable
$page_title = "Approve Resources";
$nested = true;

// Include header
include_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
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

    <!-- Filters Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Resources</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row gx-3 gy-2 align-items-center">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category === intval($cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search"
                        placeholder="Search by title or description" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                    <a href="approve.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Resources Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Resources for Review</h6>
            <span class="badge bg-primary"><?= number_format($total_resources) ?> resources</span>
        </div>
        <div class="card-body">
            <?php if (empty($resources)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-check-circle fa-4x text-gray-300 mb-3"></i>
                    <p class="text-gray-500 mb-0">No resources found matching your criteria.</p>
                    <?php if ($status === 'pending'): ?>
                        <p class="text-gray-500">All resources have been reviewed!</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Submitted By</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th width="150">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resources as $resource): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($resource['title']) ?></div>
                                        <div class="small text-muted">
                                            <?= strtoupper($resource['file_type']) ?> •
                                            <?= formatFileSize($resource['file_size']) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($resource['category_name']) ?></td>
                                    <td>
                                        <a href="../users/view.php?id=<?= $resource['user_id'] ?>">
                                            <?= htmlspecialchars($resource['first_name'] . ' ' . $resource['last_name']) ?>
                                        </a>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($resource['created_at'])) ?></td>
                                    <td>
                                        <?php if ($resource['status'] === 'pending'): ?>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        <?php elseif ($resource['status'] === 'approved'): ?>
                                            <span class="badge bg-success">Approved</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Rejected</span>
                                            <?php if (!empty($resource['admin_feedback'])): ?>
                                                <div class="small mt-1">
                                                    <a href="#" data-bs-toggle="tooltip"
                                                        title="<?= htmlspecialchars($resource['admin_feedback']) ?>">
                                                        <i class="fas fa-info-circle"></i> View Feedback
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view.php?id=<?= $resource['id'] ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($resource['status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal"
                                                    data-bs-target="#approveModal<?= $resource['id'] ?>">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                                    data-bs-target="#rejectModal<?= $resource['id'] ?>">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                            <a href="download.php?id=<?= $resource['id'] ?>" class="btn btn-sm btn-secondary">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>



                                        <!-- Approve Modal -->
                                        
                                        <div class="modal fade" id="approveModal<?= $resource['id'] ?>" tabindex="-1"
                                            aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Approve Resource</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                            aria-label="Close"></button>
                                                    </div>
                                                    <form action="update_status.php" method="POST">
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to approve this resource?</p>
                                                            <p><strong>Title:</strong>
                                                                <?= htmlspecialchars($resource['title']) ?></p>

                                                            <input type="hidden" name="resource_id"
                                                                value="<?= $resource['id'] ?>">
                                                            <input type="hidden" name="action" value="approve">
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary"
                                                                data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-success">Approve
                                                                Resource</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Reject Modal -->
                                        <div class="modal fade" id="rejectModal<?= $resource['id'] ?>" tabindex="-1"
                                            aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Reject Resource</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                            aria-label="Close"></button>
                                                    </div>
                                                    <form action="update_status.php" method="POST">
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to reject this resource?</p>
                                                            <p><strong>Title:</strong>
                                                                <?= htmlspecialchars($resource['title']) ?></p>
                                                            <p><strong>Submitted by:</strong>
                                                                <?= htmlspecialchars($resource['first_name'] . ' ' . $resource['last_name']) ?>
                                                            </p>

                                                            <div class="mb-3">
                                                                <label for="feedback<?= $resource['id'] ?>"
                                                                    class="form-label">Feedback (Required)</label>
                                                                <textarea class="form-control"
                                                                    id="feedback<?= $resource['id'] ?>" name="feedback" rows="3"
                                                                    required></textarea>
                                                                <div class="form-text">Provide feedback to help the user improve
                                                                    their submission.</div>
                                                            </div>

                                                            <input type="hidden" name="resource_id"
                                                                value="<?= $resource['id'] ?>">
                                                            <input type="hidden" name="action" value="reject">
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary"
                                                                data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger">Reject</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
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
                                        <a class="page-link"
                                            href="?page=<?= $page - 1 ?>&status=<?= $status ?>&category=<?= $category ?>&search=<?= urlencode($search) ?>">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link"
                                            href="?page=<?= $i ?>&status=<?= $status ?>&category=<?= $category ?>&search=<?= urlencode($search) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link"
                                            href="?page=<?= $page + 1 ?>&status=<?= $status ?>&category=<?= $category ?>&search=<?= urlencode($search) ?>">
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
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>