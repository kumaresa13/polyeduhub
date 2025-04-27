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

// Get user profile data
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // User not found
        $_SESSION['error_message'] = "User data could not be retrieved.";
        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Error in profile edit page: " . $e->getMessage());
    $_SESSION['error_message'] = "An error occurred while retrieving user data.";
    header("Location: index.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $year_of_study = intval($_POST['year_of_study'] ?? 0);
    $bio = trim($_POST['bio'] ?? '');
    
    // Validate inputs
    $errors = [];
    
    if (empty($first_name)) {
        $errors[] = "First name is required";
    }
    
    if (empty($last_name)) {
        $errors[] = "Last name is required";
    }
    
    // Handle profile image upload
    $profile_image = $user['profile_image']; // Default to current image
    
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_image'];
        $fileName = $file['name'];
        $fileTmpPath = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Validate file type
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = "Invalid image file. Allowed formats: JPG, JPEG, PNG, GIF";
        }
        
        // Validate file size (max 2MB)
        if ($fileSize > 2 * 1024 * 1024) {
            $errors[] = "Profile image must be less than 2MB";
        }
        
        if (empty($errors)) {
            // Generate unique filename
            $newFileName = 'profile_' . $user_id . '_' . uniqid() . '.' . $fileType;
            $uploadPath = '../../uploads/profile/' . $newFileName;
            
            // Ensure directory exists
            $uploadDir = '../../uploads/profile/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Move uploaded file
            if (move_uploaded_file($fileTmpPath, $uploadPath)) {
                // Delete old profile image if it exists
                if ($profile_image && file_exists('../../' . $profile_image)) {
                    unlink('../../' . $profile_image);
                }
                
                $profile_image = 'uploads/profile/' . $newFileName;
            } else {
                $errors[] = "Failed to upload profile image";
            }
        }
    }
    
    // If no errors, update profile
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, department = ?, 
                    student_id = ?, year_of_study = ?, bio = ?, 
                    profile_image = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $first_name, $last_name, $department, 
                $student_id, $year_of_study, $bio, 
                $profile_image, $user_id
            ]);
            
            // Update session data
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['department'] = $department;
            $_SESSION['profile_image'] = $profile_image;
            
            $_SESSION['success_message'] = "Profile updated successfully";
            header("Location: index.php");
            exit();
        } catch (PDOException $e) {
            error_log("Error updating profile: " . $e->getMessage());
            $errors[] = "An error occurred while updating your profile";
        }
    }
}

// Page title
$page_title = "Edit Profile";
$nested = true;

// Include header
include_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Edit Profile</h1>
        <a href="index.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back to Profile
        </a>
    </div>
    
    <!-- Display Errors -->
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
    
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Edit Your Profile</h6>
                </div>
                <div class="card-body">
                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?= htmlspecialchars($user['first_name']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?= htmlspecialchars($user['last_name']) ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                            <div class="form-text">Email address cannot be changed.</div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3">
                                <label for="department" class="form-label">Department</label>
                                <input type="text" class="form-control" id="department" name="department" 
                                       value="<?= htmlspecialchars($user['department'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="student_id" class="form-label">Student ID</label>
                                <input type="text" class="form-control" id="student_id" name="student_id" 
                                       value="<?= htmlspecialchars($user['student_id'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="year_of_study" class="form-label">Semester of Study</label>
                            <select class="form-select" id="year_of_study" name="year_of_study">
                                <option value="0">-- Select Semester --</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>" <?= ($user['year_of_study'] == $i) ? 'selected' : '' ?>>
                                    Semester <?= $i ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bio" class="form-label">Bio</label>
                            <textarea class="form-control" id="bio" name="bio" rows="4"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                            <div class="form-text">Tell others about yourself or your interests (optional).</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="profile_image" class="form-label">Profile Image</label>
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <div class="profile-image-preview mb-3 position-relative">
                                        <img src="<?= $user['profile_image'] ? '../../' . $user['profile_image'] : '../../assets/img/ui/default-profile.png' ?>" 
                                             alt="Profile Image" class="rounded-circle" width="100" height="100" id="imagePreview">
                                    </div>
                                </div>
                                <div class="col">
                                    <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*">
                                    <div class="form-text">Max file size: 2MB. Supported formats: JPG, JPEG, PNG, GIF.</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <a href="index.php" class="btn btn-secondary me-2">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
            
            

<script>
    // Preview uploaded image
    document.getElementById('profile_image').addEventListener('change', function(e) {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('imagePreview').src = e.target.result;
            }
            reader.readAsDataURL(file);
        }
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>