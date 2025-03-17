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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $name = filter_var($_POST['room_name'], FILTER_SANITIZE_STRING);
    $description = filter_var($_POST['room_description'], FILTER_SANITIZE_STRING);
    $type = filter_var($_POST['room_type'], FILTER_SANITIZE_STRING);
    
    // Validate inputs
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Room name is required";
    } elseif (strlen($name) > 100) {
        $errors[] = "Room name must be less than 100 characters";
    }
    
    if (strlen($description) > 500) {
        $errors[] = "Description must be less than 500 characters";
    }
    
    if (!in_array($type, ['public', 'private', 'group'])) {
        $errors[] = "Invalid room type";
    }
    
    // If no errors, create the chat room
    if (empty($errors)) {
        $pdo = getDbConnection();
        
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert chat room
            $stmt = $pdo->prepare("
                INSERT INTO chat_rooms (name, description, type, created_by, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $description, $type, $user_id]);
            $room_id = $pdo->lastInsertId();
            
            // Add creator as a member
            $stmt = $pdo->prepare("
                INSERT INTO chat_room_members (room_id, user_id, joined_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$room_id, $user_id]);
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect to the new chat room
            header("Location: room.php?id=" . $room_id);
            exit();
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Page title
$page_title = "Create Chat Room";
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
                <a class="nav-link" href="../resources/index.php">
                    <i class="fas fa-fw fa-folder"></i>
                    <span>Browse Resources</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="../resources/upload.php">
                    <i class="fas fa-fw fa-file-upload"></i>
                    <span>Upload Resource</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="../resources/my-resources.php">
                    <i class="fas fa-fw fa-list"></i>
                    <span>My Resources</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="../resources/favorites.php">
                    <i class="fas fa-fw fa-star"></i>
                    <span>Favorites</span>
                </a>
            </li>
            
            <hr class="sidebar-divider">
            
            <div class="sidebar-heading">
                Community
            </div>
            
            <li class="nav-item">
                <a class="nav-link active" href="index.php">
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
                
                <a href="index.php" class="btn btn-link text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i> Back to Rooms
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
                <h1 class="h3 mb-0 text-gray-800">Create New Chat Room</h1>
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
            
            <!-- Create Chat Room Form -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Room Details</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="room_name" class="form-label">Room Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="room_name" name="room_name" required maxlength="100" placeholder="Enter a name for your chat room" value="<?= isset($_POST['room_name']) ? htmlspecialchars($_POST['room_name']) : '' ?>">
                                    <div class="form-text">Choose a descriptive name that helps others understand the purpose of the room.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="room_description" class="form-label">Description</label>
                                    <textarea class="form-control" id="room_description" name="room_description" rows="3" maxlength="500" placeholder="Provide a brief description of what this room is for"><?= isset($_POST['room_description']) ? htmlspecialchars($_POST['room_description']) : '' ?></textarea>
                                    <div class="form-text">Add details about topics to discuss, rules, or who should join.</div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">Room Type <span class="text-danger">*</span></label>
                                    <div class="row">
                                        <div class="col-md-4 mb-2">
                                            <div class="card h-100">
                                                <div class="card-body">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="room_type" id="type_public" value="public" <?= (!isset($_POST['room_type']) || $_POST['room_type'] === 'public') ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="type_public">
                                                            <div class="mb-2">
                                                                <span class="badge bg-primary">Public</span>
                                                            </div>
                                                            <p class="small text-muted mb-0">Anyone can see and join this room.</p>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <div class="card h-100">
                                                <div class="card-body">
                                                    <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="room_type" id="type_private" value="private" <?= isset($_POST['room_type']) && $_POST['room_type'] === 'private' ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="type_private">
                                                            <div class="mb-2">
                                                                <span class="badge bg-danger">Private</span>
                                                            </div>
                                                            <p class="small text-muted mb-0">Only invited members can join this room.</p>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <div class="card h-100">
                                                <div class="card-body">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="room_type" id="type_group" value="group" <?= isset($_POST['room_type']) && $_POST['room_type'] === 'group' ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="type_group">
                                                            <div class="mb-2">
                                                                <span class="badge bg-success">Group</span>
                                                            </div>
                                                            <p class="small text-muted mb-0">Selected department/class members can join.</p>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="index.php" class="btn btn-secondary me-md-2">Cancel</a>
                                    <button type="submit" class="btn btn-primary">Create Room</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Chat Room Tips</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <h6><i class="fas fa-lightbulb text-warning me-2"></i> Room Name Ideas</h6>
                                <ul class="small text-muted">
                                    <li>Course Specific: "Database Systems 101 Discussion"</li>
                                    <li>Project Based: "Final Year Project Collaboration"</li>
                                    <li>Study Group: "Java Programming Study Group"</li>
                                </ul>
                            </div>
                            <div class="mb-3">
                                <h6><i class="fas fa-shield-alt text-info me-2"></i> Privacy Considerations</h6>
                                <p class="small text-muted">Public rooms are visible to all students. Choose Private or Group rooms for more sensitive discussions.</p>
                            </div>
                            <div>
                                <h6><i class="fas fa-users text-success me-2"></i> Building Community</h6>
                                <p class="small text-muted">Active chat rooms help build a stronger learning community. Consider setting clear room guidelines in your description.</p>
                            </div>
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