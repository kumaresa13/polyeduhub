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
    header("Location: my-resources.php");
    exit();
}

// Get resource information
$pdo = getDbConnection();
$stmt = $pdo->prepare("
    SELECT r.*, rc.name as category_name
    FROM resources r
    JOIN resource_categories rc ON r.category_id = rc.id
    WHERE r.id = ? AND r.user_id = ?
");
$stmt->execute([$resource_id, $user_id]);
$resource = $stmt->fetch();

if (!$resource) {
    $_SESSION['error_message'] = "Resource not found or you don't have permission to edit it";
    header("Location: my-resources.php");
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
$tags_string = implode(', ', $tags);

// Get resource categories
$stmt = $pdo->prepare("SELECT id, name FROM resource_categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $title = filter_var($_POST['title'], FILTER_SANITIZE_STRING);
    $description = filter_var($_POST['description'], FILTER_SANITIZE_STRING);
    $category_id = intval($_POST['category']);
    $tags = filter_var($_POST['tags'], FILTER_SANITIZE_STRING);
    
    // Validate inputs
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    
    if (!$category_id) {
        $errors[] = "Category is required";
    }
    
    // If no errors, update the resource
    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Update resource
            $stmt = $pdo->prepare("
                UPDATE resources
                SET title = ?, description = ?, category_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$title, $description, $category_id, $resource_id]);
            
            // Handle tags
            if (!empty($tags)) {
                // Delete existing tags
                $stmt = $pdo->prepare("DELETE FROM resource_tag_relationship WHERE resource_id = ?");
                $stmt->execute([$resource_id]);
                
                // Add new tags
                $tag_array = array_map('trim', explode(',', $tags));
                foreach ($tag_array as $tag_name) {
                    // Find or create tag
                    $tag_stmt = $pdo->prepare("
                        INSERT INTO resource_tags (name) 
                        VALUES (?) 
                        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
                    ");
                    $tag_stmt->execute([$tag_name]);
                    $tag_id = $pdo->lastInsertId();
                    
                    // Link resource to tag
                    $link_stmt = $pdo->prepare("
                        INSERT INTO resource_tag_relationship (resource_id, tag_id) 
                        VALUES (?, ?)
                    ");
                    $link_stmt->execute([$resource_id, $tag_id]);
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            $_SESSION['success_message'] = "Resource updated successfully";
            header("Location: my-resources.php");
            exit();
        } catch (Exception $e) {
            // Rollback on error
            $pdo->rollBack();
            $errors[] = "Error updating resource: " . $e->getMessage();
        }
    }
}

// Page title
$page_title = "Edit Resource";
$nested = true;

// Include header
include_once '../includes/header.php';
?>

<!-- Page CSS -->
<style>
    .card {
        border: none;
        box-shadow: 0 0 1rem rgba(0, 0, 0, 0.1);
        margin-bottom: 1.5rem;
    }
    
    .card-header {
        background-color: transparent;
        border-bottom: 1px solid rgba(0, 0, 0, 0.125);
        padding: 1rem 1.5rem;
    }
    
    .card-body {
        padding: 1.5rem;
    }
    
    .form-label {
        font-weight: 600;
        color: #344767;
        margin-bottom: 0.5rem;
    }
    
    .form-control, .form-select {
        border-radius: 0.375rem;
        border: 1px solid #d2d6da;
        padding: 0.625rem 0.875rem;
        font-size: 0.875rem;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #80bdff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }
    
    .input-group-text {
        background-color: #f8f9fa;
        border: 1px solid #d2d6da;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        padding: 0.625rem 0.875rem;
    }
    
    .badge {
        padding: 0.5em 0.75em;
        font-weight: 600;
        font-size: 0.75rem;
    }
    
    .btn {
        padding: 0.625rem 1.25rem;
        font-weight: 600;
        font-size: 0.875rem;
        border-radius: 0.375rem;
    }
    
    .btn-primary {
        background-color: #4e73df;
        border-color: #4e73df;
    }
    
    .btn-primary:hover {
        background-color: #2e59d9;
        border-color: #2653d4;
    }
    
    .btn-secondary {
        background-color: #858796;
        border-color: #858796;
    }
    
    .btn-secondary:hover {
        background-color: #6e707e;
        border-color: #6b6d7d;
    }
    
    .text-primary {
        color: #4e73df !important;
    }
    
    .text-xs {
        font-size: 0.7rem;
    }
    
    .text-gray-600 {
        color: #858796;
    }
    
    .resource-info-item {
        margin-bottom: 1rem;
    }
    
    .resource-info-item:last-child {
        margin-bottom: 0;
    }
    
    .resource-info-label {
        font-weight: 600;
        color: #344767;
        margin-bottom: 0.25rem;
    }
    
    .resource-info-value {
        color: #666;
    }
</style>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Edit Resource</h1>
    </div>
    
    <!-- Display errors if any -->
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
            <li><?= $error ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <!-- Edit Resource Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Resource Details</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label for="title" class="form-label">Resource Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($resource['title']) ?>" required>
                        </div>
                        
                        <div class="mb-4">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($resource['description']) ?></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>" <?= $category['id'] == $resource['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="tags" class="form-label">Tags (comma-separated)</label>
                            <input type="text" class="form-control" id="tags" name="tags" value="<?= htmlspecialchars($tags_string) ?>">
                            <div class="form-text text-muted">Add tags to help others find your resource easier, e.g., "java, programming, database"</div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">File</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <?php
                                    // Select icon based on file type
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
                                    <i class="fas <?= $icon_class ?>"></i>
                                </span>
                                <input type="text" class="form-control" value="<?= basename($resource['file_path']) ?> (<?= formatFileSize($resource['file_size']) ?>)" readonly>
                                <a href="download.php?id=<?= $resource_id ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            </div>
                            <div class="form-text text-muted">To replace the file, delete this resource and upload a new one.</div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="my-resources.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Back to My Resources
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Resource Information Card -->
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Resource Information</h6>
                </div>
                <div class="card-body">
                    <div class="resource-info-item">
                        <div class="resource-info-label">Status</div>
                        <div class="resource-info-value">
                            <?php if ($resource['status'] === 'approved'): ?>
                            <span class="badge bg-success">Approved</span>
                            <?php elseif ($resource['status'] === 'pending'): ?>
                            <span class="badge bg-warning text-dark">Pending Approval</span>
                            <?php else: ?>
                            <span class="badge bg-danger">Rejected</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="resource-info-item">
                        <div class="resource-info-label">Uploaded</div>
                        <div class="resource-info-value"><?= date('F j, Y, g:i a', strtotime($resource['created_at'])) ?></div>
                    </div>
                    
                    <div class="resource-info-item">
                        <div class="resource-info-label">Downloads</div>
                        <div class="resource-info-value"><?= number_format($resource['download_count']) ?></div>
                    </div>
                    
                    <div class="resource-info-item">
                        <div class="resource-info-label">Views</div>
                        <div class="resource-info-value"><?= number_format($resource['view_count']) ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Resource Tips Card -->
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Tips for Better Resources</h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0" style="list-style: none; padding-left: 0;">
                        <li class="mb-3">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            Use a clear, descriptive title that explains what the resource is about
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            Add a detailed description to help users understand what they'll get
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            Choose the most accurate category for your resource
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            Add relevant tags to increase discoverability
                        </li>
                        <li>
                            <i class="fas fa-check-circle text-success me-2"></i>
                            Resources with complete information tend to get more downloads
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>