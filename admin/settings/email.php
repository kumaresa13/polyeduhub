<?php
// File path: admin/settings/email.php

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
    // Determine which form was submitted
    if (isset($_POST['save_email_settings'])) {
        // Email settings form
        $email_from = filter_var($_POST['email_from'], FILTER_SANITIZE_EMAIL);
        $email_from_name = filter_var($_POST['email_from_name'], FILTER_SANITIZE_STRING);
        $smtp_host = filter_var($_POST['smtp_host'], FILTER_SANITIZE_STRING);
        $smtp_port = filter_var($_POST['smtp_port'], FILTER_SANITIZE_NUMBER_INT);
        $smtp_secure = filter_var($_POST['smtp_secure'], FILTER_SANITIZE_STRING);
        $smtp_auth = isset($_POST['smtp_auth']) ? 1 : 0;
        $smtp_username = filter_var($_POST['smtp_username'], FILTER_SANITIZE_STRING);
        $smtp_password = $_POST['smtp_password']; // Don't sanitize password
        
        // Only update password if a new one is provided
        $update_password = !empty($smtp_password);
        
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
                'email_from' => $email_from,
                'email_from_name' => $email_from_name,
                'smtp_host' => $smtp_host,
                'smtp_port' => $smtp_port,
                'smtp_secure' => $smtp_secure,
                'smtp_auth' => $smtp_auth
            ];
            
            if ($update_password) {
                $settings['smtp_password'] = $smtp_password;
            }
            
            $settings['smtp_username'] = $smtp_username;
            
            // Start transaction
            $pdo->beginTransaction();
            
            foreach ($settings as $key => $value) {
                $stmt = $pdo->prepare("INSERT INTO system_settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
                $stmt->execute([$key, $value, $value]);
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Log action
            logAdminAction($admin_id, 'Updated email settings', 'Email configuration settings updated');
            
            $success_message = 'Email settings updated successfully';
        } catch (PDOException $e) {
            // Rollback on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = 'Database error: ' . $e->getMessage();
        }
    } elseif (isset($_POST['save_template'])) {
        // Email template form
        $template_id = filter_var($_POST['template_id'], FILTER_SANITIZE_STRING);
        $template_subject = filter_var($_POST['template_subject'], FILTER_SANITIZE_STRING);
        $template_content = $_POST['template_content']; // Don't sanitize HTML content
        
        try {
            $pdo = getDbConnection();
            
            // Check if email_templates table exists, create if not
            $stmt = $pdo->query("SHOW TABLES LIKE 'email_templates'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("
                    CREATE TABLE email_templates (
                        `id` VARCHAR(50) PRIMARY KEY,
                        `subject` VARCHAR(255) NOT NULL,
                        `content` TEXT NOT NULL,
                        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )
                ");
            }
            
            // Update or insert template
            $stmt = $pdo->prepare("
                INSERT INTO email_templates (id, subject, content) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE subject = ?, content = ?
            ");
            $stmt->execute([$template_id, $template_subject, $template_content, $template_subject, $template_content]);
            
            // Log action
            logAdminAction($admin_id, 'Updated email template', "Email template updated: $template_id");
            
            $success_message = 'Email template updated successfully';
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    } elseif (isset($_POST['send_test_email'])) {
        // Test email form
        $test_email = filter_var($_POST['test_email'], FILTER_SANITIZE_EMAIL);
        
        // Simple email sending test using PHP mail function
        $subject = 'Test Email from ' . APP_NAME;
        $message = "This is a test email from your educational platform.\n\n";
        $message .= "If you received this email, your email settings are configured correctly.\n\n";
        $message .= "Time: " . date('Y-m-d H:i:s');
        
        $headers = 'From: ' . $settings['email_from_name'] . ' <' . $settings['email_from'] . '>';
        
        if (mail($test_email, $subject, $message, $headers)) {
            $success_message = 'Test email sent successfully to ' . $test_email;
            
            // Log action
            logAdminAction($admin_id, 'Sent test email', "Test email sent to: $test_email");
        } else {
            $error_message = 'Failed to send test email. Please check your email settings.';
        }
    }
}

// Get current email settings
try {
    $pdo = getDbConnection();
    
    // Check if settings table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SELECT * FROM system_settings WHERE `key` LIKE 'email_%' OR `key` LIKE 'smtp_%'");
        $settings_rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Set default values if not in database
        $settings = [
            'email_from' => $settings_rows['email_from'] ?? 'noreply@yourdomain.com',
            'email_from_name' => $settings_rows['email_from_name'] ?? APP_NAME,
            'smtp_host' => $settings_rows['smtp_host'] ?? '',
            'smtp_port' => $settings_rows['smtp_port'] ?? '587',
            'smtp_secure' => $settings_rows['smtp_secure'] ?? 'tls',
            'smtp_auth' => $settings_rows['smtp_auth'] ?? '1',
            'smtp_username' => $settings_rows['smtp_username'] ?? '',
            'smtp_password' => $settings_rows['smtp_password'] ?? ''
        ];
    } else {
        // Default settings
        $settings = [
            'email_from' => 'noreply@yourdomain.com',
            'email_from_name' => APP_NAME,
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_secure' => 'tls',
            'smtp_auth' => '1',
            'smtp_username' => '',
            'smtp_password' => ''
        ];
    }
    
    // Get email templates
    $email_templates = [];
    
    // Check if email_templates table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'email_templates'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SELECT * FROM email_templates ORDER BY id");
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($templates as $template) {
            $email_templates[$template['id']] = $template;
        }
    }
    
    // Define default templates if they don't exist
    $default_templates = [
        'welcome' => [
            'id' => 'welcome',
            'subject' => 'Welcome to ' . APP_NAME,
            'content' => '<p>Dear {{first_name}},</p>
<p>Welcome to ' . APP_NAME . '! We\'re excited to have you as part of our community.</p>
<p>Your account has been created successfully. You can now log in and start sharing educational resources with fellow students.</p>
<p>If you have any questions, please don\'t hesitate to contact us.</p>
<p>Best regards,<br>The ' . APP_NAME . ' Team</p>'
        ],
        'password_reset' => [
            'id' => 'password_reset',
            'subject' => 'Password Reset Request',
            'content' => '<p>Dear {{first_name}},</p>
<p>We received a request to reset your password. Please click the link below to set a new password:</p>
<p><a href="{{reset_link}}">Reset Password</a></p>
<p>If you didn\'t request this password reset, you can safely ignore this email.</p>
<p>This link will expire in 24 hours.</p>
<p>Best regards,<br>The ' . APP_NAME . ' Team</p>'
        ],
        'resource_approved' => [
            'id' => 'resource_approved',
            'subject' => 'Your Resource Has Been Approved',
            'content' => '<p>Dear {{first_name}},</p>
<p>Good news! Your resource "{{resource_title}}" has been approved by our administrators.</p>
<p>Your contribution is now available to all students on the platform. Thank you for sharing your knowledge!</p>
<p><a href="{{action_link}}">View Your Resource</a></p>
<p>Best regards,<br>The ' . APP_NAME . ' Team</p>'
        ],
        'resource_rejected' => [
            'id' => 'resource_rejected',
            'subject' => 'Your Resource Needs Revision',
            'content' => '<p>Dear {{first_name}},</p>
<p>Your resource "{{resource_title}}" has been reviewed but needs some revisions before it can be approved.</p>
<p>Here\'s the feedback from our review team:</p>
<p><strong>{{rejection_reason}}</strong></p>
<p>Please make the necessary changes and resubmit your resource. If you have any questions about the feedback, please contact us.</p>
<p><a href="{{action_link}}">Edit Your Resource</a></p>
<p>Best regards,<br>The ' . APP_NAME . ' Team</p>'
        ],
        'badge_earned' => [
            'id' => 'badge_earned',
            'subject' => 'Congratulations! You\'ve Earned a New Badge',
            'content' => '<p>Dear {{first_name}},</p>
<p>Congratulations! You\'ve earned the "{{badge_name}}" badge.</p>
<p>{{badge_description}}</p>
<p>Keep up the great work and continue contributing to our educational community!</p>
<p><a href="{{profile_link}}">View Your Profile</a></p>
<p>Best regards,<br>The ' . APP_NAME . ' Team</p>'
        ]
    ];
    
    // Merge default templates with existing ones
    foreach ($default_templates as $id => $template) {
        if (!isset($email_templates[$id])) {
            $email_templates[$id] = $template;
        }
    }
    
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
    
    // Default settings in case of error
    $settings = [
        'email_from' => 'noreply@yourdomain.com',
        'email_from_name' => APP_NAME,
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_secure' => 'tls',
        'smtp_auth' => '1',
        'smtp_username' => '',
        'smtp_password' => ''
    ];
    
    $email_templates = $default_templates ?? [];
}

