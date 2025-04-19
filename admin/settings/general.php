<?php
// File path: admin/settings/general.php

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $site_name = filter_var($_POST['site_name'], FILTER_SANITIZE_STRING);
    $site_description = filter_var($_POST['site_description'], FILTER_SANITIZE_STRING);
    $max_upload_size = intval($_POST['max_upload_size']) * 1024 * 1024; // Convert MB to bytes
    $allowed_file_types = isset($_POST['allowed_file_types']) ? $_POST['allowed_file_types'] : [];
    $enable_registration = isset($_POST['enable_registration']) ? 1 : 0;
    $require_approval = isset($_POST['require_approval']) ? 1 : 0;
    $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
    
    // Format allowed file types
    $allowed_extensions = implode(',', $allowed_file_types);
    
    try {
        $pdo = getDbConnection();
        
        // Check if settings table exists, create if not
        $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("
                CREATE TABLE system_settings (
                    `key` VARCHAR(50) PRIMARY KEY,
                    `value` TEXT,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
        }
        
        // Update settings
        $settings = [
            'site_name' => $site_name,
            'site_description' => $site_description,
            'max_upload_size' => $max_upload_size,
            'allowed_extensions' => $allowed_extensions,
            'enable_registration' => $enable_registration,
            'require_approval' => $require_approval,
            'maintenance_mode' => $maintenance_mode
        ];
        
        // Start transaction
        $pdo->beginTransaction();
        
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO system_settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
            $stmt->execute([$key, $value, $value]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Update constants in config file
        $config_file = '../../includes/config.php';
        $config_content = file_get_contents($config_file);
        
        // Replace upload size and allowed extensions
        $config_content = preg_replace(
            "/define\('UPLOAD_MAX_SIZE', \d+\);/", 
            "define('UPLOAD_MAX_SIZE', {$max_upload_size});", 
            $config_content
        );
        
        $config_content = preg_replace(
            "/define\('ALLOWED_EXTENSIONS', \[.*?\]\);/s", 
            "define('ALLOWED_EXTENSIONS', ['" . str_replace(',', "','", $allowed_extensions) . "']);", 
            $config_content
        );
        
        file_put_contents($config_file, $config_content);
        
        // Log action
        logAdminAction($admin_id, 'Updated system settings', 'General settings updated');
        
        $success_message = 'Settings updated successfully';
    } catch (PDOException $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = 'Database error: ' . $e->getMessage();
    } catch (Exception $e) {
        $error_message = 'Error: ' . $e->getMessage();
    }
}

// Get current settings
try {
    $pdo = getDbConnection();
    
    // Check if settings table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SELECT * FROM system_settings");
        $settings_rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Set default values if not in database
        $settings = [
            'site_name' => $settings_rows['site_name'] ?? APP_NAME,
            'site_description' => $settings_rows['site_description'] ?? 'Educational Resource Sharing Platform',
            'max_upload_size' => $settings_rows['max_upload_size'] ?? UPLOAD_MAX_SIZE,
            'allowed_extensions' => $settings_rows['allowed_extensions'] ?? implode(',', ALLOWED_EXTENSIONS),
            'enable_registration' => $settings_rows['enable_registration'] ?? 1,
            'require_approval' => $settings_rows['require_approval'] ?? 1,
            'maintenance_mode' => $settings_rows['maintenance_mode'] ?? 0
        ];
    } else {
        // Default settings
        $settings = [
            'site_name' => APP_NAME,
            'site_description' => 'Educational Resource Sharing Platform',
            'max_upload_size' => UPLOAD_MAX_SIZE,
            'allowed_extensions' => implode(',', ALLOWED_EXTENSIONS),
            'enable_registration' => 1,
            'require_approval' => 1,
            'maintenance_mode' => 0
        ];
    }
    
    // Convert max_upload_size from bytes to MB for display
    $settings['max_upload_size_mb'] = $settings['max_upload_size'] / (1024 * 1024);
    
    // Convert allowed_extensions string to array
    $allowed_extensions_array = explode(',', $settings['allowed_extensions']);
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
    
    // Default settings in case of error
    $settings = [
        'site_name' => APP_NAME,
        'site_description' => 'Educational Resource Sharing Platform',
        'max_upload_size_mb' => UPLOAD_MAX_SIZE / (1024 * 1024),
        'allowed_extensions' => implode(',', ALLOWED_EXTENSIONS),
        'enable_registration' => 1,
        'require_approval' => 1,
        'maintenance_mode' => 0
    ];
    $allowed_extensions_array = ALLOWED_EXTENSIONS;
}

