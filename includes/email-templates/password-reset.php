<?php
/**
 * Password Reset Email Template
 * File location: polyeduhub/includes/email-templates/password-reset.php
 */

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