<?php
/**
 * Welcome Email Template
 * File location: polyeduhub/includes/email-templates/welcome.php
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