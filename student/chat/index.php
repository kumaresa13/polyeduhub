<?php
// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db-connection.php';
require_once '../../includes/functions.php';

// Check if the session is already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Only check if user is logged in
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
$nested = true; 

// Include header
include_once '../includes/header.php';
?>

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

<script>
    // Join Room Modal
    document.getElementById('joinRoomBtn').addEventListener('click', function(e) {
        e.preventDefault();
        var joinRoomModal = new bootstrap.Modal(document.getElementById('joinRoomModal'));
        joinRoomModal.show();
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>