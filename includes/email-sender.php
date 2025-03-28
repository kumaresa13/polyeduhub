<?php
/**
 * Email Sender
 * 
 * This file acts as a bridge to the actual email functionality
 * in the email-templates directory.
 */

// Include the actual email functionality files
require_once __DIR__ . '/email-templates/welcome.php';
require_once __DIR__ . '/email-templates/password-reset.php';
require_once __DIR__ . '/email-templates/notification.php';

/**
 * Send a welcome email to a newly registered user
 * 
 * @param array $user_data User data containing 'first_name', 'last_name', 'email', 'role'
 * @return bool True if email was sent successfully, false otherwise
 */
function sendWelcomeEmail($user_data) {
    // In development environment, just log and return success
    if (defined('ENVIRONMENT') && ENVIRONMENT == 'development') {
        error_log("Welcome email would be sent to: " . ($user_data['email'] ?? 'unknown email'));
        return true;
    }
    
    // Generate email content using the template
    $emailContent = generateWelcomeEmail($user_data);
    
    // Try to send the email
    $to = $user_data['email'];
    $subject = $emailContent['subject'];
    $message = $emailContent['body'];
    
    // Email headers
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "From: PolyEduHub <noreply@polyeduhub.edu.my>\r\n";
    $headers .= "Reply-To: support@polyeduhub.edu.my\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
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
 * Send a password reset email
 * 
 * @param array $reset_data Data containing 'email', 'first_name', 'reset_link', 'expiry_time'
 * @return bool True if email was sent successfully, false otherwise
 */
function sendPasswordResetEmail($reset_data) {
    // In development environment, just log and return success
    if (defined('ENVIRONMENT') && ENVIRONMENT == 'development') {
        error_log("Password reset email would be sent to: " . ($reset_data['email'] ?? 'unknown email'));
        return true;
    }
    
    // Generate email content using the template
    $emailContent = generatePasswordResetEmail($reset_data);
    
    // Try to send the email
    $to = $reset_data['email'];
    $subject = $emailContent['subject'];
    $message = $emailContent['body'];
    
    // Email headers
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "From: PolyEduHub <noreply@polyeduhub.edu.my>\r\n";
    $headers .= "Reply-To: support@polyeduhub.edu.my\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
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
 * Send a notification email
 * 
 * @param string $notification_type Type of notification
 * @param array $data Notification data
 * @return bool True if email was sent successfully, false otherwise
 */
function sendNotificationEmail($notification_type, $data) {
    // In development environment, just log and return success
    if (defined('ENVIRONMENT') && ENVIRONMENT == 'development') {
        error_log("Notification email ({$notification_type}) would be sent to: " . ($data['email'] ?? 'unknown email'));
        return true;
    }
    
    // Generate email content using the template
    $emailContent = createNotificationByType($notification_type, $data);
    
    // Try to send the email
    $to = $data['email'];
    $subject = $emailContent['subject'];
    $message = $emailContent['body'];
    
    // Email headers
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "From: PolyEduHub <noreply@polyeduhub.edu.my>\r\n";
    $headers .= "Reply-To: support@polyeduhub.edu.my\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Try to send the email
    try {
        $mail_sent = mail($to, $subject, $message, $headers);
        return $mail_sent;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}