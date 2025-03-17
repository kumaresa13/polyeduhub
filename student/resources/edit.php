<?php
// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db-connection.php';
require_once '../../includes/functions.php';

// Start session and check if user is logged in
session_start();
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - <?= $page_title ?></title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="../../assets/img/favicon.png" type="image/x-icon">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/css/styles.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon rotate-n-15">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="sidebar-brand-text mx-3">PolyEduHub</div>
        </div>
        
        <hr class="sidebar-divider">
        
        <div class="sidebar-heading">
            Navigation
        </div>
        
        <ul class="nav">
            <li class="nav-item">
                <a class="nav-link" href="../dashboard.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <hr class="sidebar-divider">
            
            <div class="sidebar-heading">
                Resources
            </div>
            
            <li class="nav-item">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-fw fa-folder"></i>
                    <span>Browse Resources</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="upload.php">
                    <i class="fas fa-fw fa-file-upload"></i>
                    <span>Upload Resource</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link active" href="my-resources.php">
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
            
            <div class="sidebar-heading">
                Community
            </div>
            
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
            
            <div class="sidebar-heading">
                Account
            </div>
            
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
            
            <hr class="sidebar-divider">
            
            <li class="nav-item">
                <a class="nav-link" href="../../logout.php">
                    <i class="fas fa-fw fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>
    
    <!-- Content Wrapper -->
    <div class="content">
        <!-- Topbar -->
        <nav class="navbar navbar-expand-lg navbar-light mb-4">
            <div class="container-fluid">
                <button class="btn toggle-sidebar" id="toggleSidebar">
                    <i class="fas fa-bars"></i>
                </button>
                
                <a href="my-resources.php" class="btn btn-link text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i> Back to My Resources
                </a>
                
                <div class="navbar-nav ms-auto">
                    <!-- User Information -->
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle user-info" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="d-none d-lg-inline text-gray-600 small me-2"><?= htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) ?></span>
                            <img src="../../assets/img/ui/default-profile.png" alt="Profile Image">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="../profile/index.php"><i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="../profile/edit.php"><i class="fas fa-cogs fa-sm fa-fw me-2 text-gray-400"></i> Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../logout.php"><i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- Page Content -->
        <div class="container-fluid">
            <!-- Page Heading -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Edit Resource</h1>
            </div>
            
            <!-- Display errors if any -->
            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <!-- Edit Resource Form -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Resource Details</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Resource Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($resource['title']) ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($resource['description']) ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>" <?= $category['id'] == $resource['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars($category['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="tags" class="form-label">Tags (comma-separated)</label>
                                    <input type="text" class="form-control" id="tags" name="tags" value="<?= htmlspecialchars($tags_string) ?>">
                                    <div class="form-text">Add tags to help others find your resource easier, e.g., "java, programming, database"</div>
                                </div>
                                
                                <div class="mb-3">
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
                                    <div class="form-text">To replace the file, delete this resource and upload a new one.</div>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="my-resources.php" class="btn btn-secondary me-md-2">Cancel</a>
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <!-- Resource Information Card -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Resource Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="fw-bold">Status</label>
                                <div>
                                    <?php if ($resource['status'] === 'approved'): ?>
                                    <span class="badge bg-success">Approved</span>
                                    <?php elseif ($resource['status'] === 'pending'): ?>
                                    <span class="badge bg-warning text-dark">Pending Approval</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">Rejected</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="fw-bold">Uploaded</label>
                                <div><?= date('F j, Y, g:i a', strtotime($resource['created_at'])) ?></div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="fw-bold">Downloads</label>
                                <div><?= number_format($resource['download_count']) ?></div>
                            </div>
                            
                            <div>
                            <label class="fw-bold">Views</label>
                                <div><?= number_format($resource['view_count']) ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Resource Tips Card -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Tips for Better Resources</h6>
                        </div>
                        <div class="card-body">
                            <ul class="small mb-0">
                                <li class="mb-2">Use a clear, descriptive title that explains what the resource is about</li>
                                <li class="mb-2">Add a detailed description to help users understand what they'll get</li>
                                <li class="mb-2">Choose the most accurate category for your resource</li>
                                <li class="mb-2">Add relevant tags to increase discoverability</li>
                                <li>Resources with complete information tend to get more downloads</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="sticky-footer bg-white mt-4">
            <div class="container my-auto">
                <div class="copyright text-center my-auto">
                    <span>&copy; <?= date('Y') ?> PolyEduHub. All rights reserved.</span>
                </div>
            </div>
        </footer>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="../../assets/js/scripts.js"></script>
    
    <script>
        // Toggle sidebar on mobile
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
            document.querySelector('.content').classList.toggle('pushed');
        });
    </script>
</body>
</html>