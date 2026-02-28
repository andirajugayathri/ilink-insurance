<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $full_name = $_POST['fullName'];
    $email = $_POST['email'];
    $contact = $_POST['contact'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];

    // Prepare the email content
    $email_subject = "Ilnik Insurance (Asbestos Page) Inquiry";
    $email_message = "
        <html>
        <head>
            <title>New Asbestos Inquiry</title>
        </head>
        <body>
            <table border='1'>
                <tr><th>Full Name</th><td>{$full_name}</td></tr>
                <tr><th>Email</th><td>{$email}</td></tr>
                <tr><th>Contact Number</th><td>{$contact}</td></tr>
                <tr><th>Subject</th><td>{$subject}</td></tr>
                <tr><th>Message</th><td>{$message}</td></tr>
                <tr><th>Submission Date</th><td>" . date("Y-m-d H:i:s") . "</td></tr>
            </table>
        </body>
        </html>
    ";

    // Set recipient email addresses
    $to = "info@ilinkinsurance.com.au, smartsolutions.designstudio@gmail.com, quotes@ilinkinsurance.com.au, madhkunchala@gmail.com";

    // Set headers for the email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: {$email}" . "\r\n";
    $headers .= "Reply-To: {$email}" . "\r\n";

    // Send the email using the mail() function
    if (mail($to, $email_subject, $email_message, $headers)) {
        // Redirect to the thank you page after email is sent
        header("Location: thankyou.html");
        exit();
    } else {
        // If email fails, display an error message
        echo "Error sending email.";
    }
} else {
    echo "Invalid request.";
}
?>
