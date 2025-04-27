<?php

// Include required email template files
require_once 'email-templates/welcome.php';
require_once 'email-templates/password-reset.php';
require_once 'email-templates/notification.php';

/**
 * Send an email using PHP's mail function
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email body (HTML)
 * @param array $headers Optional additional headers
 * @return bool Whether the email was sent successfully
 */
function sendEmail($to, $subject, $message, $headers = []) {
    // Set default headers for HTML email
    $defaultHeaders = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: PolyEduHub <noreply@polyeduhub.edu.my>',
        'Reply-To: support@polyeduhub.edu.my',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // Merge default headers with provided headers
    $allHeaders = array_merge($defaultHeaders, $headers);
    
    // Convert headers array to string
    $headerString = implode("\r\n", $allHeaders);
    
    // Log the email attempt (for debugging)
    error_log("Sending email to: $to, Subject: $subject");
    
    // Send the email
    $result = mail($to, $subject, $message, $headerString);
    
    // Log the result
    if ($result) {
        error_log("Email sent successfully to: $to");
    } else {
        error_log("Failed to send email to: $to");
    }
    
    return $result;
}

/**
 * Send welcome email to newly registered user
 * 
 * @param array $userData User data including first_name, last_name, email, role
 * @return bool Whether the email was sent successfully
 */
function sendWelcomeEmail($userData) {
    // Generate welcome email content
    $emailContent = generateWelcomeEmail($userData);
    
    // Send the email
    return sendEmail(
        $userData['email'],
        $emailContent['subject'],
        $emailContent['body']
    );
}

/**
 * Send password reset email
 * 
 * @param array $resetData Reset data including first_name, email, reset_link, expiry_time
 * @return bool Whether the email was sent successfully
 */
function sendPasswordResetEmail($resetData) {
    // Generate password reset email content
    $emailContent = generatePasswordResetEmail($resetData);
    
    // Send the email
    return sendEmail(
        $resetData['email'],
        $emailContent['subject'],
        $emailContent['body']
    );
}

/**
 * Send notification email
 * 
 * @param array $notificationData Notification data
 * @return bool Whether the email was sent successfully
 */
function sendNotificationEmail($notificationData) {
    // Generate notification email content
    $emailContent = generateNotificationEmail($notificationData);
    
    // Send the email
    return sendEmail(
        $notificationData['email'],
        $emailContent['subject'],
        $emailContent['body']
    );
}