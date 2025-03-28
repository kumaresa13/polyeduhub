<?php
// Place this file in: polyeduhub/student/includes/header.php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check student authentication
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Student Dashboard' ?> - <?= APP_NAME ?></title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="<?= isset($nested) ? '../' : '' ?>../assets/img/favicon.png" type="image/x-icon">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Student CSS -->
    <link rel="stylesheet" href="<?= isset($nested) ? '../' : '' ?>../assets/css/student.css">
    
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
        
        .sidebar-category {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.8rem;
            padding-left: 10px;
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
    <?= $additional_styles ?? '' ?>
</head>
<body>
    <!-- Sidebar -->
    <?php include_once dirname(__FILE__) . '/sidebar.php'; ?>
    
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
                <form class="d-none d-md-flex ms-4 search-bar">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Search for resources...">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                
                <!-- Right-aligned nav items -->
                <ul class="navbar-nav ms-auto">
                    <!-- Notifications Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <?php if (isset($unread_notifications) && $unread_notifications > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?= $unread_notifications ?>
                            </span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <!-- Notification items would go here -->
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center" href="<?= isset($nested) ? '../' : '' ?>notifications/index.php">See all notifications</a></li>
                        </ul>
                    </li>
                    
                    <!-- User Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="d-none d-lg-inline me-2"><?= htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) ?></span>
                            <img class="rounded-circle" src="<?= isset($nested) ? '../' : '' ?>../assets/img/ui/default-profile.png" width="30" height="30">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="<?= isset($nested) ? '../' : '' ?>profile/index.php"><i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="<?= isset($nested) ? '../' : '' ?>profile/edit.php"><i class="fas fa-cogs fa-sm fa-fw me-2 text-gray-400"></i> Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= isset($nested) ? '../' : '' ?>../logout.php"><i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
        
        <!-- Page Content -->
        <div class="content">