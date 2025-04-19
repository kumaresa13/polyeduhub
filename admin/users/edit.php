<?php
// File path: admin/users/edit.php

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

// Get user ID from query string
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$user_id) {
    $_SESSION['error_message'] = "Invalid user ID";
    header("Location: index.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    // Get form data
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $department = trim($_POST['department']);
    $student_id = trim($_POST['student_id'] ?? '');
    $role = $_POST['role'];
    $status = $_POST['status'];
    
    // Validate required fields
    $errors = [];
    
    if (empty($first_name)) {
        $errors[] = "First name is required";
    }
    
    if (empty($last_name)) {
        $errors[] = "Last name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email is not valid";
    }
    
    // Check if email is already in use by another user
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            $errors[] = "Email is already in use by another user";
        }
    } catch (PDOException $e) {
        error_log("Error checking email: " . $e->getMessage());
        $errors[] = "An error occurred while checking email";
    }
    
    // If no errors, update user
    if (empty($errors)) {
        try {
            // Handle password update if provided
            $password_sql = "";
            $params = [$first_name, $last_name, $email, $role, $status, $department, $student_id];
            
            if (!empty($_POST['password'])) {
                $password = $_POST['password'];
                $password_sql = ", password = ?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
            
            // Update user in database
            $stmt = $pdo->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, email = ?, role = ?, status = ?, 
                    department = ?, student_id = ? $password_sql
                WHERE id = ?
            ");
            
            $params[] = $user_id;
            $stmt->execute($params);
            
            // Log admin action
            logAdminAction($admin_id, "Updated user", "Updated user ID: $user_id");
            
            $_SESSION['success_message'] = "User updated successfully";
            header("Location: view.php?id=$user_id");
            exit();
        } catch (PDOException $e) {
            error_log("Error updating user: " . $e->getMessage());
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch user data
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error_message'] = "User not found";
        header("Location: index.php");
        exit();
    }
    
} catch (PDOException $e) {
    error_log("Error fetching user: " . $e->getMessage());
    $_SESSION['error_message'] = "Error fetching user data";
    header("Location: index.php");
    exit();
}

// Set page title and nested path variable
$page_title = "Edit User";
$nested = true;

// Include header
include_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Edit User</h1>
        <a href="view.php?id=<?= $user_id ?>" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back to User Profile
        </a>
    </div>
    
    <!-- Display errors if any -->
    <?php if (isset($errors) && !empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h5 class="alert-heading">Please fix the following errors:</h5>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= $error ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Edit User Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
            <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">User Information</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="student_id" class="form-label">Student ID</label>
                                <input type="text" class="form-control" id="student_id" name="student_id" value="<?= htmlspecialchars($user['student_id'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="department" class="form-label">Department</label>
                                <input type="text" class="form-control" id="department" name="department" value="<?= htmlspecialchars($user['department'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="student" <?= $user['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                                    <option value="teacher" <?= $user['role'] === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password">
                                <div class="form-text">Leave blank to keep current password.</div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="view.php?id=<?= $user_id ?>" class="btn btn-secondary me-md-2">Cancel</a>
                            <button type="submit" name="update_user" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Account Information</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">User ID</label>
                        <p class="form-control-static"><?= $user['id'] ?></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Joined</label>
                        <p class="form-control-static"><?= date('F j, Y', strtotime($user['created_at'])) ?></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Last Login</label>
                        <p class="form-control-static">
                            <?= $user['last_login'] ? date('F j, Y, g:i a', strtotime($user['last_login'])) : 'Never' ?>
                        </p>
                    </div>
                    
                    <hr>
                    
                    <div class="alert alert-info mb-0">
                        <h6 class="alert-heading">Note:</h6>
                        <p class="mb-0">Changing a user's role may affect their access to certain features and content. Please ensure you select the appropriate role.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>