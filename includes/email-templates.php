<?php
/**
 * Email Templates
 * File location: polyeduhub/includes/email-templates.php
 * 
 * This file contains all email template generation functions for PolyEduHub
 */

/**
 * Generate welcome email content
 * 
 * @param array $data User data including:
 *      - first_name: User's first name
 *      - last_name: User's last name
 *      - email: User's email address
 *      - role: User's role (student or admin)
 *      - verification_link: Email verification link (optional)
 * @return array Email content with subject and body
 */
function generateWelcomeEmail($data) {
    // Extract user data
    $first_name = $data['first_name'] ?? 'User';
    $last_name = $data['last_name'] ?? '';
    $full_name = $first_name . ' ' . $last_name;
    $email = $data['email'] ?? '';
    $role = $data['role'] ?? 'student';
    $verification_link = $data['verification_link'] ?? '#';
    
    // Email subject
    $subject = "Welcome to PolyEduHub - Your Educational Resource Hub";
    
    // Email body
    $body = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Welcome to PolyEduHub</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
            }
            .header {
                background-color: #4e73df;
                padding: 20px;
                text-align: center;
                color: white;
                border-radius: 5px 5px 0 0;
            }
            .content {
                background-color: #f8f9fc;
                padding: 20px;
                border-left: 1px solid #e3e6f0;
                border-right: 1px solid #e3e6f0;
            }
            .footer {
                background-color: #f8f9fc;
                padding: 20px;
                text-align: center;
                font-size: 12px;
                color: #666;
                border-radius: 0 0 5px 5px;
                border: 1px solid #e3e6f0;
            }
            .button {
                display: inline-block;
                padding: 10px 20px;
                background-color: #4e73df;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin: 20px 0;
            }
            .resources {
                background-color: #fff;
                border: 1px solid #e3e6f0;
                border-radius: 5px;
                padding: 15px;
                margin: 20px 0;
            }
            .resources h3 {
                margin-top: 0;
                color: #4e73df;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Welcome to PolyEduHub!</h1>
            </div>
            <div class="content">
                <p>Hello, <strong>' . htmlspecialchars($full_name) . '</strong>!</p>
                <p>Thank you for joining PolyEduHub, your centralized platform for educational resources and collaboration at Polytechnic Malaysia.</p>
                <p>Your account has been successfully created with the following details:</p>
                <ul>
                    <li><strong>Email:</strong> ' . htmlspecialchars($email) . '</li>
                    <li><strong>Role:</strong> ' . ucfirst(htmlspecialchars($role)) . '</li>
                </ul>';
    
    // Add verification link if provided
    if ($verification_link && $verification_link !== '#') {
        $body .= '
                <p>To verify your email address and activate your account, please click the button below:</p>
                <div style="text-align: center;">
                    <a href="' . htmlspecialchars($verification_link) . '" class="button">Verify Email Address</a>
                </div>';
    }
    
    $body .= '
                <div class="resources">
                    <h3>Get Started with PolyEduHub</h3>
                    <p>Here are some things you can do:</p>
                    <ul>
                        <li>Browse and download educational resources</li>
                        <li>Upload your own notes and study materials</li>
                        <li>Join chat rooms to collaborate with other students</li>
                        <li>Earn points and badges through active participation</li>
                    </ul>
                </div>
                
                <p>If you have any questions or need assistance, please don\'t hesitate to contact our support team at <a href="mailto:support@polyeduhub.edu.my">support@polyeduhub.edu.my</a>.</p>
                
                <p>Happy learning!</p>
                <p>The PolyEduHub Team</p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' PolyEduHub. All rights reserved.</p>
                <p>This email was sent to ' . htmlspecialchars($email) . ' because you registered for a PolyEduHub account.</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Return email content
    return [
        'subject' => $subject,
        'body' => $body
    ];
}

/**
 * Generate password reset email content
 * 
 * @param array $data User data including:
 *      - first_name: User's first name
 *      - email: User's email address
 *      - reset_link: Password reset link
 *      - expiry_time: Token expiry time (in minutes)
 * @return array Email content with subject and body
 */
