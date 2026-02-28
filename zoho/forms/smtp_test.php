<?php
// Load PHPMailer classes directly
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->SMTPDebug = 2; // Verbose debug output
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 's.shah@ilinkinsurance.com'; // G Suite email
    $mail->Password   = 'IlinkBTPM7874#';            // App password (see note below)
    $mail->SMTPSecure = 'tls';                       // Encryption
    $mail->Port       = 587;

    $mail->setFrom('s.shah@ilinkinsurance.com', 'Shah Test');
    $mail->addAddress('rajaadonai@gmail.com', 'Raja Adonai');

    $mail->isHTML(true);
    $mail->Subject = 'G Suite SMTP Test';
    $mail->Body    = '<h3>Hello!</h3><p>This is a test email sent through G Suite SMTP.</p>';
    $mail->AltBody = 'Hello! This is a test email sent through G Suite SMTP.';

    $mail->send();
    echo "✅ Email sent successfully!";
} catch (Exception $e) {
    echo "❌ Error: {$mail->ErrorInfo}";
}