// Set page title and nested path variable
$page_title = "Email Settings";
$nested = true;

// Include header
include_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Email Settings</h1>
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
    
    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" id="emailTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab" aria-controls="settings" aria-selected="true">
                <i class="fas fa-cog me-1"></i> Settings
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="templates-tab" data-bs-toggle="tab" data-bs-target="#templates" type="button" role="tab" aria-controls="templates" aria-selected="false">
                <i class="fas fa-file-alt me-1"></i> Email Templates
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="test-tab" data-bs-toggle="tab" data-bs-target="#test" type="button" role="tab" aria-controls="test" aria-selected="false">
                <i class="fas fa-paper-plane me-1"></i> Test Email
            </button>
        </li>
    </ul>
    
    <div class="tab-content" id="emailTabsContent">
        <!-- Email Settings Tab -->
        <div class="tab-pane fade show active" id="settings" role="tabpanel" aria-labelledby="settings-tab">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Email Configuration</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <!-- Send From Settings -->
                        <div class="mb-4">
                            <h5 class="text-gray-800">Send From</h5>
                            <hr>
                            <div class="mb-3 row">
                                <label for="email_from" class="col-sm-3 col-form-label">From Email Address</label>
                                <div class="col-sm-9">
                                    <input type="email" class="form-control" id="email_from" name="email_from" 
                                           value="<?= htmlspecialchars($settings['email_from']) ?>" required>
                                    <div class="form-text">The email address that emails will be sent from</div>
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label for="email_from_name" class="col-sm-3 col-form-label">From Name</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" id="email_from_name" name="email_from_name" 
                                           value="<?= htmlspecialchars($settings['email_from_name']) ?>" required>
                                    <div class="form-text">The name that will appear as the sender of emails</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- SMTP Settings -->
                        <div class="mb-4">
                            <h5 class="text-gray-800">SMTP Settings</h5>
                            <hr>
                            <div class="mb-3 row">
                                <label for="smtp_host" class="col-sm-3 col-form-label">SMTP Server</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                           value="<?= htmlspecialchars($settings['smtp_host']) ?>">
                                    <div class="form-text">The hostname of your SMTP server (e.g., smtp.gmail.com)</div>
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label for="smtp_port" class="col-sm-3 col-form-label">SMTP Port</label>
                                <div class="col-sm-9">
                                    <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                           value="<?= htmlspecialchars($settings['smtp_port']) ?>">
                                    <div class="form-text">Common ports: 25, 465 (SSL), 587 (TLS)</div>
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label for="smtp_secure" class="col-sm-3 col-form-label">Security</label>
                                <div class="col-sm-9">
                                    <select class="form-select" id="smtp_secure" name="smtp_secure">
                                        <option value="" <?= $settings['smtp_secure'] === '' ? 'selected' : '' ?>>None</option>
                                        <option value="ssl" <?= $settings['smtp_secure'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                        <option value="tls" <?= $settings['smtp_secure'] === 'tls' ? 'selected' : '' ?>>TLS</option>
                                    </select>
                                    <div class="form-text">Connection security type</div>
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label class="col-sm-3 col-form-label">Authentication</label>
                                <div class="col-sm-9">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="smtp_auth" name="smtp_auth" 
                                               <?= $settings['smtp_auth'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="smtp_auth">Use SMTP authentication</label>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label for="smtp_username" class="col-sm-3 col-form-label">Username</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" id="smtp_username" name="smtp_username" 
                                           value="<?= htmlspecialchars($settings['smtp_username']) ?>">
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label for="smtp_password" class="col-sm-3 col-form-label">Password</label>
                                <div class="col-sm-9">
                                    <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                           placeholder="<?= empty($settings['smtp_password']) ? '' : '••••••••••••' ?>">
                                    <div class="form-text">Leave blank to keep existing password</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Save Button -->
                        <div class="text-center">
                            <button type="submit" name="save_email_settings" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Email Templates Tab -->
        <div class="tab-pane fade" id="templates" role="tabpanel" aria-labelledby="templates-tab">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Email Templates</h6>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <label for="templateSelector" class="form-label">Select Template to Edit</label>
                        <select class="form-select" id="templateSelector">
                            <?php foreach ($email_templates as $id => $template): ?>
                                <option value="<?= htmlspecialchars($id) ?>"><?= ucwords(str_replace('_', ' ', $id)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php foreach ($email_templates as $id => $template): ?>
                        <div id="template-<?= htmlspecialchars($id) ?>" class="template-editor" style="display: none;">
                            <form method="POST" action="">
                                <input type="hidden" name="template_id" value="<?= htmlspecialchars($id) ?>">
                                
                                <div class="mb-3">
                                    <label for="template_subject_<?= htmlspecialchars($id) ?>" class="form-label">Email Subject</label>
                                    <input type="text" class="form-control" id="template_subject_<?= htmlspecialchars($id) ?>" name="template_subject" 
                                           value="<?= htmlspecialchars($template['subject']) ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="template_content_<?= htmlspecialchars($id) ?>" class="form-label">Email Content</label>
                                    <textarea class="form-control" id="template_content_<?= htmlspecialchars($id) ?>" name="template_content" rows="15" required><?= htmlspecialchars($template['content']) ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Available Variables</label>
                                    <div class="bg-light p-3 border rounded">
                                        <?php
                                        // Define available variables for each template
                                        $variables = [
                                            'welcome' => [
                                                '{{first_name}}' => 'User\'s first name',
                                                '{{last_name}}' => 'User\'s last name',
                                                '{{email}}' => 'User\'s email address',
                                                '{{login_link}}' => 'Link to login page'
                                            ],
                                            'password_reset' => [
                                                '{{first_name}}' => 'User\'s first name',
                                                '{{reset_link}}' => 'Password reset link',
                                                '{{expiry_time}}' => 'Link expiry time'
                                            ],
                                            'resource_approved' => [
                                                '{{first_name}}' => 'User\'s first name',
                                                '{{resource_title}}' => 'Resource title',
                                                '{{action_link}}' => 'Link to view the resource'
                                            ],
                                            'resource_rejected' => [
                                                '{{first_name}}' => 'User\'s first name',
                                                '{{resource_title}}' => 'Resource title',
                                                '{{rejection_reason}}' => 'Reason for rejection',
                                                '{{action_link}}' => 'Link to edit the resource'
                                            ],
                                            'badge_earned' => [
                                                '{{first_name}}' => 'User\'s first name',
                                                '{{badge_name}}' => 'Badge name',
                                                '{{badge_description}}' => 'Badge description',
                                                '{{profile_link}}' => 'Link to user profile'
                                            ]
                                        ];
                                        
                                        // Display variables
                                        if (isset($variables[$id])) {
                                            echo '<ul class="mb-0">';
                                            foreach ($variables[$id] as $var => $desc) {
                                                echo '<li><code>' . $var . '</code> - ' . $desc . '</li>';
                                            }
                                            echo '</ul>';
                                        } else {
                                            echo '<p class="mb-0">No specific variables available for this template.</p>';
                                        }
                                        ?>
                                    </div>
                                </div>
                                
                                <div class="text-center">
                                    <button type="submit" name="save_template" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Save Template
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Test Email Tab -->
        <div class="tab-pane fade" id="test" role="tabpanel" aria-labelledby="test-tab">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Send Test Email</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="test_email" class="form-label">Recipient Email</label>
                            <input type="email" class="form-control" id="test_email" name="test_email" required>
                            <div class="form-text">Enter your email address to receive a test email</div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> 
                            This will send a test email to the specified address using your current email configuration.
                            Use this to verify that your email settings are working correctly.
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" name="send_test_email" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-1"></i> Send Test Email
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Template selector
    const templateSelector = document.getElementById('templateSelector');
    if (templateSelector) {
        // Show first template by default
        const firstTemplateId = templateSelector.options[0].value;
        document.getElementById('template-' + firstTemplateId).style.display = 'block';
        
        // Handle template selection change
        templateSelector.addEventListener('change', function() {
            // Hide all templates
            document.querySelectorAll('.template-editor').forEach(editor => {
                editor.style.display = 'none';
            });
            
            // Show selected template
            const templateId = this.value;
            document.getElementById('template-' + templateId).style.display = 'block';
        });
    }
    
    // Keep active tab after form submission
    const activeTabId = localStorage.getItem('emailActiveTab');
    if (activeTabId) {
        const tab = document.querySelector(activeTabId);
        if (tab) {
            const tabInstance = new bootstrap.Tab(tab);
            tabInstance.show();
        }
    }
    
    // Store active tab on tab change
    const tabs = document.querySelectorAll('button[data-bs-toggle="tab"]');
    tabs.forEach(tab => {
        tab.addEventListener('shown.bs.tab', function (event) {
            localStorage.setItem('emailActiveTab', '#' + event.target.id);
        });
    });
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>