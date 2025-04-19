<?php
// File path: admin/chat/view.php

// Include necessary files
require_once '../../includes/config.php';
require_once '../../includes/db-connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin-functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../admin-login.php");
    exit();
}

// Get admin user information
$admin_id = $_SESSION['id'];

// Get room ID from query string
$room_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$room_id) {
    $_SESSION['error_message'] = "Invalid room ID";
    header("Location: rooms.php");
    exit();
}

// Handle message deletion
if (isset($_POST['delete_message'])) {
    $message_id = intval($_POST['message_id']);
    
    try {
        $pdo = getDbConnection();
        
        // Delete message
        $stmt = $pdo->prepare("DELETE FROM chat_messages WHERE id = ?");
        $stmt->execute([$message_id]);
        
        // Log admin action
        logAdminAction($admin_id, "Deleted chat message", "Message ID: $message_id from Room ID: $room_id");
        
        $_SESSION['success_message'] = "Message deleted successfully";
    } catch (Exception $e) {
        error_log("Error deleting message: " . $e->getMessage());
        $_SESSION['error_message'] = "Error deleting message: " . $e->getMessage();
    }
    
    // Redirect to refresh page
    header("Location: view.php?id=$room_id");
    exit();
}

// Fetch room data
try {
    $pdo = getDbConnection();
    
    // Get room details
    $stmt = $pdo->prepare("
        SELECT cr.id, cr.name, cr.description, cr.type, cr.created_at, cr.created_by,
               u.first_name, u.last_name
        FROM chat_rooms cr
        JOIN users u ON cr.created_by = u.id
        WHERE cr.id = ?
    ");
    $stmt->execute([$room_id]);
    $room = $stmt->fetch();
    
    if (!$room) {
        $_SESSION['error_message'] = "Room not found";
        header("Location: rooms.php");
        exit();
    }
    
    // Get room members
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.department, crm.joined_at
        FROM chat_room_members crm
        JOIN users u ON crm.user_id = u.id
        WHERE crm.room_id = ?
        ORDER BY crm.joined_at ASC
    ");
    $stmt->execute([$room_id]);
    $members = $stmt->fetchAll();
    
    // Get room messages
    $stmt = $pdo->prepare("
        SELECT cm.id, cm.message, cm.created_at,
               u.id as user_id, u.first_name, u.last_name, u.profile_image, u.role
        FROM chat_messages cm
        JOIN users u ON cm.user_id = u.id
        WHERE cm.room_id = ?
        ORDER BY cm.created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$room_id]);
    $messages = $stmt->fetchAll();
    
    // Get stats
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as message_count
        FROM chat_messages
        WHERE room_id = ?
    ");
    $stmt->execute([$room_id]);
    $message_count = $stmt->fetchColumn();
    
    // Get unique users stats
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT user_id) as unique_posters
        FROM chat_messages
        WHERE room_id = ?
    ");
    $stmt->execute([$room_id]);
    $unique_posters = $stmt->fetchColumn();
    
    // Get last message date
    $stmt = $pdo->prepare("
        SELECT MAX(created_at) as last_message
        FROM chat_messages
        WHERE room_id = ?
    ");
    $stmt->execute([$room_id]);
    $last_message = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Error fetching room details: " . $e->getMessage());
    $_SESSION['error_message'] = "Error fetching room data";
    header("Location: rooms.php");
    exit();
}

// Set page title and nested path variable
$page_title = "Chat Room: " . $room['name'];
$nested = true;

