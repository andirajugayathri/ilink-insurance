<?php
session_start();
header('Content-Type: application/json');

// Include the file with OTP functions
require_once 'zoho/forms/indexquote.php';

// Database configuration
$host = 'localhost';
$dbname = 'ilink_insurance';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Handle OTP generation request
if ($_POST['action'] === 'send_otp') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit;
    }
    
    // Use the sendOTP function from indexquote.php
    $result = sendOTP($email, $pdo);
    echo json_encode($result);
}

// Handle OTP verification request
if ($_POST['action'] === 'verify_otp') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $otp = $_POST['otp'];
    
    // Use the verifyOTP function from indexquote.php
    $result = verifyOTP($email, $otp, $pdo);
    echo json_encode($result);
}
?> 