<?php
/**
 * Mail Configuration for OTP Delivery
 * Optimized settings to reduce email delivery delays
 */

// Mail server configuration
define('MAIL_HOST', 'localhost');
define('MAIL_PORT', 25);
define('MAIL_FROM', 'no-reply@ilinkinsurance.com.au');
define('MAIL_FROM_NAME', 'iLink Insurance');

// SMTP configuration for faster delivery
function configureMailSettings() {
    // Set mail configuration for optimal performance
    ini_set('sendmail_path', '/usr/sbin/sendmail -t -i -f ' . MAIL_FROM);
    ini_set('SMTP', MAIL_HOST);
    ini_set('smtp_port', MAIL_PORT);
    ini_set('sendmail_from', MAIL_FROM);
    
    // Additional mail settings for faster delivery
    ini_set('mail.add_x_header', 1);
    ini_set('mail.log', __DIR__ . '/mail.log');
}

// Function to get optimized mail headers
function getOptimizedMailHeaders($email, $subject) {
    $boundary = md5(time());
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    $headers .= "Reply-To: " . MAIL_FROM . "\r\n";
    $headers .= "To: $email\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "X-Priority: 1\r\n"; // High priority
    $headers .= "X-MSMail-Priority: High\r\n";
    $headers .= "Importance: High\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "Message-ID: <" . time() . "." . uniqid() . "@ilinkinsurance.com.au>\r\n";
    
    return $headers;
}

// Function to send email with optimized settings
function sendOptimizedEmail($email, $subject, $plainText, $htmlMessage) {
    // Configure mail settings
    configureMailSettings();
    
    // Get optimized headers
    $headers = getOptimizedMailHeaders($email, $subject);
    $boundary = md5(time());
    
    // Build message
    $message = "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $message .= $plainText . "\r\n\r\n";
    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $message .= $htmlMessage . "\r\n\r\n";
    $message .= "--{$boundary}--";
    
    // Send email with optimized parameters
    $mailResult = mail($email, $subject, $message, $headers, "-f " . MAIL_FROM);
    
    if ($mailResult) {
        error_log("SUCCESS: Optimized email sent to: $email");
        return true;
    } else {
        error_log("ERROR: Failed to send optimized email to: $email");
        return false;
    }
}

// Function to check mail server status
function checkMailServerStatus() {
    $host = MAIL_HOST;
    $port = MAIL_PORT;
    $timeout = 5;
    
    $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
    
    if ($socket) {
        fclose($socket);
        error_log("Mail server status: OK - $host:$port");
        return true;
    } else {
        error_log("Mail server status: FAILED - $host:$port - $errstr ($errno)");
        return false;
    }
}

// Function to get mail queue status
function getMailQueueStatus() {
    $queueDir = __DIR__ . '/email_queue';
    
    if (is_dir($queueDir)) {
        $files = glob($queueDir . '/*.json');
        $count = count($files);
        error_log("Mail queue status: $count pending emails");
        return $count;
    } else {
        error_log("Mail queue status: Queue directory not found");
        return 0;
    }
}

// Function to process mail queue
function processMailQueue() {
    $queueDir = __DIR__ . '/email_queue';
    
    if (!is_dir($queueDir)) {
        mkdir($queueDir, 0755, true);
        return;
    }
    
    $files = glob($queueDir . '/*.json');
    
    foreach ($files as $file) {
        $emailData = json_decode(file_get_contents($file), true);
        
        if ($emailData && isset($emailData['email']) && isset($emailData['otp'])) {
            $subject = "Your OTP for iLink Insurance Quote";
            $plainText = "Your OTP is: {$emailData['otp']}\n\nThis code will expire in 10 minutes.\nDo not share this OTP with anyone.\n\nBest regards,\niLink Insurance Team";
            
            $htmlMessage = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #2c3e50;'>Your OTP for iLink Insurance Quote</h2>
                    <p>Your One-Time Password (OTP) is:</p>
                    <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; text-align: center; margin: 20px 0;'>
                        <h1 style='color: #e74c3c; font-size: 32px; margin: 0; letter-spacing: 5px;'>{$emailData['otp']}</h1>
                    </div>
                    <p><strong>Important:</strong></p>
                    <ul>
                        <li>This code will expire in 10 minutes</li>
                        <li>Do not share this OTP with anyone</li>
                        <li>If you didn't request this OTP, please ignore this email</li>
                    </ul>
                    <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                    <p style='color: #7f8c8d; font-size: 14px;'>
                        Best regards,<br>
                        <strong>iLink Insurance Team</strong>
                    </p>
                </div>
            </body>
            </html>";
            
            $result = sendOptimizedEmail($emailData['email'], $subject, $plainText, $htmlMessage);
            
            // Remove processed file
            unlink($file);
            
            if (!$result) {
                error_log("Failed to process email: {$emailData['email']}");
            }
        } else {
            // Remove invalid file
            unlink($file);
            error_log("Removed invalid email file: " . basename($file));
        }
    }
}

// Auto-process queue if accessed directly
if (basename($_SERVER['SCRIPT_NAME']) === 'mail_config.php') {
    processMailQueue();
}
?> 