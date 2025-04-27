<?php
// File path: admin/chat/rooms.php

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

// Handle room deletion
if (isset($_POST['delete_room'])) {
    $room_id = intval($_POST['room_id']);
    
    try {
        $pdo = getDbConnection();
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Get room info for logging
        $stmt = $pdo->prepare("SELECT name FROM chat_rooms WHERE id = ?");
        $stmt->execute([$room_id]);
        $room_name = $stmt->fetchColumn();
        
        // Delete room members
        $stmt = $pdo->prepare("DELETE FROM chat_room_members WHERE room_id = ?");
        $stmt->execute([$room_id]);
        
        // Delete room messages
        $stmt = $pdo->prepare("DELETE FROM chat_messages WHERE room_id = ?");
        $stmt->execute([$room_id]);
        
        // Delete room
        $stmt = $pdo->prepare("DELETE FROM chat_rooms WHERE id = ?");
        $stmt->execute([$room_id]);
        
        // Log admin action
        logAdminAction($admin_id, "Deleted chat room", "Room name: " . $room_name);
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success_message'] = "Chat room deleted successfully";
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Error deleting chat room: " . $e->getMessage());
        $_SESSION['error_message'] = "Error deleting chat room: " . $e->getMessage();
    }
    
    // Redirect to refresh page
    header("Location: rooms.php");
    exit();
}

// Get filter parameters
$type = isset($_GET['type']) ? $_GET['type'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Fetch chat rooms
try {
    $pdo = getDbConnection();
    
    // Build WHERE clause for filters
    $where_clauses = [];
    $params = [];
    
    if (!empty($type)) {
        $where_clauses[] = "cr.type = ?";
        $params[] = $type;
    }
    
    if (!empty($search)) {
        $where_clauses[] = "(cr.name LIKE ? OR cr.description LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    // Count total rooms with filters
    $count_sql = "SELECT COUNT(*) FROM chat_rooms cr $where_sql";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_rooms = $stmt->fetchColumn();
    $total_pages = ceil($total_rooms / $limit);
    
    // Get rooms with pagination
    $sql = "
        SELECT cr.id, cr.name, cr.description, cr.type, cr.created_at,
               u.first_name, u.last_name,
               (SELECT COUNT(*) FROM chat_room_members WHERE room_id = cr.id) as member_count,
               (SELECT COUNT(*) FROM chat_messages WHERE room_id = cr.id) as message_count
        FROM chat_rooms cr
        JOIN users u ON cr.created_by = u.id
        $where_sql
        ORDER BY cr.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $all_params = array_merge($params, [$limit, $offset]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($all_params);
    $rooms = $stmt->fetchAll();
    
    // Get room statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_rooms,
            COUNT(CASE WHEN type = 'public' THEN 1 END) as public_rooms,
            COUNT(CASE WHEN type = 'private' THEN 1 END) as private_rooms,
            COUNT(CASE WHEN type = 'group' THEN 1 END) as group_rooms,
            (SELECT COUNT(*) FROM chat_messages) as total_messages,
            (SELECT COUNT(DISTINCT user_id) FROM chat_room_members) as total_participants
        FROM chat_rooms
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log("Error fetching chat rooms: " . $e->getMessage());
    $rooms = [];
    $total_rooms = 0;
    $total_pages = 0;
    $stats = [
        'total_rooms' => 0,
        'public_rooms' => 0,
        'private_rooms' => 0,
        'group_rooms' => 0,
        'total_messages' => 0,
        'total_participants' => 0
    ];
}

// Set page title and nested path variable
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
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Rooms</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['total_rooms']) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-comments fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Public Rooms</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['public_rooms']) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-globe fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Private Rooms</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['private_rooms']) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-lock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Group Rooms</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['group_rooms']) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Messages</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['total_messages']) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-comment-dots fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Participants</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['total_participants']) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-friends fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Rooms</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="type" class="form-label">Room Type</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">All Types</option>
                        <option value="public" <?= $type === 'public' ? 'selected' : '' ?>>Public</option>
                        <option value="private" <?= $type === 'private' ? 'selected' : '' ?>>Private</option>
                        <option value="group" <?= $type === 'group' ? 'selected' : '' ?>>Group</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                          placeholder="Room name or description..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Search</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Chat Rooms List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">All Chat Rooms</h6>
            <span>Showing <?= min($offset + 1, $total_rooms) ?>-<?= min($offset + $limit, $total_rooms) ?> of <?= $total_rooms ?> rooms</span>
        </div>
        <div class="card-body">
            <?php if (empty($rooms)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-comments fa-4x text-gray-300 mb-3"></i>
                    <p>No chat rooms found matching your criteria.</p>
                    <a href="rooms.php" class="btn btn-outline-primary mt-2">Clear Filters</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Room Name</th>
                                <th>Type</th>
                                <th>Created By</th>
                                <th>Members</th>
                                <th>Messages</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rooms as $room): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($room['name']) ?>
                                        <?php if (!empty($room['description'])): ?>
                                            <div class="small text-muted text-truncate" style="max-width: 250px;">
                                                <?= htmlspecialchars($room['description']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($room['type'] === 'public'): ?>
                                            <span class="badge bg-primary">Public</span>
                                        <?php elseif ($room['type'] === 'private'): ?>
                                            <span class="badge bg-danger">Private</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Group</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($room['first_name'] . ' ' . $room['last_name']) ?></td>
                                    <td><?= number_format($room['member_count']) ?></td>
                                    <td><?= number_format($room['message_count']) ?></td>
                                    <td><?= date('M j, Y', strtotime($room['created_at'])) ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view.php?id=<?= $room['id'] ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteRoomModal"
                                                    data-id="<?= $room['id'] ?>"
                                                    data-name="<?= htmlspecialchars($room['name']) ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-center mt-4"></div>
                    <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>&type=<?= urlencode($type) ?>&search=<?= urlencode($search) ?>">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&type=<?= urlencode($type) ?>&search=<?= urlencode($search) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>&type=<?= urlencode($type) ?>&search=<?= urlencode($search) ?>">
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
    </div>
</div>

<!-- Delete Room Modal -->
<div class="modal fade" id="deleteRoomModal" tabindex="-1" aria-labelledby="deleteRoomModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteRoomModalLabel">Delete Chat Room</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="room_id" id="delete_room_id">
                    <p>Are you sure you want to delete the chat room: <strong id="delete_room_name"></strong>?</p>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i> 
                        <strong>Warning:</strong> This action cannot be undone. All messages in this room will be permanently deleted.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_room" class="btn btn-danger">Delete Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Set up delete modal
    document.addEventListener('DOMContentLoaded', function() {
        const deleteModal = document.getElementById('deleteRoomModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const roomId = button.getAttribute('data-id');
                const roomName = button.getAttribute('data-name');
                
                document.getElementById('delete_room_id').value = roomId;
                document.getElementById('delete_room_name').textContent = roomName;
            });
        }
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>