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
$room_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check if room exists and user has access
$pdo = getDbConnection();
$stmt = $pdo->prepare("
    SELECT cr.id, cr.name, cr.description, cr.type, cr.created_by
    FROM chat_rooms cr 
    LEFT JOIN chat_room_members crm ON cr.id = crm.room_id AND crm.user_id = ?
    WHERE cr.id = ? AND (cr.type = 'public' OR (cr.type IN ('private', 'group') AND crm.user_id IS NOT NULL))
");
$stmt->execute([$user_id, $room_id]);
$room = $stmt->fetch();

if (!$room) {
    // Redirect to chat index if room doesn't exist or user doesn't have access
    $_SESSION['error_message'] = "You don't have access to this chat room.";
    header("Location: index.php");
    exit();
}

// Get chat room members
$stmt = $pdo->prepare("
    SELECT u.id, u.first_name, u.last_name, u.profile_image, u.role,
           (u.id = ?) AS is_current_user,
           (u.id = ?) AS is_room_creator
    FROM chat_room_members crm 
    JOIN users u ON crm.user_id = u.id
    WHERE crm.room_id = ?
    ORDER BY is_room_creator DESC, u.first_name, u.last_name
");
$stmt->execute([$user_id, $room['created_by'], $room_id]);
$members = $stmt->fetchAll();

// If the user is not a member of the room yet (for public rooms), add them
if (count($members) === 0 || !in_array($user_id, array_column($members, 'id'))) {
    $stmt = $pdo->prepare("INSERT INTO chat_room_members (room_id, user_id, joined_at) VALUES (?, ?, NOW())");
    $stmt->execute([$room_id, $user_id]);
    
    // Refresh members list
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.profile_image, u.role,
               (u.id = ?) AS is_current_user,
               (u.id = ?) AS is_room_creator
        FROM chat_room_members crm 
        JOIN users u ON crm.user_id = u.id
        WHERE crm.room_id = ?
        ORDER BY is_room_creator DESC, u.first_name, u.last_name
    ");
    $stmt->execute([$user_id, $room['created_by'], $room_id]);
    $members = $stmt->fetchAll();
}

// Get chat messages (with pagination)
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 50; // Number of messages per page
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare("
    SELECT cm.id, cm.message, cm.created_at, 
           u.id AS user_id, u.first_name, u.last_name, u.profile_image,
           (u.id = ?) AS is_own_message
    FROM chat_messages cm
    JOIN users u ON cm.user_id = u.id
    WHERE cm.room_id = ?
    ORDER BY cm.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$user_id, $room_id, $limit, $offset]);
$messages = $stmt->fetchAll();

// Get total number of messages for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE room_id = ?");
$stmt->execute([$room_id]);
$total_messages = $stmt->fetchColumn();
$total_pages = ceil($total_messages / $limit);

// Page title
$page_title = "Chat: " . $room['name'];
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
        .chat-container {
            display: flex;
            height: calc(100vh - 180px);
        }
        
        .chat-sidebar {
            width: 250px;
            background-color: #f8f9fc;
            border-right: 1px solid #e3e6f0;
            overflow-y: auto;
        }
        
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            padding: 15px;
            background-color: #fff;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .chat-messages {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            display: flex;
            flex-direction: column-reverse;
        }
        
        .chat-input {
            padding: 15px;
            background-color: #fff;
            border-top: 1px solid #e3e6f0;
        }
        
        .message {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
        }
        
        .message.own-message {
            flex-direction: row-reverse;
        }
        
        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }
        
        .message.own-message .message-avatar {
            margin-right: 0;
            margin-left: 10px;
        }
        
        .message-content {
            max-width: 70%;
            position: relative;
        }
        
        .message-bubble {
            background-color: #f0f2f5;
            border-radius: 18px;
            padding: 10px 15px;
            display: inline-block;
            word-break: break-word;
        }
        
        .message.own-message .message-bubble {
            background-color: #4e73df;
            color: white;
        }
        
        .message-info {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .message.own-message .message-info {
            text-align: right;
        }
        
        .member-item {
            padding: 10px 15px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .member-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }
        
        .member-creator-badge {
            font-size: 0.7rem;
            background-color: #4e73df;
            color: white;
            padding: 2px 5px;
            border-radius: 3px;
            margin-left: 5px;
        }
        
        .member-current-badge {
            font-size: 0.7rem;
            background-color: #1cc88a;
            color: white;
            padding: 2px 5px;
            border-radius: 3px;
            margin-left: 5px;
        }
        
        .chat-input form {
            display: flex;
        }
        
        .chat-input-field {
            flex: 1;
            border-radius: 20px;
            padding: 10px 15px;
        }
        
        .chat-send-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            margin-left: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .message-time {
            font-size: 0.7rem;
            color: #adb5bd;
            margin-top: 2px;
        }
        
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 15px;
        }
        
        @media (max-width: 992px) {
            .chat-sidebar {
                position: fixed;
                left: -250px;
                top: 0;
                bottom: 0;
                z-index: 1000;
                transition: left 0.3s ease;
            }
            
            .chat-sidebar.show {
                left: 0;
            }
            
            .chat-sidebar-toggle {
                display: block;
            }
        }
        
        @media (min-width: 993px) {
            .chat-sidebar-toggle {
                display: none;
            }
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
        <nav class="navbar navbar-expand-lg navbar-light mb-0">
            <div class="container-fluid">
                <button class="btn toggle-sidebar" id="toggleSidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <a href="index.php" class="btn btn-link text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i> Back to Rooms
                </a>
                
                <div class="navbar-nav ms-auto">
                    <button class="btn chat-sidebar-toggle d-lg-none" id="toggleChatSidebar">
                        <i class="fas fa-users"></i>
                    </button>
                    
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
        
        <!-- Chat Container -->
        <div class="chat-container">
            <!-- Chat Sidebar (Members) -->
            <div class="chat-sidebar" id="chatSidebar">
                <div class="p-3 bg-white border-bottom">
                    <h6 class="m-0 font-weight-bold">Members (<?= count($members) ?>)</h6>
                </div>
                <div class="members-list">
                    <?php foreach ($members as $member): ?>
                    <div class="member-item">
                        <img class="member-avatar" src="<?= $member['profile_image'] ? htmlspecialchars($member['profile_image']) : '../../assets/img/ui/default-profile.png' ?>" alt="<?= htmlspecialchars($member['first_name']) ?>">
                        <div>
                            <div>
                                <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>
                                <?php if ($member['is_room_creator']): ?>
                                <span class="member-creator-badge">Creator</span>
                                <?php endif; ?>
                                <?php if ($member['is_current_user']): ?>
                                <span class="member-current-badge">You</span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted"><?= ucfirst($member['role']) ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($room['type'] === 'private' && ($room['created_by'] == $user_id)): ?>
                <div class="p-3">
                    <button class="btn btn-primary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#inviteModal">
                        <i class="fas fa-user-plus me-1"></i> Invite Members
                    </button>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Chat Main Area -->
            <div class="chat-main">
                <!-- Chat Header -->
                <div class="chat-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1"><?= htmlspecialchars($room['name']) ?></h5>
                            <small class="text-muted"><?= htmlspecialchars($room['description'] ?: 'No description provided.') ?></small>
                        </div>
                        <div>
                            <span class="badge bg-<?= $room['type'] === 'public' ? 'primary' : ($room['type'] === 'private' ? 'danger' : 'success') ?>">
                                <?= ucfirst($room['type']) ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Chat Messages -->
                <div class="chat-messages" id="chatMessages">
                    <?php if (empty($messages)): ?>
                    <div class="text-center text-muted my-4">
                        <i class="fas fa-comments fa-3x mb-3"></i>
                        <p>No messages yet. Be the first to start the conversation!</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                        <div class="message <?= $message['is_own_message'] ? 'own-message' : '' ?>">
                            <img class="message-avatar" src="<?= $message['profile_image'] ? htmlspecialchars($message['profile_image']) : '../../assets/img/ui/default-profile.png' ?>" alt="<?= htmlspecialchars($message['first_name']) ?>">
                            <div class="message-content">
                                <div class="message-bubble">
                                    <?= nl2br(htmlspecialchars($message['message'])) ?>
                                </div>
                                <div class="message-info">
                                    <?php if (!$message['is_own_message']): ?>
                                    <span class="message-sender"><?= htmlspecialchars($message['first_name'] . ' ' . $message['last_name']) ?></span> •
                                    <?php endif; ?>
                                    <span class="message-time" title="<?= date('M j, Y g:i A', strtotime($message['created_at'])) ?>">
                                        <?= time_elapsed_string($message['created_at']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination-container">
                            <nav aria-label="Message navigation">
                                <ul class="pagination pagination-sm">
                                    <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?id=<?= $room_id ?>&page=<?= $page - 1 ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    // Show limited page numbers with ellipsis
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    if ($start_page > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="?id=' . $room_id . '&page=1">1</a></li>';
                                        if ($start_page > 2) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                    }
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++) {
                                        echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">
                                            <a class="page-link" href="?id=' . $room_id . '&page=' . $i . '">' . $i . '</a>
                                        </li>';
                                    }
                                    
                                    if ($end_page < $total_pages) {
                                        if ($end_page < $total_pages - 1) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                        echo '<li class="page-item"><a class="page-link" href="?id=' . $room_id . '&page=' . $total_pages . '">' . $total_pages . '</a></li>';
                                    }
                                    ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?id=<?= $room_id ?>&page=<?= $page + 1 ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Chat Input -->
                <div class="chat-input">
                    <form id="messageForm" action="send_message.php" method="POST">
                        <input type="hidden" name="room_id" value="<?= $room_id ?>">
                        <input type="text" class="form-control chat-input-field" name="message" id="messageInput" placeholder="Type your message..." required>
                        <button type="submit" class="btn btn-primary chat-send-btn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Invite Members Modal -->
        <?php if ($room['type'] === 'private' && ($room['created_by'] == $user_id)): ?>
        <div class="modal fade" id="inviteModal" tabindex="-1" aria-labelledby="inviteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="inviteModalLabel">Invite Members</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Share this room code with students you want to invite:</p>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" id="roomCode" value="<?= bin2hex(random_bytes(4)) ?>-<?= $room_id ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" id="copyCodeBtn">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Members can join by clicking "Join Private Room" on the chat rooms page and entering this code.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
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
        // Toggle main sidebar on mobile
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
            document.querySelector('.content').classList.toggle('pushed');
        });
        
        // Toggle chat sidebar on mobile
        document.getElementById('toggleChatSidebar').addEventListener('click', function() {
            document.getElementById('chatSidebar').classList.toggle('show');
        });
        
        // Handle message form submission with AJAX
        document.getElementById('messageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();
            
            if (!message) return;
            
            fetch('send_message.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear input
                    messageInput.value = '';
                    
                    // Add message to chat (for immediate feedback)
                    const chatMessages = document.getElementById('chatMessages');
                    const newMessage = document.createElement('div');
                    newMessage.className = 'message own-message';
                    
                    const currentTime = new Date().toLocaleTimeString();
                    
                    newMessage.innerHTML = `
                        <img class="message-avatar" src="../../assets/img/ui/default-profile.png" alt="Your Avatar">
                        <div class="message-content">
                            <div class="message-bubble">${message.replace(/\n/g, '<br>')}</div>
                            <div class="message-info">
                                <span class="message-time">just now</span>
                            </div>
                        </div>
                    `;
                    
                    // Insert at the top since messages are ordered newest first
                    chatMessages.insertBefore(newMessage, chatMessages.firstChild);
                } else {
                    alert('Error sending message: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while sending your message. Please try again.');
            });
        });
        
        // Copy room code to clipboard
        document.getElementById('copyCodeBtn')?.addEventListener('click', function() {
            const roomCodeInput = document.getElementById('roomCode');
            roomCodeInput.select();
            document.execCommand('copy');
            
            // Show feedback
            this.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(() => {
                this.innerHTML = '<i class="fas fa-copy"></i>';
            }, 2000);
        });
        
        // Poll for new messages
        let lastMessageId = <?= !empty($messages) ? $messages[0]['id'] : 0 ?>;
        
        function pollNewMessages() {
            fetch(`get_new_messages.php?room_id=<?= $room_id ?>&after=${lastMessageId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.messages.length > 0) {
                    const chatMessages = document.getElementById('chatMessages');
                    
                    // Update last message ID
                    lastMessageId = data.messages[0].id;
                    
                    // Add new messages
                    data.messages.forEach(message => {
                        const newMessage = document.createElement('div');
                        newMessage.className = `message ${message.is_own_message ? 'own-message' : ''}`;
                        
                        newMessage.innerHTML = `
                            <img class="message-avatar" src="${message.profile_image || '../../assets/img/ui/default-profile.png'}" alt="${message.first_name}">
                            <div class="message-content">
                                <div class="message-bubble">${message.message.replace(/\n/g, '<br>')}</div>
                                <div class="message-info">
                                    ${!message.is_own_message ? `<span class="message-sender">${message.first_name} ${message.last_name}</span> • ` : ''}
                                    <span class="message-time" title="${new Date(message.created_at).toLocaleString()}">${timeElapsedString(message.created_at)}</span>
                                </div>
                            </div>
                        `;
                        
                        // Insert at the top since messages are ordered newest first
                        chatMessages.insertBefore(newMessage, chatMessages.firstChild);
                    });
                }
                
                // Continue polling
                setTimeout(pollNewMessages, 5000);
            })
            .catch(error => {
                console.error('Error polling for new messages:', error);
                // Retry after error
                setTimeout(pollNewMessages, 10000);
            });

            <span class="message-time" title="${new Date(message.created_at).toLocaleString()}">${message.time_ago}</span>
        }
        
        // Start polling
        setTimeout(pollNewMessages, 5000);
        
        
        
    </script>
</body>
</html>