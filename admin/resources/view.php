<?php
// File path: admin/resources/view.php

// Include necessary files
require_once '../../includes/config.php';
require_once '../../includes/db-connection.php';
require_once '../../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../admin-login.php");
    exit();
}

// Get resource ID
$resource_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$resource_id) {
    $_SESSION['error_message'] = "Invalid resource ID";
    header("Location: approve.php");
    exit();
}

// Get resource details
try {
    $pdo = getDbConnection();
    
    $stmt = $pdo->prepare("
        SELECT r.*, 
               u.id as user_id, u.first_name, u.last_name, u.email, u.department, u.student_id,
               rc.name as category_name,
               (SELECT COUNT(*) FROM resource_comments WHERE resource_id = r.id) as comment_count,
               (SELECT COALESCE(AVG(rating), 0) FROM resource_ratings WHERE resource_id = r.id) as avg_rating,
               (SELECT COUNT(*) FROM resource_ratings WHERE resource_id = r.id) as rating_count
        FROM resources r
        JOIN users u ON r.user_id = u.id
        JOIN resource_categories rc ON r.category_id = rc.id
        WHERE r.id = ?
    ");
    $stmt->execute([$resource_id]);
    $resource = $stmt->fetch();
    
    if (!$resource) {
        $_SESSION['error_message'] = "Resource not found";
        header("Location: approve.php");
        exit();
    }
    
    // Get resource tags
    $stmt = $pdo->prepare("
        SELECT rt.name
        FROM resource_tags rt
        JOIN resource_tag_relationship rtr ON rt.id = rtr.tag_id
        WHERE rtr.resource_id = ?
    ");
    $stmt->execute([$resource_id]);
    $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get resource comments
    $stmt = $pdo->prepare("
        SELECT rc.*, u.first_name, u.last_name, u.profile_image
        FROM resource_comments rc
        JOIN users u ON rc.user_id = u.id
        WHERE rc.resource_id = ?
        ORDER BY rc.created_at DESC
    ");
    $stmt->execute([$resource_id]);
    $comments = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error_message'] = "An error occurred while retrieving resource details.";
    header("Location: approve.php");
    exit();
}

// Set page title and nested path variable
$page_title = "View Resource";
$nested = true;

// Include header
include_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Resource Details</h1>
        <div>
            <a href="approve.php" class="btn btn-sm btn-secondary shadow-sm me-2">
                <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back to List
            </a>
            <?php if ($resource['status'] === 'pending'): ?>
                <button type="button" class="btn btn-sm btn-success shadow-sm me-2" data-bs-toggle="modal" data-bs-target="#approveModal">
                    <i class="fas fa-check fa-sm text-white-50"></i> Approve
                </button>
                <button type="button" class="btn btn-sm btn-danger shadow-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="fas fa-times fa-sm text-white-50"></i> Reject
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row">
        <!-- Resource Info Column -->
        <div class="col-lg-8">
            <!-- Resource Details Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Resource Information</h6>
                    <span class="badge 
                        <?php if ($resource['status'] === 'pending'): ?>
                            bg-warning text-dark
                        <?php elseif ($resource['status'] === 'approved'): ?>
                            bg-success
                        <?php else: ?>
                            bg-danger
                        <?php endif; ?>">
                        <?= ucfirst($resource['status']) ?>
                    </span>
                </div>
                <div class="card-body">
                    <h4 class="font-weight-bold text-gray-800 mb-3"><?= htmlspecialchars($resource['title']) ?></h4>
                    
                    <div class="mb-4">
                        <h6 class="font-weight-bold">Description</h6>
                        <p><?= !empty($resource['description']) ? nl2br(htmlspecialchars($resource['description'])) : '<em>No description provided</em>' ?></p>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="font-weight-bold">Category</h6>
                            <p><?= htmlspecialchars($resource['category_name']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="font-weight-bold">Tags</h6>
                            <p>
                                <?php if (!empty($tags)): ?>
                                    <?php foreach ($tags as $tag): ?>
                                        <span class="badge bg-secondary me-1"><?= htmlspecialchars($tag) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <em>No tags</em>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="font-weight-bold">File Details</h6>
                            <p>
                                <strong>Type:</strong> <?= strtoupper($resource['file_type']) ?><br>
                                <strong>Size:</strong> <?= formatFileSize($resource['file_size']) ?><br>
                                <strong>Uploaded:</strong> <?= date('F j, Y, g:i a', strtotime($resource['created_at'])) ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="font-weight-bold">Stats</h6>
                            <p>
                                <strong>Views:</strong> <?= number_format($resource['view_count']) ?><br>
                                <strong>Downloads:</strong> <?= number_format($resource['download_count']) ?><br>
                                <strong>Ratings:</strong> 
                                <?php if ($resource['rating_count'] > 0): ?>
                                    <?= number_format($resource['avg_rating'], 1) ?>/5
                                    <span class="text-muted">(<?= $resource['rating_count'] ?> ratings)</span>
                                <?php else: ?>
                                    <span class="text-muted">No ratings yet</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h6 class="font-weight-bold">Download File</h6>
                        <a href="download.php?id=<?= $resource_id ?>" class="btn btn-primary">
                            <i class="fas fa-download me-2"></i> Download File
                        </a>
                    </div>
                    
                    <?php if ($resource['status'] === 'rejected' && !empty($resource['admin_feedback'])): ?>
    <div class="mt-4">
        <div class="alert alert-danger">
            <h6 class="font-weight-bold">Rejection Reason:</h6>
            <p class="mb-0"><?= nl2br(htmlspecialchars($resource['admin_feedback'])) ?></p>
        </div>
    </div>
<?php endif; ?>
                    
</div>
</div>

<!-- File Preview Card (if applicable) -->
<?php if (in_array($resource['file_type'], ['jpg', 'jpeg', 'png', 'gif', 'pdf'])): ?>
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">File Preview</h6>
    </div>
    <div class="card-body text-center">
        <?php if (in_array($resource['file_type'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
            <img src="../../resources/<?= htmlspecialchars($resource['file_path']) ?>" alt="<?= htmlspecialchars($resource['title']) ?>" class="img-fluid" style="max-height: 500px;">
        <?php elseif ($resource['file_type'] === 'pdf'): ?>
            <div class="ratio ratio-16x9" style="height: 600px;">
                <iframe src="../../resources/<?= htmlspecialchars($resource['file_path']) ?>" title="<?= htmlspecialchars($resource['title']) ?>" allowfullscreen></iframe>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Comments Card -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Comments (<?= count($comments) ?>)</h6>
    </div>
    <div class="card-body">
        <?php if (empty($comments)): ?>
            <div class="text-center py-4">
                <p class="text-muted mb-0">No comments on this resource yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($comments as $comment): ?>
                <div class="d-flex mb-4">
                    <div class="flex-shrink-0 me-3">
                        <img class="rounded-circle" src="<?= $comment['profile_image'] ? '../../' . htmlspecialchars($comment['profile_image']) : '../../assets/img/ui/default-profile.png' ?>" alt="<?= htmlspecialchars($comment['first_name']) ?>" width="50" height="50">
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 font-weight-bold"><?= htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']) ?></h6>
                            <small class="text-muted"><?= time_elapsed_string($comment['created_at']) ?></small>
                        </div>
                        <p class="mt-2 mb-1"><?= nl2br(htmlspecialchars($comment['comment'])) ?></p>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#deleteCommentModal" 
                                    data-comment-id="<?= $comment['id'] ?>">
                                <i class="fas fa-trash-alt"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</div>

<!-- Uploader Info Column -->
<div class="col-lg-4">
    <!-- Uploader Details Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Uploader Information</h6>
        </div>
        <div class="card-body">
            <div class="text-center mb-3">
                <img class="img-profile rounded-circle" 
                     src="../../assets/img/ui/default-profile.png" 
                     alt="Profile image" style="width: 100px; height: 100px;">
            </div>
            <h5 class="text-center font-weight-bold"><?= htmlspecialchars($resource['first_name'] . ' ' . $resource['last_name']) ?></h5>
            <div class="mb-3 text-center">
                <span class="badge bg-primary">Student</span>
            </div>
            
            <hr>
            
            <div class="mb-2">
                <strong><i class="fas fa-envelope me-2"></i> Email:</strong>
                <p><?= htmlspecialchars($resource['email']) ?></p>
            </div>
            
            <?php if (!empty($resource['department'])): ?>
            <div class="mb-2">
                <strong><i class="fas fa-building me-2"></i> Department:</strong>
                <p><?= htmlspecialchars($resource['department']) ?></p>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($resource['student_id'])): ?>
            <div class="mb-2">
                <strong><i class="fas fa-id-card me-2"></i> Student ID:</strong>
                <p><?= htmlspecialchars($resource['student_id']) ?></p>
            </div>
            <?php endif; ?>
            
            <hr>
            
            <div class="text-center">
                <a href="../users/view.php?id=<?= $resource['user_id'] ?>" class="btn btn-outline-primary">
                    <i class="fas fa-user me-1"></i> View Full Profile
                </a>
            </div>
        </div>
    </div>
    
    <!-- Admin Actions Card -->
    <?php if ($resource['status'] === 'pending'): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Admin Actions</h6>
        </div>
        <div class="card-body">
            <div class="mb-3 text-center">
                <button type="button" class="btn btn-success btn-block" data-bs-toggle="modal" data-bs-target="#approveModal">
                    <i class="fas fa-check me-1"></i> Approve Resource
                </button>
            </div>
            <div class="text-center">
                <button type="button" class="btn btn-danger btn-block" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="fas fa-times me-1"></i> Reject Resource
                </button>
            </div>
        </div>
    </div>
    <?php elseif ($resource['status'] === 'approved'): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Admin Actions</h6>
        </div>
        <div class="card-body">
            <div class="alert alert-success mb-3">
                <i class="fas fa-check-circle me-2"></i> This resource has been approved
            </div>
            <div class="mb-3 text-center">
                <button type="button" class="btn btn-warning btn-block" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="fas fa-ban me-1"></i> Change to Rejected
                </button>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Admin Actions</h6>
        </div>
        <div class="card-body">
            <div class="alert alert-danger mb-3">
                <i class="fas fa-times-circle me-2"></i> This resource has been rejected
            </div>
            <div class="mb-3 text-center">
                <button type="button" class="btn btn-success btn-block" data-bs-toggle="modal" data-bs-target="#approveModal">
                    <i class="fas fa-check me-1"></i> Change to Approved
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
</div>
</div>



<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approveModalLabel">Approve Resource</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="update_status.php" method="POST">
                <input type="hidden" name="resource_id" value="<?= $resource_id ?>">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="from_view_page" value="1">
                
                <div class="modal-body">
                    <p>Are you sure you want to approve this resource?</p>
                    <p class="mb-0">Title: <strong><?= htmlspecialchars($resource['title']) ?></strong></p>
                    <p>Category: <strong><?= htmlspecialchars($resource['category_name']) ?></strong></p>
                    
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="send_notification" id="sendNotification" checked>
                        <label class="form-check-label" for="sendNotification">
                            Send notification to uploader
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Resource</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectModalLabel">Reject Resource</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="update_status.php" method="POST">
                <input type="hidden" name="resource_id" value="<?= $resource_id ?>">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="from_view_page" value="1">
                
                <div class="modal-body">
                    <p>Are you sure you want to reject this resource?</p>
                    <p class="mb-0">Title: <strong><?= htmlspecialchars($resource['title']) ?></strong></p>
                    <p>Category: <strong><?= htmlspecialchars($resource['category_name']) ?></strong></p>
                    
                    <div class="mb-3">
                        <label for="feedback" class="form-label">Feedback/Reason (will be shown to uploader):</label>
                        <textarea class="form-control" id="feedback" name="feedback" rows="4" required></textarea>
                    </div>
                    
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="send_notification" id="sendRejectionNotification" checked>
                        <label class="form-check-label" for="sendRejectionNotification">
                            Send notification to uploader
                        </label>
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

<!-- Delete Comment Modal -->
<div class="modal fade" id="deleteCommentModal" tabindex="-1" aria-labelledby="deleteCommentModalLabel" aria-hidden="true">
<div class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="deleteCommentModalLabel">Delete Comment</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="delete_comment.php" method="POST">
            <input type="hidden" name="comment_id" id="commentIdToDelete">
            <input type="hidden" name="resource_id" value="<?= $resource_id ?>">
            
            <div class="modal-body">
                <p>Are you sure you want to delete this comment?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete Comment</button>
            </div>
        </form>
    </div>
</div>
</div>

<script>
// Set comment ID when delete button is clicked
document.addEventListener('DOMContentLoaded', function() {
    var deleteCommentModal = document.getElementById('deleteCommentModal');
    if (deleteCommentModal) {
        deleteCommentModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var commentId = button.getAttribute('data-comment-id');
            document.getElementById('commentIdToDelete').value = commentId;
        });
    }
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>