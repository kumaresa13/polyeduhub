<?php
// File path: admin/users/permissions.php

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

// Handle role updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_roles'])) {
    $user_id = intval($_POST['user_id']);
    $new_role = $_POST['role'];
    
    // Validate inputs
    if (!$user_id || !in_array($new_role, ['student', 'teacher', 'admin'])) {
        $_SESSION['error_message'] = "Invalid user ID or role";
    } else {
        try {
            $pdo = getDbConnection();
            
            // Check if user exists
            $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception("User not found");
            }
            
            // Don't allow changing own role
            if ($user_id == $admin_id) {
                throw new Exception("You cannot change your own role");
            }
            
            // Update user role
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$new_role, $user_id]);
            
            // Log admin action
            logAdminAction(
                $admin_id, 
                "Updated user role", 
                "Changed user ID: $user_id role from {$user['role']} to $new_role"
            );
            
            $_SESSION['success_message'] = "User role updated successfully";
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error: " . $e->getMessage();
        }
    }
    
    // Redirect to refresh page
    header("Location: permissions.php");
    exit();
}

// Fetch users with roles
try {
    $pdo = getDbConnection();
    
    // Get student count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'student'");
    $stmt->execute();
    $student_count = $stmt->fetchColumn();
    
    // Get teacher count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'teacher'");
    $stmt->execute();
    $teacher_count = $stmt->fetchColumn();
    
    // Get admin count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $stmt->execute();
    $admin_count = $stmt->fetchColumn();
    
    // Get administrators
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, email, created_at, last_login
        FROM users 
        WHERE role = 'admin'
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $admins = $stmt->fetchAll();
    
    // Get teachers
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, email, department, created_at
        FROM users 
        WHERE role = 'teacher'
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $teachers = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching user roles: " . $e->getMessage());
    $student_count = 0;
    $teacher_count = 0;
    $admin_count = 0;
    $admins = [];
    $teachers = [];
}

// Set page title and nested path variable
$page_title = "User Permissions";
$nested = true;

// Include header
include_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">User Permissions</h1>
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
    
    <!-- Role Distribution -->
    <div class="row mb-4">
        <div class="col-xl-4 col-md-4 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Students</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($student_count) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-4 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Teachers</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($teacher_count) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chalkboard-teacher fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-4 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Administrators</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($admin_count) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-shield fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Role Management -->
    <div class="row">
        <!-- Administrator List -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Administrators</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($admins)): ?>
                        <div class="text-center py-4">
                            <p>No administrators found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Last Login</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($admins as $admin): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?></td>
                                            <td><?= htmlspecialchars($admin['email']) ?></td>
                                            <td><?= $admin['last_login'] ? date('M j, Y', strtotime($admin['last_login'])) : 'Never' ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="view.php?id=<?= $admin['id'] ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($admin['id'] != $admin_id): ?>
                                                        <button type="button" class="btn btn-sm btn-warning" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#changeRoleModal"
                                                                data-id="<?= $admin['id'] ?>"
                                                                data-name="<?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?>"
                                                                data-current-role="admin">
                                                            <i class="fas fa-user-cog"></i>
                                                        </button>
                                                    <?php endif; ?>
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
        </div>
        
        <!-- Teacher List -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Teachers</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($teachers)): ?>
                        <div class="text-center py-4">
                            <p>No teachers found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Department</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?></td>
                                            <td><?= htmlspecialchars($teacher['email']) ?></td>
                                            <td><?= htmlspecialchars($teacher['department'] ?: 'N/A') ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="view.php?id=<?= $teacher['id'] ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-warning" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#changeRoleModal"
                                                            data-id="<?= $teacher['id'] ?>"
                                                            data-name="<?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?>"
                                                            data-current-role="teacher">
                                                        <i class="fas fa-user-cog"></i>
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
        </div>
    </div>
    
    <!-- Assign Admin/Teacher -->
    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Assign New Role</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="index.php" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Find User</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" placeholder="Search by name or email">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <p class="mb-0 text-muted">Search for a user, then click on the User Settings icon <i class="fas fa-user-cog"></i> to change their role.</p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Role Information -->
    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Role Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-user-graduate text-primary me-2"></i> Student
                                    </h5>
                                    <ul class="list-unstyled">
                                        <li>- Can upload and download resources</li>
                                        <li>- Can join chat rooms</li>
                                        <li>- Can earn points and badges</li>
                                        <li>- Can rate and comment on resources</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-chalkboard-teacher text-info me-2"></i> Teacher
                                    </h5>
                                    <ul class="list-unstyled">
                                        <li>- All Student permissions</li>
                                        <li>- Can create subject-specific chat rooms</li>
                                        <li>- Can mark resources as recommended</li>
                                        <li>- Resources auto-approved</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-user-shield text-warning me-2"></i> Administrator
                                    </h5>
                                    <ul class="list-unstyled">
                                        <li>- Full system access</li>
                                        <li>- Can approve/reject resources</li>
                                        <li>- Can manage users and roles</li>
                                        <li>- Can modify system settings</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Change Role Modal -->
<div class="modal fade" id="changeRoleModal" tabindex="-1" aria-labelledby="changeRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeRoleModalLabel">Change User Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="modal_user_id">
                    <p>Change role for user: <strong id="modal_user_name"></strong></p>
                    <p>Current role: <span class="badge" id="modal_current_role"></span></p>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">New Role</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> 
                        Changing a user's role will affect their access permissions on the platform.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_roles" class="btn btn-primary">Update Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Set up change role modal
    document.addEventListener('DOMContentLoaded', function() {
        const roleModal = document.getElementById('changeRoleModal');
        if (roleModal) {
            roleModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const userId = button.getAttribute('data-id');
                const userName = button.getAttribute('data-name');
                const currentRole = button.getAttribute('data-current-role');
                
                document.getElementById('modal_user_id').value = userId;
                document.getElementById('modal_user_name').textContent = userName;
                
                const roleSpan = document.getElementById('modal_current_role');
                roleSpan.textContent = currentRole.charAt(0).toUpperCase() + currentRole.slice(1);
                
                if (currentRole === 'admin') {
                    roleSpan.classList.add('bg-warning');
                    roleSpan.classList.remove('bg-info', 'bg-primary');
                } else if (currentRole === 'teacher') {
                    roleSpan.classList.add('bg-info');
                    roleSpan.classList.remove('bg-warning', 'bg-primary');
                } else {
                    roleSpan.classList.add('bg-primary');
                    roleSpan.classList.remove('bg-warning', 'bg-info');
                }
                
                // Pre-select current role in dropdown
                const selectElement = document.getElementById('role');
                for (let i = 0; i < selectElement.options.length; i++) {
                    if (selectElement.options[i].value === currentRole) {
                        selectElement.options[i].selected = true;
                        break;
                    }
                }
            });
        }
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>