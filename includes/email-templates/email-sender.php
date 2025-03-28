<?php
/**
 * Email Sender
 * 
 * This file contains functions for sending various types of emails from the PolyEduHub platform.
 */

/**
 * Send a welcome email to a newly registered user
 * 
 * @param array $user_data User data containing 'first_name', 'last_name', 'email', 'role'
 * @return bool True if email was sent successfully, false otherwise
 */
function sendWelcomeEmail($user_data) {
    // Check if all required fields are present
    if (!isset($user_data['first_name']) || !isset($user_data['last_name']) || 
        !isset($user_data['email']) || !isset($user_data['role'])) {
        error_log("Incomplete user data for welcome email");
        return false;
    }
    
    // Email subject
    $subject = "Welcome to PolyEduHub!";
    
    // Prepare the email content based on user role
    if ($user_data['role'] == 'student') {
        $content = studentWelcomeEmailTemplate($user_data);
    } else if ($user_data['role'] == 'admin') {
        $content = adminWelcomeEmailTemplate($user_data);
    } else {
        error_log("Unknown role for welcome email: " . $user_data['role']);
        return false;
    }
    
    // Try to send the email
    return sendEmail($user_data['email'], $subject, $content);
}

/**
 * Send a password reset email
 * 
 * @param array $reset_data Data containing 'email', 'first_name', 'reset_link', 'expiry_time'
 * @return bool True if email was sent successfully, false otherwise
 */
function sendPasswordResetEmail($reset_data) {
    // Check if all required fields are present
    if (!isset($reset_data['email']) || !isset($reset_data['first_name']) || 
        !isset($reset_data['reset_link']) || !isset($reset_data['expiry_time'])) {
        error_log("Incomplete data for password reset email");
        return false;
    }
    
    $subject = "PolyEduHub Password Reset";
    $content = passwordResetEmailTemplate($reset_data);
    
    // Try to send the email
    return sendEmail($reset_data['email'], $subject, $content);
}

/**
 * Send a notification email about resource activities
 * 
 * @param array $notification_data Data containing all necessary notification information
 * @return bool True if email was sent successfully, false otherwise
 */
function sendNotificationEmail($notification_data) {
    // Check if all required fields are present
    if (!isset($notification_data['email']) || !isset($notification_data['first_name']) || 
        !isset($notification_data['type']) || !isset($notification_data['message'])) {
        error_log("Incomplete data for notification email");
        return false;
    }
    
    $subject = "PolyEduHub: " . $notification_data['type'];
    $content = notificationEmailTemplate($notification_data);
    
    // Try to send the email
    return sendEmail($notification_data['email'], $subject, $content);
}

/**
 * Core function to actually send the email
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $message Email content (HTML)
 * @return bool True if email was sent successfully, false otherwise
 */
