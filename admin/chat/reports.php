<?php
// File path: admin/chat/reports.php

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

// Handle report resolution
if (isset($_POST['resolve_report'])) {
    $report_id = intval($_POST['report_id']);
    $action = $_POST['action']; // 'delete_message', 'dismiss'
    
    try {
        $pdo = getDbConnection();
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Get report info before updating
        $stmt = $pdo->prepare("
            SELECT cr.id, cr.message_id, cr.reporter_id, cr.reason, cr.reported_at,
                   cm.message, cm.user_id as message_user_id
            FROM chat_reports cr
            JOIN chat_messages cm ON cr.message_id = cm.id
            WHERE cr.id = ?
        ");
        $stmt->execute([$report_id]);
        $report = $stmt->fetch();
        
        if (!$report) {
            throw new Exception("Report not found");
        }
        
        // Update report status to resolved
        $stmt = $pdo->prepare("
            UPDATE chat_reports 
            SET status = 'resolved', 
                resolved_by = ?, 
                resolved_at = NOW(),
                resolution_notes = ?
            WHERE id = ?
        ");
        
        $resolution_notes = "Report resolved by admin. Action taken: ";
        
        if ($action === 'delete_message') {
            // Delete the reported message
            $stmt_delete = $pdo->prepare("DELETE FROM chat_messages WHERE id = ?");
            $stmt_delete->execute([$report['message_id']]);
            
            $resolution_notes .= "Message deleted";
            
            // Log admin action for message deletion
            logAdminAction(
                $admin_id, 
                "Deleted reported chat message", 
                "Message ID: {$report['message_id']}, Reported by User ID: {$report['reporter_id']}"
            );
            
            // Create notification for the reporter
            createNotification(
                $report['reporter_id'],
                "Your report has been reviewed and the message has been removed.",
                "chat/index.php"
            );
            
        } else {
            // Just dismiss the report
            $resolution_notes .= "Report dismissed";
            
            // Create notification for the reporter
            createNotification(
                $report['reporter_id'],
                "Your report has been reviewed. The message does not violate our community standards.",
                "chat/index.php"
            );
        }
        
        // Execute the report status update
        $stmt->execute([$admin_id, $resolution_notes, $report_id]);
        
        // Log admin action for report resolution
        logAdminAction(
            $admin_id, 
            "Resolved chat message report", 
            "Report ID: $report_id, Action: $action"
        );
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success_message'] = "Report resolved successfully";
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Error resolving report: " . $e->getMessage());
        $_SESSION['error_message'] = "Error resolving report: " . $e->getMessage();
    }
    
    // Redirect to refresh page
    header("Location: reports.php");
    exit();
}

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : 'pending';

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Fetch message reports
try {
    $pdo = getDbConnection();
    
    // Build WHERE clause for filters
    $where_clause = "WHERE cr.status = ?";
    $params = [$status];
    
    // Count total reports with filters
    $count_sql = "SELECT COUNT(*) FROM chat_reports cr $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_reports = $stmt->fetchColumn();
    $total_pages = ceil($total_reports / $limit);
    
    // Get reports with pagination
    $sql = "
        SELECT cr.id, cr.message_id, cr.reporter_id, cr.reason, cr.reported_at, cr.status,
               cr.resolved_by, cr.resolved_at, cr.resolution_notes,
               cm.message, cm.created_at as message_date, cm.room_id,
               r.name as room_name,
               u1.first_name as reporter_first_name, u1.last_name as reporter_last_name,
               u2.id as sender_id, u2.first_name as sender_first_name, u2.last_name as sender_last_name
        FROM chat_reports cr
        JOIN chat_messages cm ON cr.message_id = cm.id
        JOIN chat_rooms r ON cm.room_id = r.id
        JOIN users u1 ON cr.reporter_id = u1.id
        JOIN users u2 ON cm.user_id = u2.id
        $where_clause
        ORDER BY cr.reported_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $all_params = array_merge($params, [$limit, $offset]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($all_params);
    $reports = $stmt->fetchAll();
    
    // Get report statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_reports,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_reports,
            COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_reports
        FROM chat_reports
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log("Error fetching message reports: " . $e->getMessage());
    $reports = [];
    $total_reports = 0;
    $total_pages = 0;
    $stats = [
        'total_reports' => 0,
        'pending_reports' => 0,
        'resolved_reports' => 0
    ];
}

// Set page title and nested path variable
$page_title = "Reported Messages";
$nested = true;

// Include header
include_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Reported Messages</h1>
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
        <div class="col-xl-4 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Reports</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['total_reports']) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-flag fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Pending Reports</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['pending_reports']) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Resolved Reports</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['resolved_reports']) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter Tabs -->
    <div class="card shadow mb-4">
        <div class="card-body p-0">
            <ul class="nav nav-tabs">
                <li class="nav-item">
                    <a class="nav-link <?= $status === 'pending' ? 'active' : '' ?>" href="?status=pending">
                        <i class="fas fa-exclamation-circle me-1"></i> Pending
                        <span class="badge bg-warning text-dark"><?= number_format($stats['pending_reports']) ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $status === 'resolved' ? 'active' : '' ?>" href="?status=resolved">
                        <i class="fas fa-check-circle me-1"></i> Resolved
                        <span class="badge bg-success"><?= number_format($stats['resolved_reports']) ?></span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <!-- Reports Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <?= $status === 'pending' ? 'Pending Reports' : 'Resolved Reports' ?>
            </h6>
            <span>Showing <?= min($offset + 1, $total_reports) ?>-<?= min($offset + $limit, $total_reports) ?> of <?= $total_reports ?> reports</span>
        </div>
        <div class="card-body">
            <?php if (empty($reports)): ?>
                <div class="text-center py-4">
                    <?php if ($status === 'pending'): ?>
                        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                        <p>No pending reports to review. Good job!</p>
                    <?php else: ?>
                        <i class="fas fa-history fa-4x text-gray-300 mb-3"></i>
                        <p>No resolved reports found.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($reports as $report): ?>
                    <div class="card mb-4 <?= $status === 'pending' ? 'border-left-warning' : 'border-left-success' ?>">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-lg-9">
                                    <!-- Message details -->
                                    <h5 class="card-title">
                                        Reported Message 
                                        <span class="badge bg-dark ms-2"><?= $report['room_name'] ?></span>
                                        <?php if ($status === 'pending'): ?>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Resolved</span>
                                        <?php endif; ?>
                                    </h5>
                                    
                                    <div class="d-flex mb-3">
                                        <div class="flex-shrink-0 me-3">
                                            <div class="p-2 rounded-circle bg-primary text-white" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                                <?= strtoupper(substr($report['sender_first_name'], 0, 1)) ?>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between">
                                                <strong>
                                                    <a href="../users/view.php?id=<?= $report['sender_id'] ?>"><?= htmlspecialchars($report['sender_first_name'] . ' ' . $report['sender_last_name']) ?></a>
                                                </strong>
                                                <small class="text-muted"><?= date('M j, Y, g:i a', strtotime($report['message_date'])) ?></small>
                                            </div>
                                            <div class="p-3 mt-2 rounded bg-light">
                                                <?= nl2br(htmlspecialchars($report['message'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <strong>Reported By:</strong> 
                                        <a href="../users/view.php?id=<?= $report['reporter_id'] ?>">
                                            <?= htmlspecialchars($report['reporter_first_name'] . ' ' . $report['reporter_last_name']) ?>
                                        </a>
                                        on <?= date('M j, Y, g:i a', strtotime($report['reported_at'])) ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <strong>Reason for Report:</strong> 
                                        <div class="p-2 rounded bg-light"><?= htmlspecialchars($report['reason']) ?></div>
                                    </div>
                                    
                                    <?php if ($status === 'resolved'): ?>
                                        <div class="mb-3">
                                            <strong>Resolution:</strong>
                                            <div class="p-2 rounded bg-light">
                                                <?= htmlspecialchars($report['resolution_notes']) ?>
                                                <div class="mt-1 small text-muted">
                                                    Resolved by Admin ID: <?= $report['resolved_by'] ?> on 
                                                    <?= date('M j, Y, g:i a', strtotime($report['resolved_at'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-lg-3">
                                    <!-- Action buttons -->
                                    <?php if ($status === 'pending'): ?>
                                        <div class="card border-light mb-3">
                                            <div class="card-header bg-light">Actions</div>
                                            <div class="card-body">
                                                <form action="" method="POST">
                                                    <input type="hidden" name="report_id" value="<?= $report['id'] ?>">
                                                    <input type="hidden" name="action" value="delete_message">
                                                    <button type="submit" name="resolve_report" class="btn btn-danger btn-block mb-2">
                                                        <i class="fas fa-trash me-1"></i> Delete Message
                                                    </button>
                                                </form>
                                                
                                                <form action="" method="POST">
                                                    <input type="hidden" name="report_id" value="<?= $report['id'] ?>">
                                                    <input type="hidden" name="action" value="dismiss">
                                                    <button type="submit" name="resolve_report" class="btn btn-secondary btn-block">
                                                        <i class="fas fa-ban me-1"></i> Dismiss Report
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="card border-light">
                                        <div class="card-header bg-light">Links</div>
                                        <div class="card-body">
                                            <a href="view.php?id=<?= $report['room_id'] ?>" class="btn btn-info btn-block mb-2">
                                                <i class="fas fa-comments me-1"></i> View Chat Room
                                            </a>
                                            <a href="../users/view.php?id=<?= $report['sender_id'] ?>" class="btn btn-outline-primary btn-block mb-2">
                                                <i class="fas fa-user me-1"></i> View Message Sender
                                            </a>
                                            <a href="../users/view.php?id=<?= $report['reporter_id'] ?>" class="btn btn-outline-secondary btn-block">
                                                <i class="fas fa-user-shield me-1"></i> View Reporter
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-center mt-4">
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>&status=<?= $status ?>">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&status=<?= $status ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>&status=<?= $status ?>">
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

<?php
// Include footer
include_once '../includes/footer.php';
?>