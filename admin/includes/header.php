<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check admin authentication
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../admin-login.php");
    exit();
}

// Base path for assets and links
$base_path = isset($nested) ? '../../' : '../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Admin Dashboard' ?> - <?= APP_NAME ?></title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="<?= $base_path ?>assets/img/favicon.png" type="image/x-icon">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Admin CSS -->
    <link rel="stylesheet" href="<?= $base_path ?>assets/css/admin.css">
    
    <style>
        body {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        
        .sidebar {
            width: 220px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            z-index: 1000;
            background-color: #4e73df !important;
            color: white;
        }
        
        .sidebar-heading {
            font-size: 0.7rem;
            padding: 0.75rem 1rem;
            text-transform: uppercase;
            font-weight: bold;
            color: rgba(255, 255, 255, 0.6);
        }
        
        .sidebar .nav-item .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
        
        .sidebar .nav-item .nav-link:hover {
            color: #fff !important;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar .nav-item .nav-link.active {
            color: #fff !important;
            font-weight: bold;
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .sidebar .nav-item .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 0.5rem;
        }
        
        .main-content {
            flex: 1;
            margin-left: 220px;
            overflow-y: auto;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .topbar {
            height: 56px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            z-index: 900;
            background-color: #fff;
        }
        
        .content {
            flex: 1;
            padding: 1.5rem;
            overflow-y: auto;
        }
        
        .copyright {
            font-size: 0.8rem;
            padding: 0.5rem 1.5rem;
            border-top: 1px solid #eaecf4;
            background-color: #fff;
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
    <?php include 'sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white topbar">
            <div class="container-fluid px-4">
                <!-- Mobile Toggle Button -->
                <button class="btn d-md-none" id="sidebarToggle" type="button">
                    <i class="fas fa-bars"></i>
                </button>
                
                <!-- Search Form -->
                <form class="d-none d-md-flex ms-4" style="flex: 1; max-width: 300px;">
                    <div class="input-group">
                        <input type="text" class="form-control border-0 bg-light" placeholder="Search...">
                        <button class="btn btn-primary" type="button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                
                <!-- Right-aligned nav items -->
                <ul class="navbar-nav ms-auto">
                    <!-- Notifications Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell fa-fw"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                1
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
                                        <p class="mb-0">New resource needs approval</p>
                                        <small class="text-muted">Today, 10:30 AM</small>
                                    </div>
                                </div>
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center small" href="#">Show All Notifications</a></li>
                        </ul>
                    </li>
                    
                    <!-- User Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="d-none d-lg-inline me-2 text-gray-600"><?= htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) ?></span>
                            <img class="rounded-circle" src="<?= $base_path ?>assets/img/ui/default-profile.png" width="32" height="32">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i> Settings</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-list fa-sm fa-fw mr-2 text-gray-400"></i> Activity Log</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= $base_path ?>logout.php"><i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
        
        <!-- Page Content -->
        <div class="content">