function sendEmail($to, $subject, $message) {
    // Email headers
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "From: PolyEduHub <noreply@polyeduhub.edu.my>\r\n";
    $headers .= "Reply-To: support@polyeduhub.edu.my\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // For local development, you might want to log instead of actually sending
    if (defined('ENVIRONMENT') && ENVIRONMENT == 'development') {
        error_log("Email would be sent to: $to with subject: $subject");
        error_log("Email content: $message");
        return true;
    }
    
    // Try to send the email
    try {
        $mail_sent = mail($to, $subject, $message, $headers);
        return $mail_sent;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Template for student welcome emails
 * 
 * @param array $user_data User data
 * @return string HTML content for the email
 */
function studentWelcomeEmailTemplate($user_data) {
    $full_name = htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']);
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Welcome to PolyEduHub</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4e73df; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f8f9fc; }
            .footer { font-size: 12px; text-align: center; margin-top: 20px; color: #666; }
            .button { display: inline-block; background-color: #4e73df; color: white; padding: 10px 20px; 
                      text-decoration: none; border-radius: 5px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Welcome to PolyEduHub!</h1>
            </div>
            <div class="content">
                <p>Hello ' . $full_name . ',</p>
                <p>Welcome to PolyEduHub - your centralized platform for accessing, sharing, and collaborating on educational resources!</p>
                <p>As a student, you can now:</p>
                <ul>
                    <li>Access notes, assignments, and activities shared by other students</li>
                    <li>Upload your own resources to help others</li>
                    <li>Participate in chat rooms to collaborate with peers</li>
                    <li>Earn points and badges as you contribute to the community</li>
                </ul>
                <p>Get started by exploring resources or uploading your first material!</p>
                <center><a href="https://polyeduhub.edu.my/login.php" class="button">Login to Your Account</a></center>
                <p>If you have any questions or need assistance, please contact our support team at <a href="mailto:support@polyeduhub.edu.my">support@polyeduhub.edu.my</a>.</p>
                <p>Happy learning!</p>
                <p>The PolyEduHub Team</p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' PolyEduHub. All rights reserved.</p>
                <p>This email was sent to ' . htmlspecialchars($user_data['email']) . '</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Template for admin welcome emails
 * 
 * @param array $user_data User data
 * @return string HTML content for the email
 */
function adminWelcomeEmailTemplate($user_data) {
    $full_name = htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']);
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Admin Account Created - PolyEduHub</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #224abe; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f8f9fc; }
            .footer { font-size: 12px; text-align: center; margin-top: 20px; color: #666; }
            .button { display: inline-block; background-color: #224abe; color: white; padding: 10px 20px; 
                      text-decoration: none; border-radius: 5px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Admin Account Created</h1>
            </div>
            <div class="content">
                <p>Hello ' . $full_name . ',</p>
                <p>Your administrator account on PolyEduHub has been successfully created!</p>
                <p>As an administrator, you have access to:</p>
                <ul>
                    <li>Approve and manage resources uploaded by students</li>
                    <li>Moderate chat rooms and community interactions</li>
                    <li>Manage user accounts and permissions</li>
                    <li>View reports and analytics on platform usage</li>
                    <li>Configure system settings and gamification features</li>
                </ul>
                <p>Please login to access your administrator dashboard:</p>
                <center><a href="https://polyeduhub.edu.my/admin-login.php" class="button">Admin Login</a></center>
                <p>If you have any questions or encounter any issues, please contact the system administrator at <a href="mailto:sysadmin@polyeduhub.edu.my">sysadmin@polyeduhub.edu.my</a>.</p>
                <p>Thank you for your contribution to making PolyEduHub a valuable resource for our students!</p>
                <p>The PolyEduHub Management Team</p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' PolyEduHub. All rights reserved.</p>
                <p>This email was sent to ' . htmlspecialchars($user_data['email']) . '</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Template for password reset emails
 * 
 * @param array $reset_data Password reset data
 * @return string HTML content for the email
 */
function passwordResetEmailTemplate($reset_data) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Password Reset - PolyEduHub</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4e73df; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f8f9fc; }
            .footer { font-size: 12px; text-align: center; margin-top: 20px; color: #666; }
            .button { display: inline-block; background-color: #4e73df; color: white; padding: 10px 20px; 
                      text-decoration: none; border-radius: 5px; margin-top: 20px; }
            .warning { color: #e74a3b; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Password Reset Request</h1>
            </div>
            <div class="content">
                <p>Hello ' . htmlspecialchars($reset_data['first_name']) . ',</p>
                <p>We received a request to reset your password for your PolyEduHub account.</p>
                <p>To reset your password, please click the button below:</p>
                <center><a href="' . htmlspecialchars($reset_data['reset_link']) . '" class="button">Reset Password</a></center>
                <p>If the button above doesn\'t work, copy and paste the following link into your browser:</p>
                <p>' . htmlspecialchars($reset_data['reset_link']) . '</p>
                <p class="warning">This link will expire on ' . htmlspecialchars($reset_data['expiry_time']) . '.</p>
                <p>If you did not request a password reset, please ignore this email or contact support if you have concerns about your account security.</p>
                <p>Thank you,</p>
                <p>The PolyEduHub Team</p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' PolyEduHub. All rights reserved.</p>
                <p>This email was sent to ' . htmlspecialchars($reset_data['email']) . '</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Template for notification emails
 * 
 * @param array $notification_data Notification data
 * @return string HTML content for the email
 */
function notificationEmailTemplate($notification_data) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . htmlspecialchars($notification_data['type']) . ' - PolyEduHub</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4e73df; color: white; padding: 15px; text-align: center; }
            .content { padding: 20px; background-color: #f8f9fc; }
            .footer { font-size: 12px; text-align: center; margin-top: 20px; color: #666; }
            .button { display: inline-block; background-color: #4e73df; color: white; padding: 10px 20px; 
                      text-decoration: none; border-radius: 5px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>' . htmlspecialchars($notification_data['type']) . '</h2>
            </div>
            <div class="content">
                <p>Hello ' . htmlspecialchars($notification_data['first_name']) . ',</p>
                <p>' . $notification_data['message'] . '</p>';
                
    // Add action button if link is provided
    if (isset($notification_data['action_link']) && isset($notification_data['action_text'])) {
        $html .= '<center><a href="' . htmlspecialchars($notification_data['action_link']) . '" class="button">' . 
                 htmlspecialchars($notification_data['action_text']) . '</a></center>';
    }
    
    $html .= '
                <p>Thank you for being part of the PolyEduHub community!</p>
                <p>The PolyEduHub Team</p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' PolyEduHub. All rights reserved.</p>
                <p>This email was sent to ' . htmlspecialchars($notification_data['email']) . '</p>
                <p><small>If you wish to manage your email notifications, visit your <a href="https://polyeduhub.edu.my/student/notifications/settings.php">notification settings</a>.</small></p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}