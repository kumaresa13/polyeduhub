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

// Get resource ID
$resource_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$resource_id) {
    $_SESSION['error_message'] = "Invalid resource ID";
    header("Location: index.php");
    exit();
}

// Initialize variables
$resource = null;
$comments = [];
$related_resources = [];
$is_favorited = false;
$user_rating = 0;

try {
    $pdo = getDbConnection();

    // Increment view count
    $stmt = $pdo->prepare("UPDATE resources SET view_count = view_count + 1 WHERE id = ? AND status = 'approved'");
    $stmt->execute([$resource_id]);

    // Get resource details
    $stmt = $pdo->prepare("
        SELECT r.*, rc.name as category_name, 
               u.id as uploader_id, u.first_name, u.last_name, u.profile_image, u.department,
               (SELECT COUNT(*) FROM resource_comments WHERE resource_id = r.id) as comment_count,
               (SELECT COALESCE(AVG(rating), 0) FROM resource_ratings WHERE resource_id = r.id) as avg_rating,
               (SELECT COUNT(*) FROM resource_ratings WHERE resource_id = r.id) as rating_count
        FROM resources r
        JOIN resource_categories rc ON r.category_id = rc.id
        JOIN users u ON r.user_id = u.id
        WHERE r.id = ? AND r.status = 'approved'
    ");
    $stmt->execute([$resource_id]);
    $resource = $stmt->fetch();

    if (!$resource) {
        $_SESSION['error_message'] = "Resource not found or not approved";
        header("Location: index.php");
        exit();
    }

    // Check if resource is in user's favorites
    $check_favorites_sql = "
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = 'resource_favorites'
    ";
    $favorites_table_exists = $pdo->query($check_favorites_sql)->fetchColumn();

    if ($favorites_table_exists) {
        $stmt = $pdo->prepare("SELECT id FROM resource_favorites WHERE resource_id = ? AND user_id = ?");
        $stmt->execute([$resource_id, $user_id]);
        $is_favorited = (bool) $stmt->fetchColumn();
    }

    // Get user's rating for this resource
    $stmt = $pdo->prepare("SELECT rating FROM resource_ratings WHERE resource_id = ? AND user_id = ?");
    $stmt->execute([$resource_id, $user_id]);
    $user_rating = $stmt->fetchColumn() ?: 0;

    // Get resource comments
    $stmt = $pdo->prepare("
        SELECT rc.*, u.first_name, u.last_name, u.profile_image, 
               (u.id = ?) as is_own_comment
        FROM resource_comments rc
        JOIN users u ON rc.user_id = u.id
        WHERE rc.resource_id = ?
        ORDER BY rc.created_at DESC
    ");
    $stmt->execute([$user_id, $resource_id]);
    $comments = $stmt->fetchAll();

    // Get resource tags
    $stmt = $pdo->prepare("
        SELECT rt.name
        FROM resource_tags rt
        JOIN resource_tag_relationship rtr ON rt.id = rtr.tag_id
        WHERE rtr.resource_id = ?
    ");
    $stmt->execute([$resource_id]);
    $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get related resources (same category or tags)
    $stmt = $pdo->prepare("
        SELECT r.id, r.title, r.file_type, r.download_count, r.created_at
        FROM resources r
        WHERE r.id != ? 
          AND r.status = 'approved'
          AND (r.category_id = ? OR r.id IN (
              SELECT DISTINCT rtr.resource_id
              FROM resource_tag_relationship rtr
              JOIN resource_tag_relationship rtr2 ON rtr.tag_id = rtr2.tag_id
              WHERE rtr2.resource_id = ?
          ))
        ORDER BY r.download_count DESC
        LIMIT 5
    ");
    $stmt->execute([$resource_id, $resource['category_id'], $resource_id]);
    $related_resources = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error in resource view: " . $e->getMessage());
    $_SESSION['error_message'] = "An error occurred while retrieving resource information";
    header("Location: index.php");
    exit();
}

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    $comment_text = trim($_POST['comment']);

    if (!empty($comment_text)) {
        try {
            // Start transaction
            $pdo->beginTransaction();

            // Insert comment
            $stmt = $pdo->prepare("
                INSERT INTO resource_comments (resource_id, user_id, comment, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$resource_id, $user_id, $comment_text]);

            // Award points for commenting
            awardPoints(
                $user_id,
                POINTS_COMMENT,
                'Posted Comment',
                "Commented on resource: {$resource['title']}"
            );

            // If comment is on someone else's resource, create notification
            if ($user_id != $resource['uploader_id']) {
                createNotification(
                    $resource['uploader_id'],
                    "New comment on your resource: {$resource['title']}",
                    "view.php?id=$resource_id#comments"
                );
            }

            // Commit transaction
            $pdo->commit();

            // Redirect to avoid form resubmission
            header("Location: view.php?id=$resource_id#comments");
            exit();
        } catch (PDOException $e) {
            // Rollback transaction on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error adding comment: " . $e->getMessage());
            $_SESSION['error_message'] = "Error posting comment: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Comment cannot be empty";
    }
}

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rating'])) {
    $rating = intval($_POST['rating']);

    if ($rating >= 1 && $rating <= 5) {
        try {
            // Check if the resource_ratings table exists
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = 'resource_ratings'
            ");
            $stmt->execute();
            $tableExists = $stmt->fetchColumn() > 0;

            // Create table if it doesn't exist
            if (!$tableExists) {
                $pdo->exec("CREATE TABLE `resource_ratings` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `resource_id` int(11) NOT NULL,
                    `user_id` int(11) NOT NULL,
                    `rating` int(11) NOT NULL,
                    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `resource_user` (`resource_id`, `user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            }

            // Check if user has already rated this resource
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM resource_ratings WHERE resource_id = ? AND user_id = ?");
            $stmt->execute([$resource_id, $user_id]);
            $has_rated = (bool) $stmt->fetchColumn();

            // Start transaction only after all checks
            $pdo->beginTransaction();

            if ($has_rated) {
                // Update existing rating
                $stmt = $pdo->prepare("
                    UPDATE resource_ratings 
                    SET rating = ?, updated_at = NOW()
                    WHERE resource_id = ? AND user_id = ?
                ");
                $stmt->execute([$rating, $resource_id, $user_id]);
            } else {
                // Insert new rating
                $stmt = $pdo->prepare("
                    INSERT INTO resource_ratings (resource_id, user_id, rating, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$resource_id, $user_id, $rating]);

                // Award points for first-time rating
                awardPoints(
                    $user_id,
                    POINTS_RATING,
                    'Rated Resource',
                    "Rated resource: {$resource['title']}"
                );

                // Notify resource owner if it's someone else's resource
                if ($user_id != $resource['uploader_id']) {
                    createNotification(
                        $resource['uploader_id'],
                        "Someone rated your resource: {$resource['title']} ({$rating} stars)",
                        "view.php?id=$resource_id"
                    );
                }
            }

            // Commit transaction
            $pdo->commit();

            // Redirect to avoid form resubmission
            header("Location: view.php?id=$resource_id");
            exit();
        } catch (PDOException $e) {
            // Rollback transaction on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error adding rating: " . $e->getMessage());
            $_SESSION['error_message'] = "Error submitting rating: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Invalid rating value";
    }
}

// Set page title and nested path variable
$page_title = "View Resource: " . ($resource['title'] ?? '');
$nested = true;

// Include header
include_once '../includes/header.php';
?>


<style>
    .btn-favorite {
        background-color: white;
        border: 1px solid #dc3545;
        color: #dc3545;
        transition: all 0.3s ease;
        padding: 8px 16px;
        border-radius: 20px;
    }

    .btn-favorite:hover {
        background-color: #ffebee;
    }

    .btn-favorite.favorited {
        background-color: #dc3545;
        color: white;
    }

    .btn-favorite i {
        margin-right: 5px;
    }

    .btn-favorite.favorited:hover {
        background-color: #c82333;
    }

    .toast-notification {
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 15px 20px;
        background: #333;
        color: white;
        border-radius: 5px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
        z-index: 9999;
        font-size: 14px;
        transition: transform 0.3s ease, opacity 0.3s ease;
        max-width: 300px;
    }

    .toast-notification.error {
        background: #f44336;
    }

    .toast-notification.success {
        background: #4CAF50;
    }

    .toast-notification i {
        margin-right: 8px;
    }

    .toast-notification.hide {
        transform: translateY(30px);
        opacity: 0;
    }
</style>


<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= htmlspecialchars($resource['title']) ?></h1>
        <a href="index.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back to Resources
        </a>
    </div>

    <!-- Display Messages -->
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['success_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <div class="row">
        <!-- Resource Details -->
        <div class="col-lg-8">
            <!-- Resource Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Resource Details</h6>
                    <div class="d-flex">
                        <!-- Favorite Button  -->
                        <button class="btn btn-favorite <?= $is_favorited ? 'favorited' : '' ?> me-2"
                            data-resource-id="<?= $resource_id ?>"
                            title="<?= $is_favorited ? 'Remove from favorites' : 'Add to favorites' ?>">
                            <i class="<?= $is_favorited ? 'fas' : 'far' ?> fa-heart"></i>
                            <span><?= $is_favorited ? 'Favorited' : 'Add to Favorites' ?></span>
                        </button>

                        <!-- Download Button -->
                        <a href="download.php?id=<?= $resource_id ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-download"></i>
                            <span class="d-none d-md-inline ms-1">Download</span>
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-3 text-center mb-3 mb-md-0">
                            <!-- Resource Icon -->
                            <?php
                            // Select icon based on file type
                            $icon_class = 'fa-file';
                            switch (strtolower($resource['file_type'])) {
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
                                case 'txt':
                                    $icon_class = 'fa-file-alt';
                                    break;
                            }
                            ?>
                            <div class="mb-3">
                                <i class="fas <?= $icon_class ?> fa-6x text-primary"></i>
                            </div>
                            <div class="font-weight-bold text-uppercase mb-1">
                                <?= strtoupper($resource['file_type']) ?> File
                            </div>
                            <div class="small text-muted">
                                <?= formatFileSize($resource['file_size']) ?>
                            </div>
                        </div>

                        <div class="col-md-9">
                            <!-- Resource Description -->
                            <h5 class="font-weight-bold">Description</h5>
                            <p>
                                <?php if (!empty($resource['description'])): ?>
                                    <?= nl2br(htmlspecialchars($resource['description'])) ?>
                                <?php else: ?>
                                    <span class="text-muted">No description provided.</span>
                                <?php endif; ?>
                            </p>

                            <!-- Resource Details -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="mb-1">
                                        <i class="fas fa-folder me-2 text-primary"></i>
                                        <strong>Category:</strong> <?= htmlspecialchars($resource['category_name']) ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-calendar me-2 text-primary"></i>
                                        <strong>Uploaded:</strong>
                                        <?= date('F j, Y', strtotime($resource['created_at'])) ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-download me-2 text-primary"></i>
                                        <strong>Downloads:</strong> <?= number_format($resource['download_count']) ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1">
                                        <i class="fas fa-eye me-2 text-primary"></i>
                                        <strong>Views:</strong> <?= number_format($resource['view_count']) ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-comment me-2 text-primary"></i>
                                        <strong>Comments:</strong> <?= number_format($resource['comment_count']) ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-star me-2 text-primary"></i>
                                        <strong>Rating:</strong>
                                        <?= number_format($resource['avg_rating'], 1) ?>/5
                                        <span class="text-muted">(<?= $resource['rating_count'] ?> ratings)</span>
                                    </p>
                                </div>
                            </div>

                            <!-- Tags -->
                            <?php if (!empty($tags)): ?>
                                <div class="mb-3">
                                    <h6 class="font-weight-bold">Tags:</h6>
                                    <div>
                                        <?php foreach ($tags as $tag): ?>
                                            <a href="index.php?search=<?= urlencode($tag) ?>"
                                                class="badge bg-secondary text-decoration-none me-1">
                                                <?= htmlspecialchars($tag) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Uploader Info -->
                            <div class="mt-4">
                                <h6 class="font-weight-bold">Uploaded by:</h6>
                                <div class="d-flex align-items-center">
                                    <img src="<?= $resource['profile_image'] ? htmlspecialchars($resource['profile_image']) : '../../assets/img/ui/default-profile.png' ?>"
                                        class="rounded-circle me-2"
                                        alt="<?= htmlspecialchars($resource['first_name']) ?>" width="40" height="40">
                                    <div>
                                        <a href="../profile/index.php?id=<?= $resource['uploader_id'] ?>"
                                            class="text-decoration-none">
                                            <?= htmlspecialchars($resource['first_name'] . ' ' . $resource['last_name']) ?>
                                        </a>
                                        <div class="small text-muted">
                                            <?= htmlspecialchars($resource['department'] ?: 'Department not specified') ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rating System -->
                    <div class="card mb-4 border-left-warning">
                        <div class="card-body">
                            <h5 class="font-weight-bold">Rate this Resource</h5>
                            <form action="" method="POST" class="rating-form">
                                <div class="mb-3">
                                    <div class="btn-group" role="group" aria-label="Rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <input type="radio" class="btn-check" name="rating" id="rating<?= $i ?>"
                                                value="<?= $i ?>" <?= $user_rating == $i ? 'checked' : '' ?>>
                                            <label class="btn btn-outline-warning" for="rating<?= $i ?>">
                                                <?= $i ?> <i class="fas fa-star"></i>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <?= $user_rating > 0 ? 'Update Rating' : 'Submit Rating' ?>
                                </button>
                                <?php if ($user_rating > 0): ?>
                                    <div class="small text-muted mt-2">You rated this resource <?= $user_rating ?>
                                        star<?= $user_rating > 1 ? 's' : '' ?>.</div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <!-- Download Section -->
                    <div class="text-center p-4 bg-light rounded">
                        <h5 class="mb-3">Ready to Download?</h5>
                        <a href="download.php?id=<?= $resource_id ?>" class="btn btn-success btn-lg">
                            <i class="fas fa-download me-2"></i> Download Resource
                        </a>
                        <div class="small text-muted mt-2">File Size: <?= formatFileSize($resource['file_size']) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Comments Section -->
            <div class="card shadow mb-4" id="comments">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Comments (<?= count($comments) ?>)
                    </h6>
                </div>
                <div class="card-body">
                    <!-- Comment Form -->
                    <div class="mb-4">
                        <h5 class="font-weight-bold mb-3">Add a Comment</h5>
                        <form action="view.php?id=<?= $resource_id ?>#comments" method="POST">
                            <div class="mb-3">
                                <textarea class="form-control" name="comment" rows="3"
                                    placeholder="Write your comment here..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Post Comment</button>
                        </form>
                    </div>

                    <!-- Comments List -->
                    <?php if (empty($comments)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="far fa-comment-dots fa-3x mb-3"></i>
                            <p>No comments yet. Be the first to leave a comment!</p>
                        </div>
                    <?php else: ?>
                        <div class="comments-list">
                            <?php foreach ($comments as $comment): ?>
                                <div class="comment-item d-flex mb-4">
                                    <div class="flex-shrink-0 me-3">
                                        <img src="<?= $comment['profile_image'] ? htmlspecialchars($comment['profile_image']) : '../../assets/img/ui/default-profile.png' ?>"
                                            class="rounded-circle" alt="<?= htmlspecialchars($comment['first_name']) ?>"
                                            width="50" height="50">
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <div class="fw-bold">
                                                <?= htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']) ?>
                                                <?php if ($comment['is_own_comment']): ?>
                                                    <span class="badge bg-primary ms-1">You</span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted"><?= time_elapsed_string($comment['created_at']) ?></small>
                                        </div>
                                        <div class="mb-2"><?= nl2br(htmlspecialchars($comment['comment'])) ?></div>

                                        <?php if ($comment['is_own_comment'] || $user_id == $resource['uploader_id']): ?>
                                            <div class="comment-actions small">
                                                <?php if ($comment['is_own_comment']): ?>
                                                    <a href="edit_comment.php?id=<?= $comment['id'] ?>&resource_id=<?= $resource_id ?>"
                                                        class="text-primary me-2">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                <?php endif; ?>

                                                <?php if ($comment['is_own_comment'] || $user_id == $resource['uploader_id']): ?>
                                                    <a href="delete_comment.php?id=<?= $comment['id'] ?>&resource_id=<?= $resource_id ?>"
                                                        class="text-danger"
                                                        onclick="return confirm('Are you sure you want to delete this comment?');">
                                                        <i class="fas fa-trash-alt"></i> Delete
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Related Resources -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Related Resources</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($related_resources)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-search fa-2x mb-3"></i>
                            <p>No related resources found</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($related_resources as $related): ?>
                                <a href="view.php?id=<?= $related['id'] ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0 me-3">
                                            <?php
                                            // Select icon based on file type
                                            $rel_icon_class = 'fa-file';
                                            switch (strtolower($related['file_type'])) {
                                                case 'pdf':
                                                    $rel_icon_class = 'fa-file-pdf';
                                                    break;
                                                case 'doc':
                                                case 'docx':
                                                    $rel_icon_class = 'fa-file-word';
                                                    break;
                                                case 'xls':
                                                case 'xlsx':
                                                    $rel_icon_class = 'fa-file-excel';
                                                    break;
                                                case 'ppt':
                                                case 'pptx':
                                                    $rel_icon_class = 'fa-file-powerpoint';
                                                    break;
                                                case 'zip':
                                                case 'rar':
                                                    $rel_icon_class = 'fa-file-archive';
                                                    break;
                                                case 'jpg':
                                                case 'jpeg':
                                                case 'png':
                                                    $rel_icon_class = 'fa-file-image';
                                                    break;
                                            }
                                            ?>
                                            <i class="fas <?= $rel_icon_class ?> fa-2x text-primary"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold"><?= htmlspecialchars($related['title']) ?></div>
                                            <div class="small text-muted">
                                                <i class="fas fa-download me-1"></i> <?= $related['download_count'] ?> downloads
                                                • <?= time_elapsed_string($related['created_at']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Resource Statistics -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Resource Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="h3"><?= number_format($resource['view_count']) ?></div>
                            <div class="small text-muted">Views</div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="h3"><?= number_format($resource['download_count']) ?></div>
                            <div class="small text-muted">Downloads</div>
                        </div>
                        <div class="col-6">
                            <div class="h3"><?= number_format($resource['comment_count']) ?></div>
                            <div class="small text-muted">Comments</div>
                        </div>
                        <div class="col-6">
                            <div class="h3"><?= number_format($resource['rating_count']) ?></div>
                            <div class="small text-muted">Ratings</div>
                        </div>
                    </div>

                    <!-- Rating Breakdown -->
                    <?php if ($resource['rating_count'] > 0): ?>
                        <hr>
                        <h6 class="font-weight-bold mb-3">Rating Breakdown</h6>

                        <?php
                        // Get rating distribution
                        $stmt = $pdo->prepare("
                        SELECT rating, COUNT(*) as count
                        FROM resource_ratings
                        WHERE resource_id = ?
                        GROUP BY rating
                        ORDER BY rating DESC
                    ");
                        $stmt->execute([$resource_id]);
                        $rating_distribution = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

                        // Fill in missing ratings with 0
                        for ($i = 5; $i >= 1; $i--) {
                            if (!isset($rating_distribution[$i])) {
                                $rating_distribution[$i] = 0;
                            }
                        }

                        // Sort by rating (highest first)
                        krsort($rating_distribution);

                        foreach ($rating_distribution as $rating => $count):
                            $percentage = ($count / $resource['rating_count']) * 100;
                            ?>
                            <div class="d-flex align-items-center mb-2">
                                <div class="me-2" style="width: 80px;">
                                    <?= $rating ?> <i class="fas fa-star text-warning"></i>
                                </div>
                                <div class="progress flex-grow-1" style="height: 8px;">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $percentage ?>%"
                                        aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div class="ms-2 small">
                                    <?= $count ?> (<?= round($percentage) ?>%)
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Actions Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="download.php?id=<?= $resource_id ?>" class="btn btn-success">
                            <i class="fas fa-download me-1"></i> Download Resource
                        </a>

                        <button class="btn btn-favorite <?= $is_favorited ? 'favorited' : '' ?>"
                            data-resource-id="<?= $resource_id ?>">
                            <i class="<?= $is_favorited ? 'fas' : 'far' ?> fa-heart me-1"></i>
                            <?= $is_favorited ? 'Remove from Favorites' : 'Add to Favorites' ?>
                        </button>

                        <a href="#comments" class="btn btn-outline-primary">
                            <i class="fas fa-comment me-1"></i> Leave a Comment
                        </a>

                        <a href="report.php?id=<?= $resource_id ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-flag me-1"></i> Report Issue
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Handle favorite button click
    document.querySelectorAll('.btn-favorite').forEach(btn => {
        btn.addEventListener('click', function () {
            const resourceId = this.getAttribute('data-resource-id');
            const isFavorited = this.classList.contains('favorited');

            // Send request to toggle favorite status
            fetch('toggle_favorite.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `resource_id=${resourceId}&action=${isFavorited ? 'remove' : 'add'}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update button appearance
                        const icon = this.querySelector('i');
                        const buttonText = this.querySelector('span');

                        if (isFavorited) {
                            this.classList.remove('favorited');
                            icon.classList.replace('fas', 'far');
                            buttonText.textContent = 'Add to Favorites';
                            this.setAttribute('title', 'Add to favorites');
                        } else {
                            this.classList.add('favorited');
                            icon.classList.replace('far', 'fas');
                            buttonText.textContent = 'Favorited';
                            this.setAttribute('title', 'Remove from favorites');
                        }
                    } else {
                        // Create a custom toast notification instead of an alert
                        const toast = document.createElement('div');
                        toast.className = 'toast-notification error';
                        toast.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${data.message}`;
                        document.body.appendChild(toast);

                        // Remove toast after 3 seconds
                        setTimeout(() => {
                            toast.classList.add('hide');
                            setTimeout(() => toast.remove(), 500);
                        }, 3000);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Also show network errors nicely
                    const toast = document.createElement('div');
                    toast.className = 'toast-notification error';
                    toast.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error. Please try again.';
                    document.body.appendChild(toast);

                    setTimeout(() => {
                        toast.classList.add('hide');
                        setTimeout(() => toast.remove(), 500);
                    }, 3000);
                });
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>