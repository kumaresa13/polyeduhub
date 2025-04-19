<?php
// File path: admin/resources/categories.php

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

// Handle form submissions
$message = "";
$message_type = "";

// Add new category
if (isset($_POST['add_category'])) {
    $category_name = filter_var($_POST['category_name'], FILTER_SANITIZE_STRING);
    $category_description = filter_var($_POST['category_description'], FILTER_SANITIZE_STRING);
    $parent_id = isset($_POST['parent_id']) && !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    
    if (empty($category_name)) {
        $message = "Category name is required";
        $message_type = "danger";
    } else {
        try {
            $pdo = getDbConnection();
            
            // Check if category already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM resource_categories WHERE name = ?");
            $stmt->execute([$category_name]);
            $categoryExists = $stmt->fetchColumn() > 0;
            
            if ($categoryExists) {
                $message = "A category with this name already exists";
                $message_type = "danger";
            } else {
                // Insert new category
                $stmt = $pdo->prepare("
                    INSERT INTO resource_categories (name, description, parent_id) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$category_name, $category_description, $parent_id]);
                
                // Log action
                logAdminAction($admin_id, "Added category", "Added new resource category: " . $category_name);
                
                $message = "Category added successfully";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            error_log("Error adding category: " . $e->getMessage());
            $message = "Database error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Edit category
if (isset($_POST['edit_category'])) {
    $category_id = intval($_POST['category_id']);
    $category_name = filter_var($_POST['category_name'], FILTER_SANITIZE_STRING);
    $category_description = filter_var($_POST['category_description'], FILTER_SANITIZE_STRING);
    $parent_id = isset($_POST['parent_id']) && !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    
    if (empty($category_name)) {
        $message = "Category name is required";
        $message_type = "danger";
    } else {
        try {
            $pdo = getDbConnection();
            
            // Check if category already exists with this name (excluding current)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM resource_categories WHERE name = ? AND id != ?");
            $stmt->execute([$category_name, $category_id]);
            $categoryExists = $stmt->fetchColumn() > 0;
            
            if ($categoryExists) {
                $message = "A category with this name already exists";
                $message_type = "danger";
            } else {
                // Update category
                $stmt = $pdo->prepare("
                    UPDATE resource_categories 
                    SET name = ?, description = ?, parent_id = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$category_name, $category_description, $parent_id, $category_id]);
                
                // Log action
                logAdminAction($admin_id, "Updated category", "Updated resource category ID: " . $category_id);
                
                $message = "Category updated successfully";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            error_log("Error updating category: " . $e->getMessage());
            $message = "Database error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Delete category
if (isset($_POST['delete_category'])) {
    $category_id = intval($_POST['category_id']);
    
    try {
        $pdo = getDbConnection();
        
        // Check if category is in use
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM resources WHERE category_id = ?");
        $stmt->execute([$category_id]);
        $resourcesInCategory = $stmt->fetchColumn();
        
        // Check if category has children
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM resource_categories WHERE parent_id = ?");
        $stmt->execute([$category_id]);
        $hasChildren = $stmt->fetchColumn() > 0;
        
        if ($resourcesInCategory > 0) {
            $message = "Cannot delete category: It contains resources. Please reassign resources first.";
            $message_type = "danger";
        } elseif ($hasChildren) {
            $message = "Cannot delete category: It has subcategories. Please delete subcategories first.";
            $message_type = "danger";
        } else {
            // Get category name for logging
            $stmt = $pdo->prepare("SELECT name FROM resource_categories WHERE id = ?");
            $stmt->execute([$category_id]);
            $categoryName = $stmt->fetchColumn();
            
            // Delete category
            $stmt = $pdo->prepare("DELETE FROM resource_categories WHERE id = ?");
            $stmt->execute([$category_id]);
            
            // Log action
            logAdminAction($admin_id, "Deleted category", "Deleted resource category: " . $categoryName);
            
            $message = "Category deleted successfully";
            $message_type = "success";
        }
    } catch (PDOException $e) {
        error_log("Error deleting category: " . $e->getMessage());
        $message = "Database error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Get all categories
try {
    $pdo = getDbConnection();
    
    // Get categories with resource count
    $stmt = $pdo->prepare("
        SELECT rc.*, 
            (SELECT COUNT(*) FROM resources WHERE category_id = rc.id) AS resource_count,
            p.name AS parent_name
        FROM resource_categories rc
        LEFT JOIN resource_categories p ON rc.parent_id = p.id
        ORDER BY rc.name ASC
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error getting categories: " . $e->getMessage());
    $categories = [];
}

// Set page title and nested path variable
$page_title = "Manage Categories";
$nested = true;

// Include header
include_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Resource Categories</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="fas fa-plus fa-sm text-white-50"></i> Add New Category
        </button>
    </div>
    
    <!-- Display Messages -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Categories Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Manage Resource Categories</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="categoriesTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Category Name</th>
                            <th>Description</th>
                            <th>Parent Category</th>
                            <th>Resources</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?= htmlspecialchars($category['name']) ?></td>
                                <td><?= htmlspecialchars($category['description'] ?? 'No description') ?></td>
                                <td><?= htmlspecialchars($category['parent_name'] ?? 'None') ?></td>
                                <td><?= $category['resource_count'] ?></td>
                                <td>
                                    <button 
                                        class="btn btn-sm btn-primary edit-category" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editCategoryModal"
                                        data-id="<?= $category['id'] ?>"
                                        data-name="<?= htmlspecialchars($category['name']) ?>"
                                        data-description="<?= htmlspecialchars($category['description'] ?? '') ?>"
                                        data-parent="<?= $category['parent_id'] ?? '' ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button 
                                        class="btn btn-sm btn-danger delete-category" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deleteCategoryModal"
                                        data-id="<?= $category['id'] ?>"
                                        data-name="<?= htmlspecialchars($category['name']) ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No categories found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="category_name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="category_name" name="category_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="category_description" class="form-label">Description</label>
                        <textarea class="form-control" id="category_description" name="category_description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="parent_id" class="form-label">Parent Category (Optional)</label>
                        <select class="form-select" id="parent_id" name="parent_id">
                            <option value="">None</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="edit_category_id" name="category_id">
                    <div class="mb-3">
                        <label for="edit_category_name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="edit_category_name" name="category_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_category_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_category_description" name="category_description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_parent_id" class="form-label">Parent Category (Optional)</label>
                        <select class="form-select" id="edit_parent_id" name="parent_id">
                            <option value="">None</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_category" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Category Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="delete_category_id" name="category_id">
                    <p>Are you sure you want to delete the category: <strong id="delete_category_name"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> 
                        This action cannot be undone. Categories containing resources or having subcategories cannot be deleted.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_category" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit Category Modal
    const editButtons = document.querySelectorAll('.edit-category');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const description = this.getAttribute('data-description');
            const parent = this.getAttribute('data-parent');
            
            document.getElementById('edit_category_id').value = id;
            document.getElementById('edit_category_name').value = name;
            document.getElementById('edit_category_description').value = description;
            
            const parentSelect = document.getElementById('edit_parent_id');
            for (let i = 0; i < parentSelect.options.length; i++) {
                if (parentSelect.options[i].value === parent) {
                    parentSelect.options[i].selected = true;
                    break;
                }
            }
            
            // Prevent selecting self as parent
            for (let i = 0; i < parentSelect.options.length; i++) {
                if (parentSelect.options[i].value === id) {
                    parentSelect.options[i].disabled = true;
                } else {
                    parentSelect.options[i].disabled = false;
                }
            }
        });
    });
    
    // Delete Category Modal
    const deleteButtons = document.querySelectorAll('.delete-category');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            
            document.getElementById('delete_category_id').value = id;
            document.getElementById('delete_category_name').textContent = name;
        });
    });
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>