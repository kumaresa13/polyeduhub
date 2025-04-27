<?php
// File path: admin/users/index.php

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

// Handle user activation/deactivation
if (isset($_POST['toggle_status'])) {
    $user_id = intval($_POST['user_id']);
    $new_status = $_POST['new_status'] === 'active' ? 'active' : 'inactive';
    
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $user_id]);
        
        // Log admin action
        logAdminAction($admin_id, "Updated user status", "Changed user ID: $user_id status to $new_status");
        
        $_SESSION['success_message'] = "User status updated successfully";
    } catch (PDOException $e) {
        error_log("Error updating user status: " . $e->getMessage());
        $_SESSION['error_message'] = "Error updating user status";
    }
    
    // Redirect to refresh page
    header("Location: index.php");
    exit();
}

// Get filter parameters
$role = isset($_GET['role']) ? $_GET['role'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$department = isset($_GET['department']) ? $_GET['department'] : '';

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Fetch users with filters
try {
    $pdo = getDbConnection();
    
    // Build WHERE clause for filters
    $where_clauses = [];
    $params = [];
    
    if (!empty($role)) {
        $where_clauses[] = "role = ?";
        $params[] = $role;
    }
    
    if (!empty($status)) {
        $where_clauses[] = "status = ?";
        $params[] = $status;
    }
    
    if (!empty($department)) {
        $where_clauses[] = "department = ?";
        $params[] = $department;
    }
    
    if (!empty($search)) {
        $where_clauses[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR student_id LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    // Count total users with filters
    $count_sql = "SELECT COUNT(*) FROM users $where_sql";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_users = $stmt->fetchColumn();
    $total_pages = ceil($total_users / $limit);
    
    // Get users with pagination
    $sql = "
        SELECT id, first_name, last_name, email, role, status, department, 
               created_at, last_login, student_id
        FROM users
        $where_sql
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $all_params = array_merge($params, [$limit, $offset]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($all_params);
    $users = $stmt->fetchAll();
    
    // Get departments for filter
    $stmt = $pdo->prepare("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department");
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get user counts by role
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN role = 'student' THEN 1 END) as students,
            COUNT(CASE WHEN role = 'teacher' THEN 1 END) as teachers,
            COUNT(CASE WHEN role = 'admin' THEN 1 END) as admins,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
            COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive
        FROM users
    ");
    $stmt->execute();
    $user_counts = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $users = [];
    $departments = [];
    $total_users = 0;
    $total_pages = 0;
    $user_counts = [
        'total' => 0,
        'students' => 0,
        'teachers' => 0,
        'admins' => 0,
        'active' => 0,
        'inactive' => 0
    ];
}

// Set page title and nested path variable
$page_title = "Manage Users";
$nested = true;

// Include header
include_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">User Management</h1>
        <a href="create.php" class="btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> Add New User
        </a>
    </div>
    
    <!-- Success/Error Messages -->
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
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Users</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($user_counts['total']) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Students</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($user_counts['students']) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Teachers</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($user_counts['teachers']) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chalkboard-teacher fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Administrators</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($user_counts['admins']) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-shield fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Active Users</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($user_counts['active']) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Inactive Users</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($user_counts['inactive']) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-times fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Users</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="role" class="form-label">Role</label>
                    <select class="form-select" id="role" name="role">
                        <option value="">All Roles</option>
                        <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>Students</option>
                        <option value="teacher" <?= $role === 'teacher' ? 'selected' : '' ?>>Teachers</option>
                        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrators</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="department" class="form-label">Department</label>
                    <select class="form-select" id="department" name="department">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= htmlspecialchars($dept) ?>" <?= $department === $dept ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                          placeholder="Name, Email, ID..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Users Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Users</h6>
            <span>Showing <?= min($offset + 1, $total_users) ?>-<?= min($offset + $limit, $total_users) ?> of <?= $total_users ?> users</span>
        </div>
        <div class="card-body">
            <?php if (empty($users)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-users fa-4x text-gray-300 mb-3"></i>
                    <p>No users found matching your criteria. Try adjusting your filters.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Joined</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                        <?php if (!empty($user['student_id'])): ?>
                                            <div class="small text-muted">ID: <?= htmlspecialchars($user['student_id']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <span class="badge bg-warning">Administrator</span>
                                        <?php elseif ($user['role'] === 'teacher'): ?>
                                            <span class="badge bg-info">Teacher</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">Student</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($user['department'] ?: 'N/A') ?></td>
                                    <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <?php if ($user['status'] === 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($user['id'] != $admin_id): ?>
                                                <?php if ($user['status'] === 'active'): ?>
                                                    <button type="button" class="btn btn-sm btn-warning" 
                                                            data-bs-toggle="modal" data-bs-target="#statusModal" 
                                                            data-userid="<?= $user['id'] ?>" 
                                                            data-username="<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>"
                                                            data-action="deactivate">
                                                        <i class="fas fa-user-times"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-success" 
                                                            data-bs-toggle="modal" data-bs-target="#statusModal" 
                                                            data-userid="<?= $user['id'] ?>" 
                                                            data-username="<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>"
                                                            data-action="activate">
                                                        <i class="fas fa-user-check"></i>
                                                    </button>
                                                <?php endif; ?>
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
                                        <a class="page-link" href="?page=<?= $page - 1 ?>&role=<?= urlencode($role) ?>&status=<?= urlencode($status) ?>&department=<?= urlencode($department) ?>&search=<?= urlencode($search) ?>">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&role=<?= urlencode($role) ?>&status=<?= urlencode($status) ?>&department=<?= urlencode($department) ?>&search=<?= urlencode($search) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>&role=<?= urlencode($role) ?>&status=<?= urlencode($status) ?>&department=<?= urlencode($department) ?>&search=<?= urlencode($search) ?>">
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

<!-- Status Toggle Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="statusModalLabel">Change User Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="modal_user_id">
                    <input type="hidden" name="new_status" id="modal_new_status">
                    <p id="modal_confirmation_message"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="toggle_status" class="btn" id="modal_action_button">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Set up status modal with dynamic content
    document.addEventListener('DOMContentLoaded', function() {
        const statusModal = document.getElementById('statusModal');
        if (statusModal) {
            statusModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const userId = button.getAttribute('data-userid');
                const userName = button.getAttribute('data-username');
                const action = button.getAttribute('data-action');
                
                document.getElementById('modal_user_id').value = userId;
                
                if (action === 'deactivate') {
                    document.getElementById('modal_new_status').value = 'inactive';
                    document.getElementById('modal_confirmation_message').textContent = 
                        `Are you sure you want to deactivate user "${userName}"? They will no longer be able to log in.`;
                    
                    const actionButton = document.getElementById('modal_action_button');
                    actionButton.classList.remove('btn-success');
                    actionButton.classList.add('btn-warning');
                    actionButton.textContent = 'Deactivate';
                } else {
                    document.getElementById('modal_new_status').value = 'active';
                    document.getElementById('modal_confirmation_message').textContent = 
                        `Are you sure you want to activate user "${userName}"? They will be able to log in again.`;
                    
                    const actionButton = document.getElementById('modal_action_button');
                    actionButton.classList.remove('btn-warning');
                    actionButton.classList.add('btn-success');
                    actionButton.textContent = 'Activate';
                }
            });
        }
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>