<!-- Place this file in: student/includes/header.php -->
<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check student authentication
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    // Calculate the path to login.php based on current depth
    $current_path = $_SERVER['PHP_SELF'];
    $path_parts = explode('/', $current_path);
    $student_index = array_search('student', $path_parts);
    $depth = count($path_parts) - $student_index - 2;
    $login_path = str_repeat('../', $depth) . '../login.php';

    header("Location: " . $login_path);
    exit();
}

// Determine base path for assets and links
$current_path = $_SERVER['PHP_SELF'];
$path_parts = explode('/', $current_path);
$student_index = array_search('student', $path_parts);
$depth = count($path_parts) - $student_index - 2;
$base_path = ($depth > 0) ? str_repeat('../', $depth) : '../';

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Student Dashboard' ?> - PolyEduHub</title>

    <!-- Favicon -->
    <link rel="shortcut icon" href="<?= $base_path ?>../assets/img/favicon.png" type="image/x-icon">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Custom Student CSS -->
    <link rel="stylesheet" href="<?= $base_path ?>../assets/css/student.css">

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

        /* Badge styling */
        .badge-icon {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100px;
            height: 100px;
            margin: 0 auto 1rem;
            background-color: #f8f9fc;
            border-radius: 50%;
            padding: 10px;
        }

        .badge-icon img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .badge-stat {
            padding: 0.75rem;
            border-radius: 0.5rem;
            background-color: #f8f9fc;
            min-width: 80px;
        }
    </style>

    <!-- Additional Page-Specific Styles -->
    <?= isset($additional_styles) ? $additional_styles : '' ?>
</head>

<body>
    <!-- Sidebar -->
    <?php include_once 'sidebar.php'; ?>

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
                <form class="d-none d-md-flex ms-4 search-bar" action="<?= $base_path ?>resources/index.php"
                    method="GET">
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
                        <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationDropdown"
                            role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <?php
                            // Get unread notification count
                            $unread_count = 0;
                            try {
                                $pdo = getDbConnection();
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
                                $stmt->execute([$_SESSION['id']]);
                                $unread_count = $stmt->fetchColumn();
                            } catch (Exception $e) {
                                // Silent error - default to 0
                            }

                            // Only show badge if there are unread notifications
                            if ($unread_count > 0):
                                ?>
                                <span
                                    class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?= $unread_count > 99 ? '99+' : $unread_count ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown">
                            <li>
                                <h6 class="dropdown-header">Notifications</h6>
                            </li>

                            <?php
                            // Get recent notifications
                            $recent_notifications = [];
                            try {
                                $stmt = $pdo->prepare("
                    SELECT message, link, created_at 
                    FROM notifications 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 3
                ");
                                $stmt->execute([$_SESSION['id']]);
                                $recent_notifications = $stmt->fetchAll();
                            } catch (Exception $e) {
                                // Silent error
                            }

                            if (empty($recent_notifications)):
                                ?>
                                <li>
                                    <div class="dropdown-item text-center text-muted">No new notifications</div>
                                </li>
                            <?php else: ?>
                                <?php foreach ($recent_notifications as $notification): ?>
                                    <li>
                                        <a class="dropdown-item" href="<?= htmlspecialchars($notification['link'] ?: '#') ?>">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0">
                                                    <div class="bg-primary text-white p-2 rounded">
                                                        <i class="fas fa-bell"></i>
                                                    </div>
                                                </div>
                                                <div class="ms-2">
                                                    <p class="mb-0"><?= htmlspecialchars($notification['message']) ?></p>
                                                    <small
                                                        class="text-muted"><?= time_elapsed_string($notification['created_at']) ?></small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <li><a class="dropdown-item text-center"
                                    href="<?= isset($nested) ? '../notifications/' : 'notifications/' ?>index.php">See
                                    all notifications</a></li>
                        </ul>
                    </li>

                    <!-- User Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <span
                                class="d-none d-lg-inline me-2"><?= htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) ?></span>
                            <?php
                            // Get the user's profile image from session or fetch from database
                            $profile_image = $_SESSION['profile_image'] ?? '';
                            if (empty($profile_image)) {
                                // Try to fetch from database if not in session
                                try {
                                    $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
                                    $stmt->execute([$_SESSION['id']]);
                                    $profile_image = $stmt->fetchColumn();
                                    // Update session for future use
                                    if ($profile_image) {
                                        $_SESSION['profile_image'] = $profile_image;
                                    }
                                } catch (Exception $e) {
                                    // Silent error - use default image
                                }
                            }

                            // Use profile image or default
                            $image_path = !empty($profile_image) ? $base_path . $profile_image : $base_path . 'assets/img/ui/default-profile.png';
                            ?>
                            <img class="rounded-circle" src="<?= $image_path ?>" width="30" height="30" alt="Profile">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item"
                                    href="<?= isset($nested) ? '../profile/' : 'profile/' ?>index.php"><i
                                        class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i> Profile</a></li>
                            <li><a class="dropdown-item"
                                    href="<?= isset($nested) ? '../profile/' : 'profile/' ?>edit.php"><i
                                        class="fas fa-cogs fa-sm fa-fw me-2 text-gray-400"></i> Settings</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="<?= $base_path ?>logout.php"><i
                                        class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Page Content -->
        <div class="content">