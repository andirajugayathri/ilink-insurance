<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include PHPMailer
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$otp = rand(100000, 999999);
$to = 'madhkunchala@gmail.com'; // Recipient

$mail = new PHPMailer(true);

try {
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = 'html';

    // GoDaddy SMTP Settings
    $mail->isSMTP();
    $mail->Host = 'smtp.secureserver.net';
    $mail->SMTPAuth = true;
    $mail->Username = 's.shah@ilinkinsurance.com'; // GoDaddy email
    $mail->Password = 'IlinkBTPM7874#';             // Email password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // You can also try ENCRYPTION_SMTPS
    $mail->Port = 587; // OR 465 if STARTTLS fails
    $mail->Timeout = 10;

    // Optional: Disable SSL verification (for testing only)
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];

    $mail->setFrom('s.shah@ilinkinsurance.com', 'iLink Insurance');
    $mail->addAddress($to);
    $mail->addReplyTo('info@ilinkinsurance.com', 'iLink Support');

    $mail->isHTML(true);
    $mail->Subject = 'Your OTP Code';
    $mail->Body    = "<h2>Your OTP is: <strong>$otp</strong></h2>";
    $mail->AltBody = "Your OTP is: $otp";

    if ($mail->send()) {
        echo "✅ OTP sent successfully to $to";
    } else {
        echo "❌ Failed to send email.";
    }

} catch (Exception $e) {
    echo "❌ Error sending email: {$mail->ErrorInfo}";
}
?>
