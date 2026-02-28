<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader or manually include
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Set execution time limit to prevent endless loading
set_time_limit(60); // 60 seconds max

// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define log file path
$logFile = __DIR__ . '/mail_error_log.txt';

// Log start time
file_put_contents($logFile, date('Y-m-d H:i:s') . " [START] Email sending attempt started\n", FILE_APPEND);

echo "<h3>Testing Email Configuration...</h3>";

// First, test basic connectivity
echo "<p>Testing connectivity to localhost:25...</p>";
$fp = @fsockopen("localhost", 25, $errno, $errstr, 5);
if (!$fp) {
    echo "<p>❌ Cannot connect to localhost:25 - $errstr ($errno)</p>";
    echo "<p>Trying alternative GoDaddy SMTP...</p>";
    $useRelay = true;
} else {
    echo "<p>✅ localhost:25 is accessible</p>";
    fclose($fp);
    $useRelay = false;
}

// Create a new PHPMailer instance
$mail = new PHPMailer(true);

try {
    // Disable debug output to browser (only log to file)
    $mail->SMTPDebug = 0; // Changed from 2 to 0 to prevent browser hanging
    $mail->Debugoutput = function ($str, $level) use ($logFile) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " DEBUG [$level]: $str\n", FILE_APPEND);
    };
    
    $mail->isSMTP();
    
    if ($useRelay) {
        // Use GoDaddy's relay server
        $mail->Host = 'relay-hosting.secureserver.net';
        $mail->Port = 25;
        echo "<p>Using GoDaddy relay server...</p>";
    } else {
        // Use localhost
        $mail->Host = 'localhost';
        $mail->Port = 25;
        echo "<p>Using localhost SMTP...</p>";
    }
    
    $mail->SMTPAuth = false;
    $mail->SMTPSecure = false;
    
    // Aggressive timeout settings to prevent hanging
    $mail->Timeout = 10;        // Connection timeout
    $mail->SMTPKeepAlive = false;
    
    // Set additional socket options
    $mail->SMTPOptions = array(
        'socket' => array(
            'timeout' => 10,
        ),
    );
    
    echo "<p>Configuring email...</p>";
    
    // Recipients
    $mail->setFrom('info@ilinkinsurance.com.au', 'iLink Insurance');
    $mail->addAddress('madhkunchala@gmail.com', 'Madhu');
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email - ' . date('Y-m-d H:i:s');
    $mail->Body = '<h3>Hello!</h3><p>This is a <strong>test email</strong> sent at ' . date('Y-m-d H:i:s') . '</p>';
    $mail->AltBody = 'Hello! This is a test email sent at ' . date('Y-m-d H:i:s');
    
    echo "<p>Attempting to send email...</p>";
    flush(); // Force output to browser
    
    // Send the message
    $mail->send();
    
    echo '<p>✅ Email sent successfully!</p>';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [SUCCESS] Email sent successfully\n", FILE_APPEND);
    
} catch (Exception $e) {
    echo "<p>❌ Failed to send email.</p>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>PHPMailer Error:</strong> " . htmlspecialchars($mail->ErrorInfo) . "</p>";
    
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [ERROR] Exception: " . $e->getMessage() . "\n", FILE_APPEND);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [ERROR] PHPMailer Error: " . $mail->ErrorInfo . "\n", FILE_APPEND);
}

// Log completion
file_put_contents($logFile, date('Y-m-d H:i:s') . " [END] Email sending attempt completed\n", FILE_APPEND);

echo "<h4>Alternative: Using PHP mail() function</h4>";

// Try simple PHP mail as backup
$to = 'madhkunchala@gmail.com';
$subject = 'Simple Mail Test - ' . date('Y-m-d H:i:s');
$message = 'This is a simple test email using PHP mail() function.';
$headers = 'From: info@ilinkinsurance.com.au' . "\r\n" .
           'Reply-To: info@ilinkinsurance.com.au' . "\r\n" .
           'Content-Type: text/plain; charset=UTF-8' . "\r\n";

if (mail($to, $subject, $message, $headers)) {
    echo "<p>✅ Simple mail() function worked!</p>";
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [SUCCESS] Simple mail() function worked\n", FILE_APPEND);
} else {
    echo "<p>❌ Simple mail() function failed</p>";
    file_put_contents($logFile, date('Y-m-d H:i:s') . " [ERROR] Simple mail() function failed\n", FILE_APPEND);
}

echo "<p><strong>Check the log file 'mail_error_log.txt' for detailed information.</strong></p>";
?>