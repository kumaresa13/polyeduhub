<?php
// File location: polyeduhub/student/resources/upload.php

// Include necessary files
require_once '../../includes/config.php';
require_once '../../includes/db-connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in
checkStudentLogin();

// Get user information
$user_id = $_SESSION['id'];

// Get resource categories
$categories = dbSelect("SELECT id, name FROM resource_categories");

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate and process file upload
    try {
        // File details
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
        $category_id = filter_input(INPUT_POST, 'category', FILTER_VALIDATE_INT);
        $tags = filter_input(INPUT_POST, 'tags', FILTER_SANITIZE_STRING);

        // File upload handling
        if (!isset($_FILES['resource_file']) || $_FILES['resource_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload failed.");
        }

        $file = $_FILES['resource_file'];
        
        // Validate file type and size
        $allowed_extensions = ALLOWED_EXTENSIONS;
        $max_file_size = UPLOAD_MAX_SIZE; // 10MB

        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_extensions)) {
            throw new Exception("Invalid file type. Allowed types: " . implode(', ', $allowed_extensions));
        }

        if ($file['size'] > $max_file_size) {
            throw new Exception("File too large. Maximum size is " . ($max_file_size / 1024 / 1024) . "MB");
        }

        // Generate unique filename
        $unique_filename = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
        $upload_path = RESOURCE_PATH . $category_id . '/';
        
        // Create category directory if it doesn't exist
        if (!is_dir($upload_path)) {
            mkdir($upload_path, 0755, true);
        }

        $full_path = $upload_path . $unique_filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $full_path)) {
            throw new Exception("Failed to move uploaded file.");
        }

        // Create thumbnail for certain file types (optional)
        $thumbnail_path = null;
        if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
            // Thumbnail generation logic (simplified)
            $thumbnail_path = $upload_path . 'thumbnails/' . $unique_filename . '.jpg';
        }

        // Insert resource record
        $resource_id = dbInsert('resources', [
            'title' => $title,
            'description' => $description,
            'file_path' => str_replace(RESOURCE_PATH, '', $full_path),
            'file_type' => $file_ext,
            'file_size' => $file['size'],
            'thumbnail' => $thumbnail_path ? str_replace(RESOURCE_PATH, '', $thumbnail_path) : null,
            'category_id' => $category_id,
            'user_id' => $user_id,
            'status' => 'pending' // Admin approval required
        ]);

        // Handle tags
        if (!empty($tags)) {
            $tag_array = array_map('trim', explode(',', $tags));
            foreach ($tag_array as $tag_name) {
                // Find or create tag
                $tag_stmt = "INSERT INTO resource_tags (name) VALUES (?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)";
                $tag_id = dbExecute($tag_stmt, [$tag_name]);
                
                // Link resource to tag
                dbInsert('resource_tag_relationship', [
                    'resource_id' => $resource_id,
                    'tag_id' => $tag_id
                ]);
            }
        }

        // Award points for resource upload
        awardPoints($user_id, POINTS_UPLOAD, 'Resource Upload', "Uploaded resource: $title");

        // Set success message
        $_SESSION['upload_success'] = "Resource uploaded successfully and awaiting admin approval.";
        
        // Redirect to my resources page
        header("Location: my-resources.php");
        exit();

    } catch (Exception $e) {
        // Handle errors
        $_SESSION['upload_error'] = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Resource - PolyEduHub</title>
    <!-- Include necessary CSS and JS -->
    <?php include '../../includes/header.php'; ?>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid">
            <h1 class="mt-4">Upload Resource</h1>
            
            <!-- Error/Success Messages -->
            <?php if (isset($_SESSION['upload_error'])): ?>
                <div class="alert alert-danger">
                    <?= $_SESSION['upload_error']; unset($_SESSION['upload_error']); ?>
                </div>
            <?php endif; ?>
            
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="card">
                    <div class="card-body">
                        <div class="form-group">
                            <label for="title">Resource Title</label>
                            <input type="text" class="form-control" id="title" name="title" required 
                                   placeholder="Enter resource title (e.g., Database Management Notes)">
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"
                                      placeholder="Provide a brief description of the resource"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select class="form-control" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="tags">Tags (comma-separated)</label>
                            <input type="text" class="form-control" id="tags" name="tags"
                                   placeholder="Enter tags (e.g., database, sql, notes)">
                        </div>
                        
                        <div class="form-group">
                            <label for="resource_file">Upload File</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="resource_file" name="resource_file" required>
                                <label class="custom-file-label" for="resource_file">Choose file</label>
                            </div>
                            <small class="form-text text-muted">
                                Allowed types: <?= implode(', ', ALLOWED_EXTENSIONS) ?> 
                                (Max size: <?= UPLOAD_MAX_SIZE / 1024 / 1024 ?>MB)
                            </small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload Resource
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
    
    <script>
        // Update file input label with selected filename
        $('.custom-file-input').on('change', function() {
            var fileName = $(this).val().split('\\').pop();
            $(this).siblings('.custom-file-label').addClass('selected').html(fileName);
        });
    </script>
</body>
</html>