<?php
/**
 * Asynchronous Email Sender for OTP
 * This script processes email queue files and sends emails in the background
 */

// Prevent direct access
if (php_sapi_name() !== 'cli' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    die('Direct access not allowed');
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/email_errors.log');

// Function to send email with improved configuration
function sendEmailWithConfig($email, $otp) {
    error_log("=== ASYNC EMAIL SENDING STARTED ===");
    error_log("Processing email for: $email");
    
    $subject = "Your OTP for iLink Insurance Quote";
    
    // Enhanced email content
    $plainText = "Your OTP is: $otp\n\nThis code will expire in 10 minutes.\nDo not share this OTP with anyone.\n\nBest regards,\niLink Insurance Team";
    
    $htmlMessage = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #2c3e50;'>Your OTP for iLink Insurance Quote</h2>
            <p>Your One-Time Password (OTP) is:</p>
            <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; text-align: center; margin: 20px 0;'>
                <h1 style='color: #e74c3c; font-size: 32px; margin: 0; letter-spacing: 5px;'>$otp</h1>
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
    
    $boundary = md5(time());
    
    // Enhanced headers for better delivery
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "From: iLink Insurance <no-reply@ilinkinsurance.com.au>\r\n";
    $headers .= "Reply-To: no-reply@ilinkinsurance.com.au\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "X-Priority: 1\r\n"; // High priority
    $headers .= "X-MSMail-Priority: High\r\n";
    $headers .= "Importance: High\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    
    // Build message
    $message = "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $message .= $plainText . "\r\n\r\n";
    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $message .= $htmlMessage . "\r\n\r\n";
    $message .= "--{$boundary}--";
    
    // Try multiple email sending methods
    $success = false;
    
    // Method 1: PHP mail() function with optimized settings
    if (!$success) {
        error_log("Attempting to send via PHP mail() function...");
        
        // Set mail configuration for faster delivery
        ini_set('sendmail_path', '/usr/sbin/sendmail -t -i -f no-reply@ilinkinsurance.com.au');
        ini_set('SMTP', 'localhost');
        ini_set('smtp_port', '25');
        
        $mailResult = mail($email, $subject, $message, $headers, "-f no-reply@ilinkinsurance.com.au");
        
        if ($mailResult) {
            error_log("SUCCESS: Email sent via PHP mail() to: $email");
            $success = true;
        } else {
            error_log("FAILED: PHP mail() function failed for: $email");
        }
    }
    
    // Method 2: Direct SMTP connection (fallback)
    if (!$success) {
        error_log("Attempting to send via direct SMTP connection...");
        
        $smtpHost = 'localhost';
        $smtpPort = 25;
        $timeout = 10;
        
        $socket = fsockopen($smtpHost, $smtpPort, $errno, $errstr, $timeout);
        
        if ($socket) {
            // Basic SMTP conversation
            fgets($socket, 515); // Read greeting
            
            fwrite($socket, "HELO localhost\r\n");
            fgets($socket, 515);
            
            fwrite($socket, "MAIL FROM: <no-reply@ilinkinsurance.com.au>\r\n");
            fgets($socket, 515);
            
            fwrite($socket, "RCPT TO: <$email>\r\n");
            fgets($socket, 515);
            
            fwrite($socket, "DATA\r\n");
            fgets($socket, 515);
            
            fwrite($socket, $headers . "\r\n" . $message . "\r\n.\r\n");
            fgets($socket, 515);
            
            fwrite($socket, "QUIT\r\n");
            fclose($socket);
            
            error_log("SUCCESS: Email sent via direct SMTP to: $email");
            $success = true;
        } else {
            error_log("FAILED: Could not connect to SMTP server: $errstr ($errno)");
        }
    }
    
    // Method 3: External mail command (fallback)
    if (!$success) {
        error_log("Attempting to send via external mail command...");
        
        $tempFile = tempnam(sys_get_temp_dir(), 'email_');
        file_put_contents($tempFile, $headers . "\r\n" . $message);
        
        $command = "sendmail -t -f no-reply@ilinkinsurance.com.au < $tempFile";
        $output = shell_exec($command . " 2>&1");
        
        unlink($tempFile);
        
        if (empty($output)) {
            error_log("SUCCESS: Email sent via external mail command to: $email");
            $success = true;
        } else {
            error_log("FAILED: External mail command failed: $output");
        }
    }
    
    if ($success) {
        error_log("=== ASYNC EMAIL SENDING COMPLETED SUCCESSFULLY ===");
        return true;
    } else {
        error_log("=== ASYNC EMAIL SENDING FAILED ===");
        return false;
    }
}

// Main processing logic
if (isset($argv[1])) {
    // Process specific email file
    $emailFile = $argv[1];
    
    if (file_exists($emailFile)) {
        $emailData = json_decode(file_get_contents($emailFile), true);
        
        if ($emailData && isset($emailData['email']) && isset($emailData['otp'])) {
            $result = sendEmailWithConfig($emailData['email'], $emailData['otp']);
            
            // Clean up the email file
            unlink($emailFile);
            
            if ($result) {
                error_log("Email processing completed successfully");
                exit(0);
            } else {
                error_log("Email processing failed");
                exit(1);
            }
        } else {
            error_log("Invalid email data format in file: $emailFile");
            unlink($emailFile);
            exit(1);
        }
    } else {
        error_log("Email file not found: $emailFile");
        exit(1);
    }
} else {
    // Process all pending emails in queue
    $emailQueueDir = __DIR__ . '/email_queue';
    
    if (is_dir($emailQueueDir)) {
        $emailFiles = glob($emailQueueDir . '/*.json');
        
        foreach ($emailFiles as $emailFile) {
            $emailData = json_decode(file_get_contents($emailFile), true);
            
            if ($emailData && isset($emailData['email']) && isset($emailData['otp'])) {
                error_log("Processing email file: " . basename($emailFile));
                $result = sendEmailWithConfig($emailData['email'], $emailData['otp']);
                
                // Clean up the email file
                unlink($emailFile);
                
                if (!$result) {
                    error_log("Failed to process email file: " . basename($emailFile));
                }
            } else {
                error_log("Invalid email data in file: " . basename($emailFile));
                unlink($emailFile);
            }
        }
    }
    
    error_log("Email queue processing completed");
    exit(0);
}
?> 