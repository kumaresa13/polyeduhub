<?php
/**
 * Admin User Management
 * Place this file in: polyeduhub/admin/users/index.php
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

// Handle user status updates
if (isset($_GET['id']) && isset($_GET['action'])) {
    $user_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    // Validate action
    if (in_array($action, ['activate', 'deactivate', 'suspend'])) {
        try {
            $pdo = getDbConnection();
            
            // Get the user
            $stmt = $pdo->prepare("SELECT first_name, last_name, status FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $_SESSION['error_message'] = "User not found.";
            } else {
                // Determine new status based on action
                $new_status = '';
                switch ($action) {
                    case 'activate':
                        $new_status = 'active';
                        break;
                    case 'deactivate':
                        $new_status = 'inactive';
                        break;
                    case 'suspend':
                        $new_status = 'suspended';
                        break;
                }
                
                // Don't update if status is already set
                if ($user['status'] === $new_status) {
                    $_SESSION['info_message'] = "User is already {$new_status}.";
                } else {
                    // Update user status
                    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                    $stmt->execute([$new_status, $user_id]);
                    
                    // Log the action
                    $user_name = $user['first_name'] . ' ' . $user['last_name'];
                    logAdminAction(
                        $admin_id, 
                        "User Status Updated", 
                        "Changed status of user {$user_name} (ID: {$user_id}) to {$new_status}."
                    );
                    
                    $_SESSION['success_message'] = "User status updated successfully to {$new_status}.";
                }
            }
        } catch (Exception $e) {
            error_log("Error updating user status: " . $e->getMessage());
            $_SESSION['error_message'] = "An error occurred while updating user status.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid action.";
    }
    
    // Redirect back to user list
    header("Location: index.php");
    exit();
}

// Get filter and search parameters
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query based on filters
try {
    $pdo = getDbConnection();
    
    $where_clauses = [];
    $params = [];
    
    if ($role_filter !== 'all') {
        $where_clauses[] = "role = ?";
        $params[] = $role_filter;
    }
    
    if ($status_filter !== 'all') {
        $where_clauses[] = "status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($search)) {
        $where_clauses[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR student_id LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_sql = empty($where_clauses) ? "" : "WHERE " . implode(' AND ', $where_clauses);
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) FROM users $where_sql";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_users = $stmt->fetchColumn();
    $total_pages = ceil($total_users / $per_page);
    
    // Get users for current page
    $sql = "
        SELECT id, first_name, last_name, email, role, department, student_id, 
               status, created_at, last_login
        FROM users
        $where_sql
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $params[] = $per_page;
    $params[] = $offset;
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    // Get user statistics
    $stats = [
        'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'students' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn(),
        'admins' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
        'active' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn(),
        'inactive' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'inactive'")->fetchColumn(),
        'suspended' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'suspended'")->fetchColumn()
    ];
    
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $users = [];
    $total_users = 0;
    $total_pages = 0;
    $stats = [
        'total_users' => 0,
        'students' => 0,
        'admins' =>

0,
        'active' => 0,
        'inactive' => 0,
        'suspended' => 0
    ];
    $_SESSION['error_message'] = "An error occurred while fetching users.";
}

// Set page title
$page_title = "User Management";

// Include header
include_once '../includes/header.php';
?>

<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">User Management</h1>
    <a href="add.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
        <i class="fas fa-user-plus fa-sm text-white-50"></i> Add New User
    </a>
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

<?php if (isset($_SESSION['info_message'])): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <?= $_SESSION['info_message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['info_message']); ?>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row mb-4">
    <!-- Total Users Card -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Users</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['total_users']) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Student Users Card -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Students</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['students']) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Admin Users Card -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Administrators</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['admins']) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-shield fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Active Users Card -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Active Users</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['active']) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-check fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter & Search Area -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary">Filter Users</h6>
    </div>
    <div class="card-body">
        <form action="index.php" method="GET" class="mb-0">
            <div class="row g-3 align-items-center">
                <div class="col-md-3 mb-3">
                    <label for="role" class="form-label">Role</label>
                    <select class="form-select" id="role" name="role">
                        <option value="all" <?= $role_filter === 'all' ? 'selected' : '' ?>>All Roles</option>
                        <option value="student" <?= $role_filter === 'student' ? 'selected' : '' ?>>Students</option>
                        <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Administrators</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Name, Email, Student ID..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            User List
            <?php if ($total_users > 0): ?>
                <span class="text-muted">(<?= number_format($total_users) ?> total)</span>
            <?php endif; ?>
        </h6>
    </div>
    <div class="card-body">
        <?php if (empty($users)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-users fa-4x mb-3"></i>
                <p>No users found matching your criteria.</p>
                <?php if (!empty($search) || $role_filter !== 'all' || $status_filter !== 'all'): ?>
                    <a href="index.php" class="btn btn-outline-primary">Reset Filters</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Student ID</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <span class="badge bg-info">Admin</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Student</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($user['department'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($user['student_id'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if ($user['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif ($user['status'] === 'inactive'): ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Suspended</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                <td><?= $user['last_login'] ? date('M d, Y', strtotime($user['last_login'])) : 'Never' ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="view.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-info" title="View User">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-primary" title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <?php if ($user['status'] !== 'active'): ?>
                                                <li><a class="dropdown-item" href="index.php?id=<?= $user['id'] ?>&action=activate">Activate</a></li>
                                            <?php endif; ?>
                                            
                                            <?php if ($user['status'] !== 'inactive'): ?>
                                                <li><a class="dropdown-item" href="index.php?id=<?= $user['id'] ?>&action=deactivate">Deactivate</a></li>
                                            <?php endif; ?>
                                            
                                            <?php if ($user['status'] !== 'suspended'): ?>
                                                <li><a class="dropdown-item" href="index.php?id=<?= $user['id'] ?>&action=suspend">Suspend</a></li>
                                            <?php endif; ?>
                                            
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item" href="reset-password.php?id=<?= $user['id'] ?>">Reset Password</a></li>
                                        </ul>
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
                                    <a class="page-link" href="index.php?page=<?= $page - 1 ?><?= $role_filter !== 'all' ? '&role=' . $role_filter : '' ?><?= $status_filter !== 'all' ? '&status=' . $status_filter : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link">&laquo;</span>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            // Calculate range of page numbers to display
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            // Display first page and ellipsis if necessary
                            if ($start_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="index.php?page=1' . ($role_filter !== 'all' ? '&role=' . $role_filter : '') . ($status_filter !== 'all' ? '&status=' . $status_filter : '') . (!empty($search) ? '&search=' . urlencode($search) : '') . '">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            // Display page numbers
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">
                                    <a class="page-link" href="index.php?page=' . $i . ($role_filter !== 'all' ? '&role=' . $role_filter : '') . ($status_filter !== 'all' ? '&status=' . $status_filter : '') . (!empty($search) ? '&search=' . urlencode($search) : '') . '">' . $i . '</a>
                                </li>';
                            }
                            
                            // Display last page and ellipsis if necessary
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="index.php?page=' . $total_pages . ($role_filter !== 'all' ? '&role=' . $role_filter : '') . ($status_filter !== 'all' ? '&status=' . $status_filter : '') . (!empty($search) ? '&search=' . urlencode($search) : '') . '">' . $total_pages . '</a></li>';
                            }
                            ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="index.php?page=<?= $page + 1 ?><?= $role_filter !== 'all' ? '&role=' . $role_filter : '' ?><?= $status_filter !== 'all' ? '&status=' . $status_filter : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link">&raquo;</span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>