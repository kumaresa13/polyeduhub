<?php
// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db-connection.php';
require_once '../../includes/functions.php';

// Check if the session is already started
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
$nested = true; 

// Include header
include_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <?= htmlspecialchars($room['name']) ?>
            <span class="badge bg-<?= $room['type'] === 'public' ? 'primary' : ($room['type'] === 'private' ? 'danger' : 'success') ?>">
                <?= ucfirst($room['type']) ?>
            </span>
        </h1>
        <a href="index.php" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-arrow-left me-1"></i> Back to Rooms
        </a>
    </div>
    
    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= $_SESSION['error_message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <!-- Chat Container -->
    <div class="row">
        <!-- Chat Members Sidebar -->
        <div class="col-md-3 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Members (<?= count($members) ?>)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($members as $member): ?>
                        <div class="list-group-item d-flex align-items-center">
                            <img class="rounded-circle me-2" src="<?= $member['profile_image'] ? htmlspecialchars($member['profile_image']) : '../../assets/img/ui/default-profile.png' ?>" alt="<?= htmlspecialchars($member['first_name']) ?>" width="36" height="36">
                            <div>
                                <div class="fw-bold">
                                    <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>
                                    <?php if ($member['is_room_creator']): ?>
                                    <span class="badge bg-primary ms-1">Creator</span>
                                    <?php endif; ?>
                                    <?php if ($member['is_current_user']): ?>
                                    <span class="badge bg-success ms-1">You</span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted"><?= ucfirst($member['role']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if ($room['type'] === 'private' && ($room['created_by'] == $user_id)): ?>
                <div class="card-footer">
                    <button class="btn btn-primary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#inviteModal">
                        <i class="fas fa-user-plus me-1"></i> Invite Members
                    </button>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Room Info Card -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Room Information</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($room['description'])): ?>
                    <p><?= htmlspecialchars($room['description']) ?></p>
                    <?php else: ?>
                    <p class="text-muted">No description provided.</p>
                    <?php endif; ?>
                    
                    <?php if ($messages): ?>
                    <div class="small text-muted mt-3">
                        <i class="fas fa-info-circle me-1"></i> This room has <?= $total_messages ?> messages.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Chat Main Area -->
        <div class="col-md-9">
            <div class="card shadow mb-4">
                <!-- Chat Messages -->
                <div class="card-body" style="height: 500px; overflow-y: auto;" id="chatMessages">
                    <?php if (empty($messages)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-comments fa-3x mb-3"></i>
                        <p>No messages yet. Be the first to start the conversation!</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                        <div class="mb-3 <?= $message['is_own_message'] ? 'text-end' : '' ?>">
                            <div class="d-inline-flex mw-75 <?= $message['is_own_message'] ? 'flex-row-reverse' : '' ?>">
                                <div class="me-2 <?= $message['is_own_message'] ? 'ms-2 me-0' : '' ?>">
                                    <img class="rounded-circle" src="<?= $message['profile_image'] ? htmlspecialchars($message['profile_image']) : '../../assets/img/ui/default-profile.png' ?>" alt="<?= htmlspecialchars($message['first_name']) ?>" width="40" height="40">
                                </div>
                                <div>
                                    <div class="p-3 rounded <?= $message['is_own_message'] ? 'bg-primary text-white' : 'bg-light' ?>">
                                        <?= nl2br(htmlspecialchars($message['message'])) ?>
                                    </div>
                                    <div class="small mt-1 text-muted">
                                        <?php if (!$message['is_own_message']): ?>
                                        <span><?= htmlspecialchars($message['first_name'] . ' ' . $message['last_name']) ?></span> • 
                                        <?php endif; ?>
                                        <span title="<?= date('M j, Y g:i A', strtotime($message['created_at'])) ?>">
                                            <?= time_elapsed_string($message['created_at']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="d-flex justify-content-center mt-4">
                            <nav aria-label="Message navigation">
                                <ul class="pagination pagination-sm">
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                        <a class="page-link" href="?id=<?= $room_id ?>&page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Chat Input -->
                <div class="card-footer bg-white">
                    <form id="messageForm" action="send_message.php" method="POST">
                        <input type="hidden" name="room_id" value="<?= $room_id ?>">
                        <div class="input-group">
                            <input type="text" class="form-control rounded-pill-start" name="message" id="messageInput" placeholder="Type your message..." required>
                            <button type="submit" class="btn btn-primary rounded-pill-end">
                                <i class="fas fa-paper-plane"></i> Send
                            </button>
                        </div>
                    </form>
                </div>
            </div>
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

<script>
    // Handle message form submission with AJAX
    document.addEventListener('DOMContentLoaded', function() {
        const messageForm = document.getElementById('messageForm');
        const messageInput = document.getElementById('messageInput');
        const chatMessages = document.getElementById('chatMessages');
        
        if (messageForm) {
            messageForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
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
                        const messageHtml = `
                            <div class="mb-3 text-end">
                                <div class="d-inline-flex mw-75 flex-row-reverse">
                                    <div class="ms-2">
                                        <img class="rounded-circle" src="../../assets/img/ui/default-profile.png" alt="Your Avatar" width="40" height="40">
                                    </div>
                                    <div>
                                        <div class="p-3 rounded bg-primary text-white">
                                            ${message.replace(/\n/g, '<br>')}
                                        </div>
                                        <div class="small mt-1 text-muted">
                                            <span title="${new Date().toLocaleString()}">
                                                just now
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        // Add at the top of the messages container
                        chatMessages.innerHTML = messageHtml + chatMessages.innerHTML;
                    } else {
                        alert('Error sending message: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while sending your message. Please try again.');
                });
            });
        }
        
        // Copy room code to clipboard
        const copyCodeBtn = document.getElementById('copyCodeBtn');
        if (copyCodeBtn) {
            copyCodeBtn.addEventListener('click', function() {
                const roomCodeInput = document.getElementById('roomCode');
                roomCodeInput.select();
                document.execCommand('copy');
                
                // Show feedback
                this.innerHTML = '<i class="fas fa-check"></i>';
                setTimeout(() => {
                    this.innerHTML = '<i class="fas fa-copy"></i>';
                }, 2000);
            });
        }
        
        // Scroll to bottom of chat messages
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Poll for new messages
        function pollNewMessages() {
            // Get the first message ID (newest message) if messages exist
            const firstMessage = document.querySelector('#chatMessages .mb-3');
            if (!firstMessage) return;
            
            const messageId = firstMessage.dataset.messageId;
            if (!messageId) return;
            
            fetch(`get_new_messages.php?room_id=<?= $room_id ?>&after=${messageId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.messages.length > 0) {
                    // Add new messages to the chat
                    let newMessagesHtml = '';
                    
                    data.messages.forEach(message => {
                        newMessagesHtml += `
                            <div class="mb-3 ${message.is_own_message ? 'text-end' : ''}" data-message-id="${message.id}">
                                <div class="d-inline-flex mw-75 ${message.is_own_message ? 'flex-row-reverse' : ''}">
                                    <div class="${message.is_own_message ? 'ms-2' : 'me-2'}">
                                        <img class="rounded-circle" src="${message.profile_image || '../../assets/img/ui/default-profile.png'}" alt="${message.first_name}" width="40" height="40">
                                    </div>
                                    <div>
                                        <div class="p-3 rounded ${message.is_own_message ? 'bg-primary text-white' : 'bg-light'}">
                                            ${message.message.replace(/\n/g, '<br>')}
                                        </div>
                                        <div class="small mt-1 text-muted">
                                            ${!message.is_own_message ? `<span>${message.first_name} ${message.last_name}</span> • ` : ''}
                                            <span title="${new Date(message.created_at).toLocaleString()}">
                                                ${message.time_ago}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    // Prepend new messages
                    chatMessages.innerHTML = newMessagesHtml + chatMessages.innerHTML;
                }
            })
            .catch(error => console.error('Error polling for new messages:', error));
        }
        
        // Poll for new messages every 5 seconds
        setInterval(pollNewMessages, 5000);
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>