function generatePasswordResetEmail($data) {
    // Extract user data
    $first_name = $data['first_name'] ?? 'User';
    $email = $data['email'] ?? '';
    $reset_link = $data['reset_link'] ?? '#';
    $expiry_time = $data['expiry_time'] ?? 60; // Default 60 minutes
    
    // Email subject
    $subject = "PolyEduHub - Password Reset Request";
    
    // Email body
    $body = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Password Reset Request</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
            }
            .header {
                background-color: #4e73df;
                padding: 20px;
                text-align: center;
                color: white;
                border-radius: 5px 5px 0 0;
            }
            .content {
                background-color: #f8f9fc;
                padding: 20px;
                border-left: 1px solid #e3e6f0;
                border-right: 1px solid #e3e6f0;
            }
            .footer {
                background-color: #f8f9fc;
                padding: 20px;
                text-align: center;
                font-size: 12px;
                color: #666;
                border-radius: 0 0 5px 5px;
                border: 1px solid #e3e6f0;
            }
            .button {
                display: inline-block;
                padding: 10px 20px;
                background-color: #4e73df;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin: 20px 0;
            }
            .alert {
                background-color: #fff3cd;
                color: #856404;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
                border: 1px solid #ffeeba;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Password Reset Request</h1>
            </div>
            <div class="content">
                <p>Hello, <strong>' . htmlspecialchars($first_name) . '</strong>!</p>
                <p>We received a request to reset your password for your PolyEduHub account. If you did not make this request, you can safely ignore this email.</p>
                
                <p>To reset your password, please click the button below:</p>
                
                <div style="text-align: center;">
                    <a href="' . htmlspecialchars($reset_link) . '" class="button">Reset My Password</a>
                </div>
                
                <div class="alert">
                    <p><strong>Important:</strong> This link will expire in ' . $expiry_time . ' minutes for security reasons.</p>
                </div>
                
                <p>If the button above doesn\'t work, you can copy and paste the following link into your browser:</p>
                <p style="word-break: break-all;">' . htmlspecialchars($reset_link) . '</p>
                
                <p>If you did not request a password reset, please ensure your account is secure by:</p>
                <ul>
                    <li>Checking that your password is strong and unique</li>
                    <li>Contacting our support team immediately at <a href="mailto:support@polyeduhub.edu.my">support@polyeduhub.edu.my</a></li>
                </ul>
                
                <p>Best regards,</p>
                <p>The PolyEduHub Team</p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' PolyEduHub. All rights reserved.</p>
                <p>This email was sent to ' . htmlspecialchars($email) . ' in response to a password reset request.</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Return email content
    return [
        'subject' => $subject,
        'body' => $body
    ];
}

/**
 * Generate notification email content
 * 
 * @param array $data Notification data including:
 *      - first_name: User's first name
 *      - email: User's email address
 *      - notification_type: Type of notification (e.g., 'resource_approved', 'new_comment', etc.)
 *      - notification_title: Title of the notification
 *      - notification_message: Main notification message
 *      - action_link: Link for the call-to-action button (optional)
 *      - action_text: Text for the call-to-action button (optional)
 * @return array Email content with subject and body
 */
