<?php
// Start session and include necessary files
session_start();

// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db-connection.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../../login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';

// Page title
$page_title = "Upload Resource";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process upload logic here
    // This is just a placeholder - you'll need to implement the actual upload logic
}

// Get categories for dropdown
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT id, name FROM resource_categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - PolyEduHub</title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="../../assets/img/favicon.png" type="image/x-icon">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            display: flex;
            min-height: 100vh;
            background-color: #f8f9fc;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        /* Sidebar styles */
        .sidebar {
            width: 250px;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 100;
            padding-top: 20px;
            background-color: #4e73df;
            color: white;
            overflow-y: auto;
        }
        
        .sidebar-category {
            font-size: 0.8rem;
            text-transform: uppercase;
            padding: 10px 15px 5px;
            color: rgba(255, 255, 255, 0.6);
        }
        
        .sidebar ul {
            padding-left: 0;
            list-style: none;
        }
        
        .sidebar a {
            display: block;
            padding: 10px 15px;
            color: white !important;
            text-decoration: none;
            transition: 0.3s;
            border-radius: 5px;
            margin: 2px 10px;
        }
        
        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar a.active {
            background-color: rgba(255, 255, 255, 0.2);
            font-weight: 600;
        }
        
        .sidebar i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        /* Main content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .content {
            flex: 1;
        }
        
        /* Card styles */
        .card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 30px;
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 15px 20px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Form styles */
        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        /* Footer */
        footer {
            background-color: white;
            padding: 15px 0;
            text-align: center;
            border-top: 1px solid #e3e6f0;
        }
        
        /* Navbar */
        .navbar {
            margin-bottom: 24px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .toggle-sidebar {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="text-center mb-4">
            <i class="fas fa-graduation-cap fa-3x mb-2"></i>
            <h4>PolyEduHub</h4>
        </div>
        
        <div class="sidebar-category">NAVIGATION</div>
        <ul>
            <li><a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        </ul>
        
        <div class="sidebar-category">RESOURCES</div>
        <ul>
            <li><a href="index.php"><i class="fas fa-folder"></i> Browse Resources</a></li>
            <li><a href="upload.php" class="active"><i class="fas fa-upload"></i> Upload Resource</a></li>
            <li><a href="my-resources.php"><i class="fas fa-list"></i> My Resources</a></li>
            <li><a href="favorites.php"><i class="fas fa-star"></i> Favorites</a></li>
        </ul>
        
        <div class="sidebar-category">COMMUNITY</div>
        <ul>
            <li><a href="../chat/index.php"><i class="fas fa-comments"></i> Chat Rooms</a></li>
            <li><a href="../leaderboard/index.php"><i class="fas fa-trophy"></i> Leaderboard</a></li>
        </ul>
        
        <div class="sidebar-category">ACCOUNT</div>
        <ul>
            <li><a href="../profile/index.php"><i class="fas fa-user"></i> My Profile</a></li>
            <li><a href="../profile/badges.php"><i class="fas fa-award"></i> My Badges</a></li>
            <li><a href="../notifications/index.php"><i class="fas fa-bell"></i> Notifications</a></li>
            <li><a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white mb-4">
            <div class="container-fluid">
                <button class="navbar-toggler toggle-sidebar" type="button" id="sidebarToggle">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="d-flex align-items-center">
                    <h1 class="h3 mb-0 text-gray-800">Upload Resource</h1>
                </div>
                
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="d-none d-lg-inline me-2"><?= htmlspecialchars($first_name . ' ' . $last_name) ?></span>
                            <img src="../../assets/img/ui/default-profile.png" class="rounded-circle" width="32" height="32" alt="Profile">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="../profile/index.php"><i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="../profile/edit.php"><i class="fas fa-cogs fa-sm fa-fw me-2 text-gray-400"></i> Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../logout.php"><i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
        
        <!-- Content -->
        <div class="content">
            <div class="container-fluid">
                <!-- Upload Form Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="m-0 font-weight-bold">Upload New Resource</h5>
                    </div>
                    <div class="card-body">
                        <form action="upload.php" method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="title" class="form-label">Resource Title</label>
                                <input type="text" class="form-control" id="title" name="title" required placeholder="Enter resource title (e.g., Database Normalization Notes)">
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4" placeholder="Provide a brief description of the resource"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="tags" class="form-label">Tags (comma-separated)</label>
                                <input type="text" class="form-control" id="tags" name="tags" placeholder="Enter tags (e.g., database, sql, normalization)">
                            </div>
                            
                            <div class="mb-4">
                                <label for="file" class="form-label">Upload File</label>
                                <input type="file" class="form-control" id="file" name="file" required>
                                <div class="form-text">Allowed types: pdf, doc, docx, ppt, pptx, xls, xlsx, txt, zip, rar, jpg, jpeg, png (Max size: 10MB)</div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Upload Resource</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Upload Guidelines Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="m-0 font-weight-bold">Upload Guidelines</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-check-circle text-success me-2"></i> Quality Standards</h6>
                                <ul>
                                    <li>Use clear, descriptive titles</li>
                                    <li>Provide a thorough description</li>
                                    <li>Choose the most appropriate category</li>
                                    <li>Add relevant tags for better discoverability</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-exclamation-triangle text-warning me-2"></i> Restrictions</h6>
                                <ul>
                                    <li>Do not upload copyrighted materials without permission</li>
                                    <li>Respect academic integrity guidelines</li>
                                    <li>Files must be under 10MB in size</li>
                                    <li>All uploads will be reviewed by administrators</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <footer>
            <div class="container">
                <span>&copy; <?= date('Y') ?> PolyEduHub. All rights reserved.</span>
            </div>
        </footer>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle sidebar on mobile
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            if(sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    document.querySelector('.sidebar').classList.toggle('show');
                });
            }
        });
    </script>
</body>
</html>