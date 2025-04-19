<?php
/**
 * Mailer functions for PolyEduHub
 * File location: includes/mailer.php
 */

/**
 * Send an email using PHP's mail function
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param array $headers Additional headers
 * @return bool Whether the email was sent
 */
function sendEmail($to, $subject, $body, $headers = []) {
    // For development with XAMPP, log emails instead of sending
    $logFile = __DIR__ . '/../logs/emails.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Log the email content
    $emailContent = "==== " . date('Y-m-d H:i:s') . " ====\r\n";
    $emailContent .= "To: $to\r\n";
    $emailContent .= "Subject: $subject\r\n";
    
    // Default headers
    $defaultHeaders = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: PolyEduHub <noreply@polyeduhub.edu.my>'
    ];
    
    // Merge with custom headers
    $allHeaders = array_merge($defaultHeaders, $headers);
    
    foreach ($allHeaders as $header) {
        $emailContent .= "$header\r\n";
    }
    
    $emailContent .= "\r\n$body\r\n";
    $emailContent .= "=============================================\r\n\r\n";
    
    file_put_contents($logFile, $emailContent, FILE_APPEND);
    
    // Log to PHP error log as well
    error_log("Email would be sent to: $to with subject: $subject");
    
    // For development with XAMPP (no mail server configured)
    // Comment this out and uncomment the mail() line when in production
    return true;
    
    // Uncomment this line when in production with a configured mail server
    // return mail($to, $subject, $body, implode("\r\n", $allHeaders));
}

/**
 * Send welcome email to newly registered user
 * 
 * @param array $userData User data including email, first_name, etc.
 * @return bool Whether the email was sent
 */
function sendWelcomeEmail($userData) {
    // Generate simple welcome email content
    $subject = "Welcome to PolyEduHub - Your Educational Resource Hub";
    
    $body = "
    <html>
    <head>
        <title>Welcome to PolyEduHub</title>
    </head>
    <body>
        <h2>Welcome to PolyEduHub, " . htmlspecialchars($userData['first_name']) . "!</h2>
        <p>Thank you for joining our educational resource sharing platform.</p>
        <p>Your account has been created successfully with the following details:</p>
        <ul>
            <li><strong>Email:</strong> " . htmlspecialchars($userData['email']) . "</li>
            <li><strong>Role:</strong> " . ucfirst(htmlspecialchars($userData['role'])) . "</li>
        </ul>
        <p>You can now log in and start exploring resources, uploading your own materials, and connecting with other students.</p>
        <p>Best regards,<br>The PolyEduHub Team</p>
    </body>
    </html>";
    
    // Send the email
    return sendEmail($userData['email'], $subject, $body);
}

/**
 * Send password reset email
 * 
 * @param array $userData User data including email, first_name, reset_link, etc.
 * @return bool Whether the email was sent
 */
function sendPasswordResetEmail($userData) {
    // Generate simple password reset email content
    $subject = "PolyEduHub - Password Reset Request";
    
    $body = "
    <html>
    <head>
        <title>Password Reset Request</title>
    </head>
    <body>
        <h2>Password Reset Request</h2>
        <p>Hello " . htmlspecialchars($userData['first_name']) . ",</p>
        <p>We received a request to reset your password for your PolyEduHub account.</p>
        <p>To reset your password, please click the link below:</p>
        <p><a href=\"" . htmlspecialchars($userData['reset_link']) . "\">Reset My Password</a></p>
        <p>This link will expire in " . htmlspecialchars($userData['expiry_time']) . " minutes.</p>
        <p>If you did not request a password reset, please ignore this email.</p>
        <p>Best regards,<br>The PolyEduHub Team</p>
    </body>
    </html>";
    
    // Send the email
    return sendEmail($userData['email'], $subject, $body);
}
?>