function generateNotificationEmail($data) {
    // Extract notification data
    $first_name = $data['first_name'] ?? 'User';
    $email = $data['email'] ?? '';
    $notification_type = $data['notification_type'] ?? 'general';
    $notification_title = $data['notification_title'] ?? 'PolyEduHub Notification';
    $notification_message = $data['notification_message'] ?? 'You have a new notification.';
    $action_link = $data['action_link'] ?? '';
    $action_text = $data['action_text'] ?? 'View Details';
    
    // Set icons and colors based on notification type
    $icon = 'bell';
    $color = '#4e73df'; // Default blue
    
    switch ($notification_type) {
        case 'resource_approved':
            $icon = 'check-circle';
            $color = '#1cc88a'; // Green
            break;
        case 'resource_rejected':
            $icon = 'times-circle';
            $color = '#e74a3b'; // Red
            break;
        case 'new_comment':
            $icon = 'comment';
            $color = '#4e73df'; // Blue
            break;
        case 'new_rating':
            $icon = 'star';
            $color = '#f6c23e'; // Yellow
            break;
        case 'badge_earned':
            $icon = 'award';
            $color = '#f6c23e'; // Yellow
            break;
        case 'download_milestone':
            $icon = 'download';
            $color = '#36b9cc'; // Teal
            break;
    }
    
    // Email subject
    $subject = $notification_title;
    
    // Email body
    $body = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($notification_title) . '</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
            }
            .header {
                background-color: ' . $color . ';
                padding: 20px;
                text-align: center;
                color: white;
                border-radius: 5px 5px 0 0;
            }
            .content {
                background-color: #f8f9fc;
                padding: 20px;
                border-left: 1px solid #e3e6f0;
                border-right: 1px solid #e3e6f0;
            }
            .footer {
                background-color: #f8f9fc;
                padding: 20px;
                text-align: center;
                font-size: 12px;
                color: #666;
                border-radius: 0 0 5px 5px;
                border: 1px solid #e3e6f0;
            }
            .button {
                display: inline-block;
                padding: 10px 20px;
                background-color: ' . $color . ';
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin: 20px 0;
            }
            .notification-icon {
                font-size: 48px;
                margin-bottom: 10px;
            }
            .notification-box {
                background-color: #fff;
                border: 1px solid #e3e6f0;
                border-radius: 5px;
                padding: 20px;
                margin: 20px 0;
            }
            .settings-link {
                display: block;
                margin-top: 20px;
                color: #666;
                text-decoration: underline;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . htmlspecialchars($notification_title) . '</h1>
            </div>
            <div class="content">
                <p>Hello, <strong>' . htmlspecialchars($first_name) . '</strong>!</p>
                
                <div class="notification-box">
                    <div style="text-align: center;">
                        <div class="notification-icon">
                            <!-- Font Awesome icon reference - Replace with actual image in production -->
                            <img src="' . APP_URL . '/assets/img/icons/' . $icon . '.png" alt="Notification" width="48" height="48">
                        </div>
                    </div>
                    
                    <p>' . nl2br(htmlspecialchars($notification_message)) . '</p>';
    
    // Add action button if link is provided
    if (!empty($action_link)) {
        $body .= '
                    <div style="text-align: center;">
                        <a href="' . htmlspecialchars($action_link) . '" class="button">' . htmlspecialchars($action_text) . '</a>
                    </div>';
    }
    
    $body .= '
                </div>
                
                <p>Thank you for being an active member of the PolyEduHub community!</p>
                
                <p>Best regards,</p>
                <p>The PolyEduHub Team</p>
                
                <a href="' . APP_URL . '/student/notifications/settings.php" class="settings-link">Manage your notification settings</a>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' PolyEduHub. All rights reserved.</p>
                <p>This email was sent to ' . htmlspecialchars($email) . ' based on your notification preferences.</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Return email content
    return [
        'subject' => $subject,
        'body' => $body
    ];
}

/**
 * Helper function for creating notification emails for specific events
 * 
 * @param string $notification_type The type of notification
 * @param array $data User and notification data
 * @return array Email content with subject and body
 */
