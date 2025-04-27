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
$page_title = "Upload Resource";

// Process form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs - replacing deprecated FILTER_SANITIZE_STRING
    $title = htmlspecialchars(trim($_POST['title'] ?? ''));
    $description = htmlspecialchars(trim($_POST['description'] ?? ''));
    $category_id = intval($_POST['category'] ?? 0);
    $tags = htmlspecialchars(trim($_POST['tags'] ?? ''));

    // Validate title
    if (empty($title)) {
        $message = "Title is required";
        $messageType = "danger";
    }
    // Validate category
    else if ($category_id <= 0) {
        $message = "Please select a valid category";
        $messageType = "danger";
    }
    // Validate file
    else if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $message = "File upload error: " . ($_FILES['file']['error'] ?? 'Unknown error');
        $messageType = "danger";
    } else {
        // Get file information
        $file = $_FILES['file'];
        $fileName = $file['name'];
        $fileTmpPath = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Check file size
        if ($fileSize > UPLOAD_MAX_SIZE) {
            $message = "File is too large. Maximum size is " . formatFileSize(UPLOAD_MAX_SIZE);
            $messageType = "danger";
        }
        // Check file extension
        else if (!in_array($fileType, ALLOWED_EXTENSIONS)) {
            $message = "Invalid file type. Allowed types: " . implode(', ', ALLOWED_EXTENSIONS);
            $messageType = "danger";
        } else {
            try {
                // Create resource directory if it doesn't exist
                $resourceDir = RESOURCE_PATH . date('Y/m/');
                if (!is_dir($resourceDir)) {
                    mkdir($resourceDir, 0755, true);
                }

                // Generate unique filename
                $newFileName = uniqid() . '_' . $fileName;
                $uploadPath = $resourceDir . $newFileName;
                $relativePath = date('Y/m/') . $newFileName;

                // Move the file
                if (move_uploaded_file($fileTmpPath, $uploadPath)) {
                    // Begin database transaction
                    $pdo = getDbConnection();
                    $pdo->beginTransaction();

                    // Insert resource
                    $stmt = $pdo->prepare("
                        INSERT INTO resources (title, description, file_path, file_type, file_size, category_id, user_id, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                    ");
                    $stmt->execute([$title, $description, $relativePath, $fileType, $fileSize, $category_id, $user_id]);
                    $resource_id = $pdo->lastInsertId();

                    // Process tags if provided
                    if (!empty($tags)) {
                        $tagArray = array_map('trim', explode(',', $tags));
                        foreach ($tagArray as $tag) {
                            if (empty($tag))
                                continue;

                            // Find or create tag
                            $stmt = $pdo->prepare("
                                INSERT INTO resource_tags (name) 
                                VALUES (?) 
                                ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)
                            ");
                            $stmt->execute([$tag]);
                            $tag_id = $pdo->lastInsertId();

                            // Link resource to tag
                            $stmt = $pdo->prepare("
                                INSERT INTO resource_tag_relationship (resource_id, tag_id)
                                VALUES (?, ?)
                            ");
                            $stmt->execute([$resource_id, $tag_id]);
                        }
                    }

                    // Commit transaction
                    $pdo->commit();

                    $message = "Resource uploaded successfully and waiting for approval!";
                    $messageType = "success";
                    
                    // Log the activity
                    logActivity($user_id, 'Resource Uploaded', "Uploaded resource: {$title}");

                    // Clear form after successful upload
                    $_POST = [];
                } else {
                    throw new Exception("Failed to move uploaded file");
                }
            } catch (Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $message = "Error: " . $e->getMessage();
                $messageType = "danger";
            }
        }
    }
}

// Get categories for dropdown
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT id, name FROM resource_categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $categories = [];
}

// Set variable for nested path to properly include header/footer
$nested = true;

// Include the header
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Upload Resource</h1>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <!-- Upload Form Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Upload New Resource</h6>
                </div>
                <div class="card-body">
                    <form action="upload.php" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="title" class="form-label">Resource Title <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required
                                value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                                placeholder="Enter resource title (e.g., Database Normalization Notes)">
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4"
                                placeholder="Provide a brief description of the resource"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" <?= (isset($_POST['category']) && $_POST['category'] == $category['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="tags" class="form-label">Tags (comma-separated)</label>
                            <input type="text" class="form-control" id="tags" name="tags"
                                value="<?= htmlspecialchars($_POST['tags'] ?? '') ?>"
                                placeholder="Enter tags (e.g., database, sql, normalization)">
                            <div class="form-text">Helps others find your resource. Example: java, programming, loops
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="file" class="form-label">Upload File <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="file" name="file" required>
                            <div class="form-text">
                                Allowed types: <?= implode(', ', ALLOWED_EXTENSIONS) ?> (Max size:
                                <?= formatFileSize(UPLOAD_MAX_SIZE) ?>)
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Upload Resource</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Upload Guidelines Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Upload Guidelines</h6>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h6><i class="fas fa-check-circle text-success me-2"></i> Quality Standards</h6>
                        <ul>
                            <li>Use clear, descriptive titles</li>
                            <li>Provide a thorough description</li>
                            <li>Choose the most appropriate category</li>
                            <li>Add relevant tags for better discoverability</li>
                        </ul>
                    </div>
                    <div>
                        <h6><i class="fas fa-exclamation-triangle text-warning me-2"></i> Restrictions</h6>
                        <ul>
                            <li>Do not upload copyrighted materials without permission</li>
                            <li>Respect academic integrity guidelines</li>
                            <li>Files must be under <?= formatFileSize(UPLOAD_MAX_SIZE) ?> in size</li>
                            <li>All uploads will be reviewed by administrators</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Points Info Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Earn Points</h6>
                </div>
                <div class="card-body">
                    <p>Earn points by uploading quality resources:</p>
                    <ul>
                        <li><strong><?= POINTS_UPLOAD ?> points</strong> for each approved resource</li>
                        <li><strong><?= POINTS_DOWNLOAD ?> point</strong> each time someone downloads your resource</li>
                        <li>Additional points for highly rated resources</li>
                    </ul>
                    <p class="mb-0">Points help you earn badges and climb the leaderboard!</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include the footer
include_once '../includes/footer.php';
?>