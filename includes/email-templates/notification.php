<?php
/**
 * Notification Email Template
 * File location: polyeduhub/includes/email-templates/notification.php
 */

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