function createNotificationByType($notification_type, $data) {
    switch ($notification_type) {
        case 'resource_approved':
            $data['notification_type'] = 'resource_approved';
            $data['notification_title'] = 'Your Resource Was Approved';
            $data['notification_message'] = 'Great news! Your resource "' . ($data['resource_title'] ?? 'Resource') . '" has been approved by our administrators and is now available for other students to access.';
            $data['action_text'] = 'View Your Resource';
            break;
            
        case 'resource_rejected':
            $data['notification_type'] = 'resource_rejected';
            $data['notification_title'] = 'Resource Requires Revision';
            $data['notification_message'] = 'Your resource "' . ($data['resource_title'] ?? 'Resource') . '" requires some revisions before it can be approved. Here\'s the feedback from our administrators: ' . ($data['rejection_reason'] ?? 'Please review and resubmit.');
            $data['action_text'] = 'Edit Resource';
            break;
            
        case 'new_comment':
            $data['notification_type'] = 'new_comment';
            $data['notification_title'] = 'New Comment on Your Resource';
            $data['notification_message'] = ($data['commenter_name'] ?? 'Someone') . ' left a comment on your resource "' . ($data['resource_title'] ?? 'Resource') . '": "' . ($data['comment_text'] ?? '') . '"';
            $data['action_text'] = 'View Comment';
            break;
            
        case 'new_rating':
            $data['notification_type'] = 'new_rating';
            $data['notification_title'] = 'New Rating on Your Resource';
            $data['notification_message'] = 'Your resource "' . ($data['resource_title'] ?? 'Resource') . '" received a ' . ($data['rating'] ?? '5') . '-star rating!';
            $data['action_text'] = 'View Resource';
            break;
            
        case 'badge_earned':
            $data['notification_type'] = 'badge_earned';
            $data['notification_title'] = 'Congratulations! New Badge Earned';
            $data['notification_message'] = 'You\'ve earned the "' . ($data['badge_name'] ?? 'Achievement') . '" badge! ' . ($data['badge_description'] ?? 'Keep up the great work!');
            $data['action_text'] = 'View Your Badges';
            break;
            
        case 'download_milestone':
            $data['notification_type'] = 'download_milestone';
            $data['notification_title'] = 'Download Milestone Achieved';
            $data['notification_message'] = 'Congratulations! Your resource "' . ($data['resource_title'] ?? 'Resource') . '" has reached ' . ($data['download_count'] ?? '100') . ' downloads. Thank you for contributing valuable content to the PolyEduHub community!';
            $data['action_text'] = 'View Resource Stats';
            break;
            
        default:
            // Use provided data or defaults for general notifications
            break;
    }
    
    return generateNotificationEmail($data);
}

/**
 * Helper function to send emails
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email body
 * @param array $headers Additional headers
 * @return bool Whether the email was sent successfully
 */
function sendEmail($to, $subject, $body, $headers = []) {
    // Set default headers if not provided
    if (empty($headers)) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: PolyEduHub <noreply@polyeduhub.edu.my>',
            'Reply-To: support@polyeduhub.edu.my'
        ];
    }
    
    // Convert headers array to string
    $headerString = implode("\r\n", $headers);
    
    // In a real application, you would use PHPMailer, SwiftMailer, or another library
    // For simplicity in this example, we'll use the built-in mail() function
    $mailSent = mail($to, $subject, $body, $headerString);
    
    // Log the email sending attempt
    if ($mailSent) {
        error_log("Email sent successfully to: $to");
    } else {
        error_log("Failed to send email to: $to");
    }
    
    return $mailSent;
}

/**
 * Send welcome email to a new user
 * 
 * @param array $userData User data
 * @return bool Whether the email was sent successfully
 */
function sendWelcomeEmail($userData) {
    // Generate email content
    $emailContent = generateWelcomeEmail($userData);
    
    // Get recipient email
    $to = $userData['email'] ?? '';
    
    // Send the email
    return sendEmail($to, $emailContent['subject'], $emailContent['body']);
}

/**
 * Send password reset email
 * 
 * @param array $resetData Password reset data
 * @return bool Whether the email was sent successfully
 */
function sendPasswordResetEmail($resetData) {
    // Generate email content
    $emailContent = generatePasswordResetEmail($resetData);
    
    // Get recipient email
    $to = $resetData['email'] ?? '';
    
    // Send the email
    return sendEmail($to, $emailContent['subject'], $emailContent['body']);
}

/**
 * Send notification email
 * 
 * @param string $notificationType Type of notification
 * @param array $notificationData Notification data
 * @return bool Whether the email was sent successfully
 */
function sendNotificationEmail($notificationType, $notificationData) {
    // Generate email content based on notification type
    $emailContent = createNotificationByType($notificationType, $notificationData);
    
    // Get recipient email
    $to = $notificationData['email'] ?? '';
    
    // Send the email
    return sendEmail($to, $emailContent['subject'], $emailContent['body']);
}