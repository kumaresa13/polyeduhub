<?php
/**
 * Admin Resource Categories Management
 * Place this file in: polyeduhub/admin/resources/categories.php
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

// Handle category operations (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error_message'] = "Invalid security token. Please try again.";
        header("Location: categories.php");
        exit();
    }
    
    // Get operation type
    $operation = isset($_POST['operation']) ? $_POST['operation'] : '';
    
    try {
        $pdo = getDbConnection();
        
        // Add new category
        if ($operation === 'add') {
            $name = trim($_POST['category_name']);
            $description = trim($_POST['category_description']);
            $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
            
            // Validate input
            if (empty($name)) {
                throw new Exception("Category name is required.");
            }
            
            // Check if category already exists
            $stmt = $pdo->prepare("SELECT id FROM resource_categories WHERE name = ?");
            $stmt->execute([$name]);
            if ($stmt->fetchColumn()) {
                throw new Exception("A category with this name already exists.");
            }
            
            // Insert new category
            $stmt = $pdo->prepare("
                INSERT INTO resource_categories (name, description, parent_id, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $description, $parent_id]);
            
            // Log the action
            logAdminAction($admin_id, "Category Created", "Created new category: $name");
            
            $_SESSION['success_message'] = "Category '{$name}' has been created successfully.";
        }
        // Edit existing category
        elseif ($operation === 'edit') {
            $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
            $name = trim($_POST['category_name']);
            $description = trim($_POST['category_description']);
            $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
            
            // Validate input
            if (empty($name)) {
                throw new Exception("Category name is required.");
            }
            
            if ($category_id <= 0) {
                throw new Exception("Invalid category ID.");
            }
            
            // Check if this would create a circular reference (parent can't be the category itself or its child)
            if ($parent_id !== null) {
                if ($parent_id == $category_id) {
                    throw new Exception("A category cannot be its own parent.");
                }
                
                // Check if parent_id is a child of this category (would create circular reference)
                // This is a simplified check - a more robust solution would check multiple levels
                $stmt = $pdo->prepare("SELECT id FROM resource_categories WHERE id = ? AND parent_id = ?");
                $stmt->execute([$parent_id, $category_id]);
                if ($stmt->fetchColumn()) {
                    throw new Exception("Cannot set a child category as the parent (circular reference).");
                }
            }
            
            // Check if another category with the same name exists
            $stmt = $pdo->prepare("SELECT id FROM resource_categories WHERE name = ? AND id != ?");
            $stmt->execute([$name, $category_id]);
            if ($stmt->fetchColumn()) {
                throw new Exception("Another category with this name already exists.");
            }
            
            // Update the category
            $stmt = $pdo->prepare("
                UPDATE resource_categories 
                SET name = ?, description = ?, parent_id = ? 
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $parent_id, $category_id]);
            
            // Log the action
            logAdminAction($admin_id, "Category Updated", "Updated category ID: $category_id, Name: $name");
            
            $_SESSION['success_message'] = "Category '{$name}' has been updated successfully.";
        }
        // Delete category
        elseif ($operation === 'delete') {
            $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
            
            // Validate input
            if ($category_id <= 0) {
                throw new Exception("Invalid category ID.");
            }
            
            // Get category name for logging
            $stmt = $pdo->prepare("SELECT name FROM resource_categories WHERE id = ?");
            $stmt->execute([$category_id]);
            $category_name = $stmt->fetchColumn();
            
            // Check if category has resources
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM resources WHERE category_id = ?");
            $stmt->execute([$category_id]);
            $resource_count = $stmt->fetchColumn();
            
            if ($resource_count > 0) {
                throw new Exception("Cannot delete this category because it has {$resource_count} resources. Please move or delete these resources first.");
            }
            
            // Check if category has children
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM resource_categories WHERE parent_id = ?");
            $stmt->execute([$category_id]);
            $child_count = $stmt->fetchColumn();
            
            if ($child_count > 0) {
                throw new Exception("Cannot delete this category because it has {$child_count} child categories. Please move or delete these subcategories first.");
            }
            
            // Delete the category
            $stmt = $pdo->prepare("DELETE FROM resource_categories WHERE id = ?");
            $stmt->execute([$category_id]);
            
            // Log the action
            logAdminAction($admin_id, "Category Deleted", "Deleted category ID: $category_id, Name: $category_name");
            
            $_SESSION['success_message'] = "Category '{$category_name}' has been deleted successfully.";
        }
        else {
            throw new Exception("Invalid operation.");
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    // Redirect to avoid form resubmission
    header("Location: categories.php");
    exit();
}

// Get all categories
try {
    $pdo = getDbConnection();
    
    $stmt = $pdo->prepare("
        SELECT 
            rc.id, rc.name, rc.description, rc.parent_id, rc.created_at,
            parent.name as parent_name,
            (SELECT COUNT(*) FROM resources WHERE category_id = rc.id) as resource_count,
            (SELECT COUNT(*) FROM resource_categories WHERE parent_id = rc.id) as child_count
        FROM resource_categories rc
        LEFT JOIN resource_categories parent ON rc.parent_id = parent.id
        ORDER BY rc.name ASC
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $categories = [];
    $_SESSION['error_message'] = "An error occurred while fetching categories.";
}

// Set page title
$page_title = "Resource Categories";

// Include header
include_once '../includes/header.php';
?>

<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Resource Categories</h1>
    <button type="button" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
        <i class="fas fa-plus fa-sm text-white-50"></i> Add New Category
    </button>
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

<!-- Categories Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Resource Categories (<?= count($categories) ?>)</h6>
    </div>
    <div class="card-body">
        <?php if (empty($categories)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-folder-open fa-4x mb-3"></i>
                <p>No categories found.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus fa-sm me-1"></i> Add New Category
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Parent Category</th>
                            <th>Resources</th>
                            <th>Subcategories</th>
                            <th>Created</th>
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
                                <td><?= $category['child_count'] ?></td>
                                <td><?= date('M d, Y', strtotime($category['created_at'])) ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-primary" 
                                            data-bs-toggle="modal" data-bs-target="#editCategoryModal"
                                            data-category-id="<?= $category['id'] ?>"
                                            data-category-name="<?= htmlspecialchars($category['name']) ?>"
                                            data-category-description="<?= htmlspecialchars($category['description'] ?? '') ?>"
                                            data-category-parent="<?= $category['parent_id'] ?? '' ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                            data-bs-toggle="modal" data-bs-target="#deleteCategoryModal"
                                            data-category-id="<?= $category['id'] ?>"
                                            data-category-name="<?= htmlspecialchars($category['name']) ?>"
                                            data-resource-count="<?= $category['resource_count'] ?>"
                                            data-child-count="<?= $category['child_count'] ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Category Tips Card -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Category Management Tips</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4 mb-4 mb-md-0">
                <h5 class="h6 font-weight-bold"><i class="fas fa-lightbulb text-warning me-2"></i> Organization</h5>
                <ul class="small">
                    <li>Use clear, descriptive names for categories</li>
                    <li>Keep the category structure simple and intuitive</li>
                    <li>Consider the needs of both uploaders and downloaders</li>
                    <li>Use parent-child relationships for related categories</li>
                </ul>
            </div>
            <div class="col-md-4 mb-4 mb-md-0">
                <h5 class="h6 font-weight-bold"><i class="fas fa-exclamation-triangle text-danger me-2"></i> Cautions</h5>
                <ul class="small">
                    <li>Deleting categories with resources is not allowed</li>
                    <li>Change category names with care as it affects existing resources</li>
                    <li>Avoid creating too many categories that may confuse users</li>
                    <li>Ensure categories don't overlap in purpose</li>
                </ul>
            </div>
            <div class="col-md-4">
                <h5 class="h6 font-weight-bold"><i class="fas fa-check-circle text-success me-2"></i> Best Practices</h5>
                <ul class="small">
                    <li>Review and update categories periodically</li>
                    <li>Monitor which categories are most used</li>
                    <li>Consider adding subcategories for popular categories</li>
                    <li>Get feedback from students on category organization</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="categories.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="operation" value="add">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="category_name" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="category_name" name="category_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="category_description" class="form-label">Description</label>
                        <textarea class="form-control" id="category_description" name="category_description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="parent_id" class="form-label">Parent Category</label>
                        <select class="form-select" id="parent_id" name="parent_id">
                            <option value="">None (Top Level)</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="categories.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="operation" value="edit">
                <input type="hidden" name="category_id" id="edit_category_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_category_name" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_category_name" name="category_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_category_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_category_description" name="category_description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_parent_id" class="form-label">Parent Category</label>
                        <select class="form-select" id="edit_parent_id" name="parent_id">
                            <option value="">None (Top Level)</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Category Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="categories.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="operation" value="delete">
                <input type="hidden" name="category_id" id="delete_category_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteCategoryModalLabel">Delete Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the category: <strong id="delete_category_name"></strong>?</p>
                    <div id="delete_warning" class="alert alert-danger" style="display: none;">
                        This category has resources or subcategories and cannot be deleted.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="confirm_delete_btn">Delete Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Additional JavaScript for this page -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle edit category modal
        const editCategoryModal = document.getElementById('editCategoryModal');
        if (editCategoryModal) {
            editCategoryModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                
                // Extract category information from button data attributes
                const categoryId = button.getAttribute('data-category-id');
                const categoryName = button.getAttribute('data-category-name');
                const categoryDescription = button.getAttribute('data-category-description');
                const categoryParent = button.getAttribute('data-category-parent');
                
                // Update the modal's content
                document.getElementById('edit_category_id').value = categoryId;
                document.getElementById('edit_category_name').value = categoryName;
                document.getElementById('edit_category_description').value = categoryDescription;
                
                // Set the parent category dropdown
                const parentDropdown = document.getElementById('edit_parent_id');
                if (categoryParent) {
                    parentDropdown.value = categoryParent;
                } else {
                    parentDropdown.value = '';
                }
                
                // Disable the category's own entry in the parent dropdown to prevent circular references
                for (let i = 0; i < parentDropdown.options.length; i++) {
                    const option = parentDropdown.options[i];
                    if (option.value === categoryId) {
                        option.disabled = true;
                    } else {
                        option.disabled = false;
                    }
                }
            });
        }
        
        // Handle delete category modal
        const deleteCategoryModal = document.getElementById('deleteCategoryModal');
        if (deleteCategoryModal) {
            deleteCategoryModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                
                // Extract category information from button data attributes
                const categoryId = button.getAttribute('data-category-id');
                const categoryName = button.getAttribute('data-category-name');
                const resourceCount = parseInt(button.getAttribute('data-resource-count'), 10);
                const childCount = parseInt(button.getAttribute('data-child-count'), 10);
                
                // Update the modal's content
                document.getElementById('delete_category_id').value = categoryId;
                document.getElementById('delete_category_name').textContent = categoryName;
                
                // Show warning and disable delete button if category has resources or children
                const warningElement = document.getElementById('delete_warning');
                const deleteButton = document.getElementById('confirm_delete_btn');
                
                if (resourceCount > 0 || childCount > 0) {
                    let warningText = 'This category cannot be deleted because it ';
                    
                    if (resourceCount > 0 && childCount > 0) {
                        warningText += `has ${resourceCount} resources and ${childCount} subcategories.`;
                    } else if (resourceCount > 0) {
                        warningText += `has ${resourceCount} resources.`;
                    } else {
                        warningText += `has ${childCount} subcategories.`;
                    }
                    
                    warningText += ' Please move or delete these first.';
                    
                    warningElement.textContent = warningText;
                    warningElement.style.display = 'block';
                    deleteButton.disabled = true;
                } else {
                    warningElement.style.display = 'none';
                    deleteButton.disabled = false;
                }
            });
        }
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?> 