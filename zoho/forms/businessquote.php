<?php
ob_start();
session_start();
include 'db.php';
include '../access-token.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------- SECURITY CONFIG -------------------
$allowed_referers = ['ilinkinsurance.com.au', 'www.ilinkinsurance.com.au'];
$log_file = __DIR__ . '/test_hits.log'; // log file for test hits
$minTime = 5; // seconds

// ------------------- TIME VALIDATION -------------------
$startTime = $_POST['form_start_time'] ?? time();
if ((time() - $startTime) < $minTime) {
    error_log("Bot suspected due to fast submission | IP: {$_SERVER['REMOTE_ADDR']}");
    header("Location: /error.html");
    exit();
}

// ------------------- TEST MODE -------------------
if (!empty($_POST['test_mode']) && $_POST['test_mode'] == '1') {
    $logData = [
        'timestamp' => date('c'),
        'IP' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'referer' => $_SERVER['HTTP_REFERER'] ?? 'none',
        'POST' => $_POST
    ];
    file_put_contents($log_file, json_encode($logData) . PHP_EOL, FILE_APPEND);
    echo "TEST OK";
    exit;
}

// ------------------- REFERER CHECK -------------------
$referer = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
if (empty($referer) || !in_array($referer, $allowed_referers)) {
    error_log("Blocked submission: Invalid or missing referer | IP: {$_SERVER['REMOTE_ADDR']}");
    header("Location: /error.html");
    exit();
}

// ------------------- HONEYPOT FIELD -------------------
if (!empty($_POST['website'])) {
    error_log("Honeypot triggered. Possible spam | IP: {$_SERVER['REMOTE_ADDR']}");
    header("Location: /error.html");
    exit();
}

// ------------------- EMAIL CONFIRMATION -------------------
$email = $_POST['email'] ?? '';
$confirmEmail = $_POST['confirmEmail'] ?? '';
if (empty($email) || empty($confirmEmail) || $email !== $confirmEmail) {
    error_log("Email confirmation failed | IP: {$_SERVER['REMOTE_ADDR']}");
    header("Location: /error.html");
    exit();
}

// ------------------- MAIN FORM HANDLER -------------------

// Map form fields to match Zoho CRM structure
function mapFormFields($formData) {
    return [
        'Last_Name' => htmlspecialchars($formData['full_name'] ?? ''),
        'Company' => htmlspecialchars($formData['company_name'] ?? ''),
        'Phone' => htmlspecialchars($formData['contact'] ?? ''),
        'Email' => htmlspecialchars($formData['email'] ?? ''),
        'Coverage_Required' => array_map('htmlspecialchars', $formData['coverage'] ?? []),
        'Description' => htmlspecialchars($formData['description'] ?? ''),
        'Layout'=> [
            'name'=> 'Website',
            'id' => '62950000001318018'
        ],
        'Owner'=>[
            'name'=> 'Shalin Shah',
            'id'=> '62950000000229001'
        ],
        'Enquiry_Source' => 'ILink Website - Call Back Form',
        'Product_Inquiry' => 'Business Insurance'
    ];
}

// Insert data into database
function insertDataIntoDatabase($mappedData, $pdo) {
    try {
        $sql = "INSERT INTO Business_insurance(full_name, company_name, contact_number, email, coverage) 
                VALUES (:full_name, :company_name, :contact_number, :email, :coverage)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':full_name' => $mappedData['Last_Name'],
            ':company_name' => $mappedData['Company'],
            ':contact_number' => $mappedData['Phone'],
            ':email' => $mappedData['Email'],
            ':coverage' => implode(', ', $mappedData['Coverage_Required'])
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Database Insert Error: " . $e->getMessage());
        return false;
    }
}

// Send data to Zoho CRM
function addRecordToZoho($mappedData, $pdo) {
    getAccessToken($pdo);
    $accessToken = $_SESSION['access_token'] ?? null;
    
    if (!$accessToken) {
        error_log("Error: Zoho Access Token Missing.");
        return false;
    }
    
    $apiUrl = "https://www.zohoapis.com.au/crm/v2/Leads";
    $jsonData = json_encode(['data' => [$mappedData]]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Zoho-oauthtoken $accessToken",
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 201) {
        error_log("Error sending data to Zoho: " . $response);
    }
    return $httpCode === 201;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Form Data: " . print_r($_POST, true));
    
    $mappedData = mapFormFields($_POST);
    
    // Validate required fields
    if (empty($mappedData['Last_Name']) || empty($mappedData['Email']) || empty($mappedData['Phone'])) {
        header("Location: /error.html");
        exit();
    }
    
    // Insert data into database
    if (insertDataIntoDatabase($mappedData, $pdo)) {
        if (addRecordToZoho($mappedData, $pdo)) {
            // Send email notification
            $to = "info@ilinkinsurance.com.au, smartsolutions.designstudio@gmail.com, quotes@ilinkinsurance.com.au, smartsolutions.designstudio1@gmail.com";
            $subject = "New Insurance Inquiry from Business Insurance";
            
            $message = "<html><head><title>Insurance Inquiry</title><style>
                        table { width: 100%; border-collapse: collapse; }
                        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        </style></head>
                        <body><h2>New Insurance Inquiry for Business Insurance</h2>
                        <table>
                        <tr><th>Full Name</th><td>{$mappedData['Last_Name']}</td></tr>
                        <tr><th>Email</th><td>{$mappedData['Email']}</td></tr>
                        <tr><th>Phone Number</th><td>{$mappedData['Phone']}</td></tr>
                        <tr><th>Company Name</th><td>{$mappedData['Company']}</td></tr>
                        <tr><th>Coverage</th><td>" . implode(', ', $mappedData['Coverage_Required']) . "</td></tr>
                        </table></body></html>";
            
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: no-reply@ilinkinsurance.com.au\r\n";
            $headers .= "Reply-To: {$mappedData['Email']}\r\n";
            
            if (mail($to, $subject, $message, $headers)) {
                header("Location: /thankyou.html");
                exit();
            } else {
                error_log("Email sending failed.");
            }
        }
    }
}

// If anything fails, redirect to error page
header("Location: /error.html");
exit();
?>
