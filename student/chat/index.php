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

// Get available chat rooms
$pdo = getDbConnection();
$stmt = $pdo->prepare("
    SELECT 
        cr.id, 
        cr.name, 
        cr.description, 
        cr.type, 
        cr.created_at,
        u.first_name, 
        u.last_name,
        (SELECT COUNT(*) FROM chat_room_members WHERE room_id = cr.id) as member_count,
        (SELECT COUNT(*) FROM chat_messages WHERE room_id = cr.id) as message_count
    FROM chat_rooms cr
    JOIN users u ON cr.created_by = u.id
    WHERE cr.type = 'public' 
        OR cr.id IN (SELECT room_id FROM chat_room_members WHERE user_id = ?)
    ORDER BY cr.created_at DESC
");
$stmt->execute([$user_id]);
$rooms = $stmt->fetchAll();

// Page title
$page_title = "Chat Rooms";
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
    
    <style>
        .chat-room-card {
            transition: transform 0.2s;
            height: 100%;
        }
        
        .chat-room-card:hover {
            transform: translateY(-5px);
        }
        
        .room-type-badge.public {
            background-color: #4e73df;
        }
        
        .room-type-badge.private {
            background-color: #e74a3b;
        }
        
        .room-type-badge.group {
            background-color: #1cc88a;
        }
        
        .chat-room-stats {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .create-room-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #4e73df;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            z-index: 100;
        }
    </style>
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
                
                <!-- Search -->
                <form class="navbar-search">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Search for chat rooms..." aria-label="Search">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                
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
                <h1 class="h3 mb-0 text-gray-800">Chat Rooms</h1>
                <div>
                    <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-outline-primary shadow-sm me-2" id="joinRoomBtn">
                        <i class="fas fa-sign-in-alt fa-sm text-primary-50"></i> Join Private Room
                    </a>
                    <a href="create.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                        <i class="fas fa-plus fa-sm text-white-50"></i> Create New Room
                    </a>
                </div>
            </div>
            
            <!-- Chat Rooms -->
            <div class="row">
                <?php if (empty($rooms)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> No chat rooms available. Create a new room to start chatting with others!
                    </div>
                </div>
                <?php else: ?>
                    <?php foreach ($rooms as $room): ?>
                    <div class="col-md-6 col-xl-4 mb-4">
                        <div class="card shadow chat-room-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold"><?= htmlspecialchars($room['name']) ?></h6>
                                <span class="badge room-type-badge <?= strtolower($room['type']) ?>"><?= ucfirst($room['type']) ?></span>
                            </div>
                            <div class="card-body">
                                <p class="card-text"><?= htmlspecialchars($room['description'] ?: 'No description provided.') ?></p>
                                <div class="chat-room-stats d-flex justify-content-between mb-3">
                                    <span><i class="fas fa-users me-1"></i> <?= $room['member_count'] ?> members</span>
                                    <span><i class="fas fa-comment-dots me-1"></i> <?= $room['message_count'] ?> messages</span>
                                </div>
                                <p class="card-text"><small class="text-muted">Created by <?= htmlspecialchars($room['first_name'] . ' ' . $room['last_name']) ?></small></p>
                                <a href="room.php?id=<?= $room['id'] ?>" class="btn btn-primary btn-block">
                                    <i class="fas fa-comments me-1"></i> Enter Chat Room
                                </a>
                            </div>
                            <div class="card-footer text-muted">
                                <small>Created <?= time_elapsed_string($room['created_at']) ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Join Private Room Modal -->
        <div class="modal fade" id="joinRoomModal" tabindex="-1" aria-labelledby="joinRoomModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="joinRoomModalLabel">Join Private Chat Room</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="join_room.php" method="POST">
                        <div class="modal-body">
                            <div class="form-group mb-3">
                                <label for="roomCode">Room Code</label>
                                <input type="text" class="form-control" id="roomCode" name="roomCode" placeholder="Enter room code" required>
                                <small class="form-text text-muted">Enter the private room code shared with you.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Join Room</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Mobile Create Room Button -->
        <a href="create.php" class="create-room-btn d-block d-sm-none">
            <i class="fas fa-plus fa-2x"></i>
        </a>
        
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
        
        // Join Room Modal
        document.getElementById('joinRoomBtn').addEventListener('click', function(e) {
            e.preventDefault();
            var joinRoomModal = new bootstrap.Modal(document.getElementById('joinRoomModal'));
            joinRoomModal.show();
        });
        
        // Time formatting helper function (to be replaced by your server-side function)
        function time_elapsed_string(datetime) {
            var now = new Date();
            var timestamp = new Date(datetime);
            var diff = Math.floor((now - timestamp) / 1000);
            
            if (diff < 60) {
                return 'just now';
            } else if (diff < 3600) {
                return Math.floor(diff / 60) + ' minutes ago';
            } else if (diff < 86400) {
                return Math.floor(diff / 3600) + ' hours ago';
            } else if (diff < 604800) {
                return Math.floor(diff / 86400) + ' days ago';
            } else {
                return 'on ' + timestamp.toLocaleDateString();
            }
        }
    </script>
</body>
</html>