// Common file types for resources
$file_types = [
    'pdf' => 'PDF Documents',
    'doc' => 'Word Documents (DOC)',
    'docx' => 'Word Documents (DOCX)',
    'ppt' => 'PowerPoint (PPT)',
    'pptx' => 'PowerPoint (PPTX)',
    'xls' => 'Excel Spreadsheets (XLS)',
    'xlsx' => 'Excel Spreadsheets (XLSX)',
    'txt' => 'Text Files',
    'zip' => 'ZIP Archives',
    'rar' => 'RAR Archives',
    'jpg' => 'JPEG Images',
    'jpeg' => 'JPEG Images',
    'png' => 'PNG Images',
    'gif' => 'GIF Images'
];

// Set page title and nested path variable
$page_title = "General Settings";
$nested = true;

// Include header
include_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">General Settings</h1>
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
    
    <!-- Settings Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">System Configuration</h6>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <!-- Site Information -->
                <div class="mb-4">
                    <h5 class="text-gray-800">Site Information</h5>
                    <hr>
                    <div class="mb-3 row">
                        <label for="site_name" class="col-sm-3 col-form-label">Site Name</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control" id="site_name" name="site_name" 
                                   value="<?= htmlspecialchars($settings['site_name']) ?>" required>
                            <div class="form-text">The name of your educational platform</div>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label for="site_description" class="col-sm-3 col-form-label">Site Description</label>
                        <div class="col-sm-9">
                            <textarea class="form-control" id="site_description" name="site_description" rows="2"><?= htmlspecialchars($settings['site_description']) ?></textarea>
                            <div class="form-text">A brief description of your platform's purpose</div>
                        </div>
                    </div>
                </div>
                
                <!-- Upload Settings -->
                <div class="mb-4">
                    <h5 class="text-gray-800">Upload Settings</h5>
                    <hr>
                    <div class="mb-3 row">
                        <label for="max_upload_size" class="col-sm-3 col-form-label">Maximum Upload Size (MB)</label>
                        <div class="col-sm-9">
                            <input type="number" class="form-control" id="max_upload_size" name="max_upload_size" 
                                   value="<?= htmlspecialchars($settings['max_upload_size_mb']) ?>" min="1" required>
                            <div class="form-text">Maximum file size for resource uploads in megabytes</div>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-3 col-form-label">Allowed File Types</label>
                        <div class="col-sm-9">
                            <div class="row">
                                <?php foreach ($file_types as $extension => $description): ?>
                                <div class="col-md-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="allowed_file_types[]" 
                                               value="<?= $extension ?>" id="file_<?= $extension ?>"
                                               <?= in_array($extension, $allowed_extensions_array) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="file_<?= $extension ?>">
                                            <?= $description ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-text">Select which file types students can upload as resources</div>
                        </div>
                    </div>
                </div>
                
                <!-- System Settings -->
                <div class="mb-4">
                    <h5 class="text-gray-800">System Settings</h5>
                    <hr>
                    <div class="mb-3 row">
                        <label class="col-sm-3 col-form-label">Registration</label>
                        <div class="col-sm-9">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="enable_registration" name="enable_registration" 
                                       <?= $settings['enable_registration'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="enable_registration">Allow new user registrations</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-3 col-form-label">Resource Approval</label>
                        <div class="col-sm-9">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="require_approval" name="require_approval" 
                                       <?= $settings['require_approval'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="require_approval">Require admin approval for uploaded resources</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-3 col-form-label">Maintenance Mode</label>
                        <div class="col-sm-9">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                       <?= $settings['maintenance_mode'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="maintenance_mode">Enable maintenance mode</label>
                                <div class="form-text">When enabled, only administrators can access the site</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Save Button -->
                <div class="text-center">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Server Information Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Server Information</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table">
                        <tbody>
                            <tr>
                                <th scope="row">PHP Version</th>
                                <td><?= phpversion() ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Server Software</th>
                                <td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Database Type</th>
                                <td>MySQL</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table">
                        <tbody>
                            <tr>
                                <th scope="row">Max Upload Size (PHP)</th>
                                <td><?= ini_get('upload_max_filesize') ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Max Post Size</th>
                                <td><?= ini_get('post_max_size') ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Memory Limit</th>
                                <td><?= ini_get('memory_limit') ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>