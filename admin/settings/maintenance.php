<?php
// File path: admin/settings/maintenance.php

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

// Initialize message variables
$success_message = '';
$error_message = '';

// Get system info
$system_info = [
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'mysql_version' => '',
    'db_size' => '',
    'upload_dir_size' => '',
    'total_users' => 0,
    'total_resources' => 0,
    'total_downloads' => 0
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check which form was submitted
    if (isset($_POST['toggle_maintenance'])) {
        // Toggle maintenance mode
        try {
            $pdo = getDbConnection();
            
            // Get current maintenance mode status
            $stmt = $pdo->prepare("SELECT `value` FROM system_settings WHERE `key` = 'maintenance_mode'");
            $stmt->execute();
            $current_status = $stmt->fetchColumn();
            
            // Toggle status
            $new_status = $current_status ? 0 : 1;
            
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (`key`, `value`) 
                VALUES ('maintenance_mode', ?) 
                ON DUPLICATE KEY UPDATE `value` = ?
            ");
            $stmt->execute([$new_status, $new_status]);
            
            // Log action
            $action = $new_status ? 'Enabled maintenance mode' : 'Disabled maintenance mode';
            logAdminAction($admin_id, $action, $action);
            
            $success_message = 'Maintenance mode ' . ($new_status ? 'enabled' : 'disabled') . ' successfully';
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    } elseif (isset($_POST['clear_cache'])) {
        // Clear system cache
        $cache_dirs = [
            '../../cache/',
            '../../temp/'
        ];
        
        $cleared = false;
        foreach ($cache_dirs as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                        $cleared = true;
                    }
                }
            }
        }
        
        if ($cleared) {
            logAdminAction($admin_id, 'Cleared system cache', 'System cache cleared');
            $success_message = 'System cache cleared successfully';
        } else {
            $success_message = 'No cache files found to clear';
        }
    } elseif (isset($_POST['optimize_db'])) {
        // Optimize database tables
        try {
            $pdo = getDbConnection();
            
            // Get all tables
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Optimize each table
            $optimized_tables = [];
            foreach ($tables as $table) {
                $stmt = $pdo->prepare("OPTIMIZE TABLE `$table`");
                $stmt->execute();
                $optimized_tables[] = $table;
            }
            
            logAdminAction($admin_id, 'Optimized database', 'Optimized ' . count($optimized_tables) . ' database tables');
            $success_message = count($optimized_tables) . ' database tables optimized successfully';
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    } elseif (isset($_POST['backup_db'])) {
        // Create database backup
        try {
            $backup_dir = '../../backups/';
            
            // Create backup directory if it doesn't exist
            if (!is_dir($backup_dir)) {
                mkdir($backup_dir, 0755, true);
            }
            
            // Generate backup filename
            $backup_file = $backup_dir . 'db_backup_' . date('Y-m-d_H-i-s') . '.sql';
            
            // Get database credentials from config
            $db_host = DB_HOST;
            $db_name = DB_NAME;
            $db_user = DB_USER;
            $db_pass = DB_PASS;
            
            // Create backup command
            $command = "mysqldump --opt --host=$db_host --user=$db_user";
            if (!empty($db_pass)) {
                $command .= " --password=$db_pass";
            }
            $command .= " $db_name > $backup_file";
            
            // Execute command
            $output = [];
            $return_var = 0;
            exec($command, $output, $return_var);
            
            if ($return_var === 0 && file_exists($backup_file)) {
                // Compress backup file
                $zip = new ZipArchive();
                $zip_file = $backup_file . '.zip';
                
                if ($zip->open($zip_file, ZipArchive::CREATE) === TRUE) {
                    $zip->addFile($backup_file, basename($backup_file));
                    $zip->close();
                    
                    // Remove original SQL file
                    @unlink($backup_file);
                    
                    logAdminAction($admin_id, 'Created database backup', 'Database backup created: ' . basename($zip_file));
                    $success_message = 'Database backup created successfully: ' . basename($zip_file);
                } else {
                    $success_message = 'Database backup created successfully: ' . basename($backup_file);
                }
            } else {
                $error_message = 'Failed to create database backup. Check server permissions.';
            }
        } catch (Exception $e) {
            $error_message = 'Error: ' . $e->getMessage();
        }
    } elseif (isset($_POST['clear_logs'])) {
        // Clear system logs
        try {
            $pdo = getDbConnection();
            
            // Check if logs table exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'admin_logs'");
            if ($stmt->rowCount() > 0) {
                // Clear logs older than 30 days
                $stmt = $pdo->prepare("DELETE FROM admin_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
                $stmt->execute();
                $cleared_rows = $stmt->rowCount();
                
                logAdminAction($admin_id, 'Cleared system logs', "Cleared $cleared_rows log entries older than 30 days");
                $success_message = "$cleared_rows log entries cleared successfully";
            } else {
                $success_message = 'No log table found to clear';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get current maintenance mode status
try {
    $pdo = getDbConnection();
    
    // Check if settings table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("SELECT `value` FROM system_settings WHERE `key` = 'maintenance_mode'");
        $stmt->execute();
        $maintenance_mode = (bool)$stmt->fetchColumn();
    } else {
        $maintenance_mode = false;
    }
    
    // Get MySQL version
    $stmt = $pdo->query("SELECT VERSION()");
    $system_info['mysql_version'] = $stmt->fetchColumn();
    
    // Get database size
    $stmt = $pdo->query("
        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size
        FROM information_schema.TABLES
        WHERE table_schema = '" . DB_NAME . "'
    ");
    $system_info['db_size'] = $stmt->fetchColumn() . ' MB';
    
    // Get total users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $system_info['total_users'] = $stmt->fetchColumn();
    
    // Get total resources
    $stmt = $pdo->query("SELECT COUNT(*) FROM resources");
    $system_info['total_resources'] = $stmt->fetchColumn();
    
    // Get total downloads
    $stmt = $pdo->query("SELECT SUM(download_count) FROM resources");
    $system_info['total_downloads'] = $stmt->fetchColumn() ?: 0;
    
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
    $maintenance_mode = false;
}

// Calculate upload directory size
$upload_dir = '../../resources/';
$system_info['upload_dir_size'] = '0 MB';

if (is_dir($upload_dir)) {
    $size = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($upload_dir)) as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }
    $system_info['upload_dir_size'] = round($size / 1024 / 1024, 2) . ' MB';
}

// Get available backups
$backups = [];
$backup_dir = '../../backups/';

if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && stripos($file, 'backup') !== false) {
            $backups[] = [
                'name' => $file,
                'size' => round(filesize($backup_dir . $file) / 1024 / 1024, 2) . ' MB',
                'date' => date('Y-m-d H:i:s', filemtime($backup_dir . $file))
            ];
        }
    }
    
    // Sort backups by date (newest first)
    usort($backups, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}

// Set page title and nested path variable
$page_title = "System Maintenance";
$nested = true;

// Include header
include_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">System Maintenance</h1>
        <a href="<?= isset($nested) ? '../' : '' ?>dashboard.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back to Dashboard
        </a>
    </div>
    
    <?php if (!empty($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $success_message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= $error_message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <!-- Maintenance Mode Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Maintenance Mode</h6>
        </div>
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <p>When maintenance mode is enabled, only administrators can access the site. Regular users will see a maintenance message.</p>
                    <p class="mb-0">Use this when performing updates, database maintenance, or other operations that might temporarily affect site functionality.</p>
                </div>
                <div class="col-md-4 text-center">
                    <div class="mb-3">
                        <span class="badge bg-<?= $maintenance_mode ? 'danger' : 'success' ?> p-2">
                            <i class="fas fa-<?= $maintenance_mode ? 'tools' : 'check-circle' ?> me-1"></i>
                            <?= $maintenance_mode ? 'Maintenance Mode Enabled' : 'Site is Online' ?>
                        </span>
                    </div>
                    <form method="POST" action="">
                        <button type="submit" name="toggle_maintenance" class="btn btn-<?= $maintenance_mode ? 'success' : 'warning' ?>">
                            <i class="fas fa-<?= $maintenance_mode ? 'power-off' : 'tools' ?> me-1"></i>
                            <?= $maintenance_mode ? 'Disable Maintenance Mode' : 'Enable Maintenance Mode' ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- System Information Card -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">System Information</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <th scope="row" width="40%">PHP Version</th>
                                    <td><?= $system_info['php_version'] ?></td>
                                </tr>
                                <tr>
                                    <th scope="row">Web Server</th>
                                    <td><?= $system_info['server_software'] ?></td>
                                </tr>
                                <tr>
                                    <th scope="row">MySQL Version</th>
                                    <td><?= $system_info['mysql_version'] ?></td>
                                </tr>
                                <tr>
                                    <th scope="row">Database Size</th>
                                    <td><?= $system_info['db_size'] ?></td>
                                </tr>
                                <tr>
                                    <th scope="row">Upload Directory Size</th>
                                    <td><?= $system_info['upload_dir_size'] ?></td>
                                </tr>
                                <tr>
                                    <th scope="row">Total Users</th>
                                    <td><?= number_format($system_info['total_users']) ?></td>
                                </tr>
                                <tr>
                                    <th scope="row">Total Resources</th>
                                    <td><?= number_format($system_info['total_resources']) ?></td>
                                </tr>
                                <tr>
                                    <th scope="row">Total Downloads</th>
                                    <td><?= number_format($system_info['total_downloads']) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Maintenance Tools Card -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Maintenance Tools</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <form method="POST" action="">
                                <button type="submit" name="clear_cache" class="btn btn-block btn-outline-primary w-100">
                                    <i class="fas fa-broom me-1"></i> Clear System Cache
                                </button>
                            </form>
                        </div>
                        <div class="col-md-6 mb-3">
                            <form method="POST" action="">
                                <button type="submit" name="optimize_db" class="btn btn-block btn-outline-primary w-100">
                                    <i class="fas fa-database me-1"></i> Optimize Database
                                </button>
                            </form>
                        </div>
                        <div class="col-md-6 mb-3">
                            <form method="POST" action="">
                                <button type="submit" name="backup_db" class="btn btn-block btn-outline-primary w-100">
                                    <i class="fas fa-download me-1"></i> Backup Database
                                </button>
                            </form>
                        </div>
                        <div class="col-md-6 mb-3">
                            <form method="POST" action="">
                                <button type="submit" name="clear_logs" class="btn btn-block btn-outline-primary w-100" 
                                       onclick="return confirm('Are you sure you want to clear logs older than 30 days?');">
                                    <i class="fas fa-eraser me-1"></i> Clear Old Logs
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> 
                            These tools help maintain system performance. Use them periodically for optimal operation.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Database Backups Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Database Backups</h6>
        </div>
        <div class="card-body">
            <?php if (empty($backups)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-database fa-3x text-gray-300 mb-3"></i>
                    <p>No database backups found.</p>
                    <p>Use the "Backup Database" tool to create a new backup.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Backup File</th>
                                <th>Size</th>
                                <th>Date Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $backup): ?>
                                <tr>
                                    <td><?= htmlspecialchars($backup['name']) ?></td>
                                    <td><?= $backup['size'] ?></td>
                                    <td><?= $backup['date'] ?></td>
                                    <td>
                                        <a href="../../backups/<?= urlencode($backup['name']) ?>" class="btn btn-sm btn-primary" download>
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                        <a href="delete_backup.php?file=<?= urlencode($backup['name']) ?>" class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Are you sure you want to delete this backup?');">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>