// Include header
include_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Chat Room Details</h1>
        <div>
            <a href="rooms.php" class="btn btn-sm btn-secondary shadow-sm me-2">
                <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back to Rooms
            </a>
            <a href="../../student/chat/room.php?id=<?= $room_id ?>" target="_blank" class="btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-external-link-alt fa-sm text-white-50"></i> View as User
            </a>
        </div>
    </div>
    
    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['success_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <div class="row">
        <!-- Room Information -->
        <div class="col-xl-4">
            <!-- Room Details Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Room Information</h6>
                </div>
                <div class="card-body">
                    <h5 class="mb-2"><?= htmlspecialchars($room['name']) ?></h5>
                    <div class="mb-3">
                        <?php if ($room['type'] === 'public'): ?>
                            <span class="badge bg-primary">Public</span>
                        <?php elseif ($room['type'] === 'private'): ?>
                            <span class="badge bg-danger">Private</span>
                        <?php else: ?>
                            <span class="badge bg-success">Group</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Description</h6>
                        <p><?= !empty($room['description']) ? htmlspecialchars($room['description']) : 'No description provided.' ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Created By</h6>
                        <p>
                            <a href="../users/view.php?id=<?= $room['created_by'] ?>">
                                <?= htmlspecialchars($room['first_name'] . ' ' . $room['last_name']) ?>
                            </a>
                        </p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Created On</h6>
                        <p><?= date('F j, Y, g:i a', strtotime($room['created_at'])) ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Stats Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Room Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6 text-center">
                            <div class="h4 font-weight-bold text-primary"><?= number_format(count($members)) ?></div>
                            <div class="text-muted">Members</div>
                        </div>
                        <div class="col-md-6 text-center">
                            <div class="h4 font-weight-bold text-primary"><?= number_format($message_count) ?></div>
                            <div class="text-muted">Messages</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 text-center">
                            <div class="h4 font-weight-bold text-primary"><?= number_format($unique_posters) ?></div>
                            <div class="text-muted">Active Users</div>
                        </div>
                        <div class="col-md-6 text-center">
                            <div class="h4 font-weight-bold text-primary">
                                <?= $last_message ? time_elapsed_string($last_message) : 'Never' ?>
                            </div>
                            <div class="text-muted">Last Activity</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Members Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Room Members</h6>
                    <span class="badge bg-primary"><?= count($members) ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($members)): ?>
                        <div class="text-center py-4">
                            <p class="mb-0">No members in this room.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($members as $member): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <a href="../users/view.php?id=<?= $member['id'] ?>">
                                                <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>
                                            </a>
                                            <div class="small text-muted">
                                                <?= htmlspecialchars($member['email']) ?>
                                            </div>
                                            <div class="small">
                                                <?php if ($member['role'] === 'admin'): ?>
                                                    <span class="badge bg-warning">Administrator</span>
                                                <?php elseif ($member['role'] === 'teacher'): ?>
                                                    <span class="badge bg-info">Teacher</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary">Student</span>
                                                <?php endif; ?>
                                                <?php if ($member['id'] == $room['created_by']): ?>
                                                    <span class="badge bg-success">Creator</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="small text-muted">
                                            Joined <?= date('M j, Y', strtotime($member['joined_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Chat Messages -->
        <div class="col-xl-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Messages</h6>
                    <span class="badge bg-primary"><?= number_format($message_count) ?> Total</span>
                </div>
                <div class="card-body">
                    <?php if (empty($messages)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-comments fa-4x text-gray-300 mb-3"></i>
                            <p class="mb-0">No messages in this room yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="chat-container" style="max-height: 600px; overflow-y: auto;">
                            <?php foreach ($messages as $message): ?>
                                <div class="mb-4">
                                    <div class="d-flex">
                                        <div class="flex-shrink-0 me-3">
                                            <img class="rounded-circle" 
                                                 src="<?= !empty($message['profile_image']) ? '../../' . $message['profile_image'] : '../../assets/img/ui/default-profile.png' ?>" 
                                                 width="50" height="50" alt="Profile Image">
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <a href="../users/view.php?id=<?= $message['user_id'] ?>" class="fw-bold">
                                                        <?= htmlspecialchars($message['first_name'] . ' ' . $message['last_name']) ?>
                                                    </a>
                                                    <?php if ($message['role'] === 'admin'): ?>
                                                        <span class="badge bg-warning ms-1">Admin</span>
                                                    <?php elseif ($message['role'] === 'teacher'): ?>
                                                        <span class="badge bg-info ms-1">Teacher</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-muted small">
                                                    <?= date('M j, Y, g:i a', strtotime($message['created_at'])) ?>
                                                </div>
                                            </div>
                                            <div class="message-content p-3 rounded bg-light mt-2">
                                                <?= nl2br(htmlspecialchars($message['message'])) ?>
                                            </div>
                                            <div class="mt-2 text-end">
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteMessageModal"
                                                        data-id="<?= $message['id'] ?>">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Message Modal -->
<div class="modal fade" id="deleteMessageModal" tabindex="-1" aria-labelledby="deleteMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteMessageModalLabel">Delete Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="message_id" id="delete_message_id">
                    <p>Are you sure you want to delete this message?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> 
                        This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_message" class="btn btn-danger">Delete Message</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Set up delete message modal
    document.addEventListener('DOMContentLoaded', function() {
        const deleteModal = document.getElementById('deleteMessageModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const messageId = button.getAttribute('data-id');
                document.getElementById('delete_message_id').value = messageId;
            });
        }
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>