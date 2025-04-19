<?php
// Place this file in: polyeduhub/student/includes/header.php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check student authentication
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: " . (isset($nested) ? '../../' : '../') . "login.php");
    exit();
}

// Determine base path for assets and links based on whether we're in a nested directory
$base_path = isset($nested) ? '../../' : '../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Student Dashboard' ?> - <?= APP_NAME ?></title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="<?= $base_path ?>assets/img/favicon.png" type="image/x-icon">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Student CSS -->
    <link rel="stylesheet" href="<?= $base_path ?>assets/css/student.css">
    
    <style>
        body {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 250px;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            z-index: 1000;
            background-color: #4e73df !important;
        }
        
        .sidebar-heading {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.8rem;
            padding-left: 10px;
            margin-top: 1rem;
            text-transform: uppercase;
        }
        
        .nav-link {
            padding: 0.75rem 1rem;
            color: rgba(255, 255, 255, 0.8) !important;
        }
        
        .nav-link:hover {
            color: #fff !important;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .nav-link.active {
            color: #fff !important;
            font-weight: bold;
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .main-content {
            flex: 1;
            margin-left: 250px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .content {
            flex: 1;
            padding: 1.5rem;
        }
        
        .search-bar {
            max-width: 300px;
        }
        
        .resource-thumbnail {
            height: 160px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fc;
        }
        
        .resource-thumbnail img {
            max-height: 100%;
            object-fit: cover;
        }
        
        .resource-thumbnail .resource-icon {
            font-size: 4rem;
            color: #4e73df;
        }
        
        .resource-card {
            transition: transform 0.2s;
            height: 100%;
        }
        
        .resource-card:hover {
            transform: translateY(-5px);
        }
        
        .favorite-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.8);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .favorite-btn:hover {
            background-color: rgba(255, 255, 255, 1);
        }
        
        .favorite-btn.active {
            color: #e74a3b;
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
        }
    </style>
    
    <!-- Additional Page-Specific Styles -->
    <?= isset($additional_styles) ? $additional_styles : '' ?>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-heading text-center py-4">
            <i class="fas fa-graduation-cap fs-2 mb-2"></i>
            <h4 class="mb-0">PolyEduHub</h4>
        </div>
        
        <div class="sidebar-heading">
            Navigation
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>" 
                   href="<?= isset($nested) ? '../' : '' ?>dashboard.php">
                    <i class="fas fa-fw fa-tachometer-alt me-2"></i> Dashboard
                </a>
            </li>
        </ul>
        
        <div class="sidebar-heading">
            Resources
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' && dirname($_SERVER['PHP_SELF']) == '/polyeduhub/student/resources' ? 'active' : '' ?>" 
                   href="<?= isset($nested) ? '' : 'resources/' ?>index.php">
                    <i class="fas fa-fw fa-folder me-2"></i> Browse Resources
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'upload.php' ? 'active' : '' ?>" 
                   href="<?= isset($nested) ? '' : 'resources/' ?>upload.php">
                    <i class="fas fa-fw fa-upload me-2"></i> Upload Resource
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'my-resources.php' ? 'active' : '' ?>" 
                   href="<?= isset($nested) ? '' : 'resources/' ?>my-resources.php">
                    <i class="fas fa-fw fa-list me-2"></i> My Resources
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'favorites.php' ? 'active' : '' ?>" 
                   href="<?= isset($nested) ? '' : 'resources/' ?>favorites.php">
                    <i class="fas fa-fw fa-star me-2"></i> Favorites
                </a>
            </li>
        </ul>
        
        <div class="sidebar-heading">
            Community
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= dirname($_SERVER['PHP_SELF']) == '/polyeduhub/student/chat' ? 'active' : '' ?>" 
                   href="<?= isset($nested) ? '../chat/' : 'chat/' ?>index.php">
                    <i class="fas fa-fw fa-comments me-2"></i> Chat Rooms
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= dirname($_SERVER['PHP_SELF']) == '/polyeduhub/student/leaderboard' ? 'active' : '' ?>" 
                   href="<?= isset($nested) ? '../leaderboard/' : 'leaderboard/' ?>index.php">
                    <i class="fas fa-fw fa-trophy me-2"></i> Leaderboard
                </a>
            </li>
        </ul>
        
        <div class="sidebar-heading">
            Account
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' && dirname($_SERVER['PHP_SELF']) == '/polyeduhub/student/profile' ? 'active' : '' ?>" 
                   href="<?= isset($nested) ? '../profile/' : 'profile/' ?>index.php">
                    <i class="fas fa-fw fa-user me-2"></i> My Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'badges.php' ? 'active' : '' ?>" 
                   href="<?= isset($nested) ? '../profile/' : 'profile/' ?>badges.php">
                    <i class="fas fa-fw fa-award me-2"></i> My Badges
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= dirname($_SERVER['PHP_SELF']) == '/polyeduhub/student/notifications' ? 'active' : '' ?>" 
                   href="<?= isset($nested) ? '../notifications/' : 'notifications/' ?>index.php">
                    <i class="fas fa-fw fa-bell me-2"></i> Notifications
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?= $base_path ?>logout.php">
                    <i class="fas fa-fw fa-sign-out-alt me-2"></i> Logout
                </a>
            </li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
            <div class="container-fluid">
                <!-- Mobile Toggle Button -->
                <button class="btn" id="sidebarToggle" type="button">
                    <i class="fas fa-bars"></i>
                </button>
                
                <!-- Search Form -->
                <form class="d-none d-md-flex ms-4 search-bar" action="<?= isset($nested) ? '' : 'resources/' ?>index.php" method="GET">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search for resources...">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                
                <!-- Right-aligned nav items -->
                <ul class="navbar-nav ms-auto">
                    <!-- Notifications Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                3
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><a class="dropdown-item" href="#">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <div class="bg-primary text-white p-2 rounded">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                    </div>
                                    <div class="ms-2">
                                        <p class="mb-0">Your resource was approved!</p>
                                        <small class="text-muted">Today, 10:30 AM</small>
                                    </div>
                                </div>
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center" href="<?= isset($nested) ? '../notifications/' : 'notifications/' ?>index.php">See all notifications</a></li>
                        </ul>
                    </li>
                    
                    <!-- User Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="d-none d-lg-inline me-2"><?= htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) ?></span>
                            <img class="rounded-circle" src="<?= $base_path ?>assets/img/ui/default-profile.png" width="30" height="30">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="<?= isset($nested) ? '../profile/' : 'profile/' ?>index.php"><i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="<?= isset($nested) ? '../profile/' : 'profile/' ?>edit.php"><i class="fas fa-cogs fa-sm fa-fw me-2 text-gray-400"></i> Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= $base_path ?>logout.php"><i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
        
        <!-- Page Content -->
        <div class="content">