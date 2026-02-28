<?php
ob_start();
session_start();
include 'db.php';
include '../access-token.php';



/**
 * Step 1: Collect Form Data
 * Step 2: Insert into Database
 * Step 3: Send to Zoho CRM
 * Step 4: Send Email Notification
 */
 //to avoid junk lads
 
// ------------------- CONFIG -------------------
$allowed_referers = ['ilinkinsurance.com.au', 'www.ilinkinsurance.com.au'];
$log_file = __DIR__ . '/test_hits.log'; // log file for test hits
// ---------------------------------------------
$minTime = 5; // seconds
$startTime = $_POST['form_start_time'] ?? time();

if ((time() - $startTime) < $minTime) {
    error_log("Bot suspected due to fast submission | IP: {$_SERVER['REMOTE_ADDR']}");
    header("Location: /error.html");
    exit();
}


// ------------------- TEST MODE FIRST -------------------
// Test mode: logs POST requests regardless of referer
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

// ------------------- SECURITY CHECKS -------------------






// 1️⃣ Block requests with missing or invalid Referer
$referer = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
if (empty($referer) || !in_array($referer, $allowed_referers)) {
    error_log("Blocked submission: Invalid or missing referer | IP: {$_SERVER['REMOTE_ADDR']}");
    header("Location: /error.html");
    exit();
}

// 2️⃣ Honeypot validation
if (!empty($_POST['website'])) {
    error_log("Honeypot triggered. Possible spam | IP: {$_SERVER['REMOTE_ADDR']}");
    header("Location: /error.html");
    exit();
}


 
 
 
 

// Map form fields to match Zoho CRM structure
function mapFormFields($formData) {
    return [
        'Last_Name' => ($formData['full-name'] ?? ''),
        'Company' => ($formData['company-name'] ?? ''),
        'Phone' => ($formData['contact'] ?? ''),
        'Email' => ($formData['email'] ?? ''),
        'Coverage_Required' => isset($formData['coverage']) ? array_map('htmlspecialchars', (array) $formData['coverage']) : [],
        'Description' => ($formData['description'] ?? ''),
        'Layout' => [
            'name' => 'Website',
            'id' => '62950000001318018'
        ],
        'Owner' => [
            'name' => 'Shalin Shah',
            'id' => '62950000000229001'
        ],
        'Enquiry_Source' => 'ILink Website - Call Back Form',
        'Product_Inquiry' => 'Hotel Insurance'
    ];
}

// Insert data into database
function insertDataIntoDatabase($mappedData, $pdo) {
    try {
        $sql = "INSERT INTO Hotel_insurance(full_name, company_name, contact_number, email, coverage) 
                VALUES (:full_name, :company_name, :contact_number, :email, :coverage)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':full_name' => $mappedData['Last_Name'],
            ':company_name' => $mappedData['Company'],
            ':contact_number' => $mappedData['Phone'],
            ':email' => $mappedData['Email'],
            ':coverage' => implode(', ', $mappedData['Coverage_Required']) // Convert array to string
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

    $responseData = json_decode($response, true);

    if ($httpCode !== 201 || empty($responseData['data'][0]['code']) || $responseData['data'][0]['code'] !== "SUCCESS") {
        error_log("Error sending data to Zoho: " . print_r($responseData, true));
        return false;
    }

    return true;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Form Data: " . print_r($_POST, true));

    $mappedData = mapFormFields($_POST);
//   error_log("data mapped", $mappedData);
 error_log("data mapped: " . print_r($mappedData, true));
    // Validate required fields
    if (empty($mappedData['Last_Name']) || empty($mappedData['Email']) || empty($mappedData['Phone'])) {
        header("Location: /error.html");
      
    }

    // Insert data into database
    if (insertDataIntoDatabase($mappedData, $pdo)) {
        if (addRecordToZoho($mappedData, $pdo)) {
            // Send email notification
            //   error_log("zoho function called", $mappedData);
             error_log("zoho function called: " . print_r($mappedData, true));
              
            $to = "info@ilinkinsurance.com.au, smartsolutions.designstudio@gmail.com, quotes@ilinkinsurance.com.au,smartsolutions.designstudio1@gmail.com";
            $subject = "New Insurance Inquiry from Hotel Insurance";

            // Fix: Correctly process Coverage data
            $coverage = !empty($mappedData['Coverage_Required']) ? implode(', ', $mappedData['Coverage_Required']) : 'N/A';

           $emailBody = "
<html>
<head>
  <title>New Insurance Inquiry</title>
  <style>
    body { font-family: Arial, sans-serif; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
    th { background-color: #f2f2f2; }
    h2 { color: #333; }
  </style>
</head>
<body>
  <h2>New Insurance Inquiry for Hotel Insurance</h2>
  <table>
    <tr><th>Full Name</th><td>{$mappedData['Last_Name']}</td></tr>
    <tr><th>Email</th><td>{$mappedData['Email']}</td></tr>
    <tr><th>Phone Number</th><td>{$mappedData['Phone']}</td></tr>
    <tr><th>Company Name</th><td>{$mappedData['Company']}</td></tr>
    <tr><th>Coverage</th><td>{$coverage}</td></tr>
  </table>
</body>
</html>";


            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: no-reply@ilinkinsurance.com.au\r\n";
            $replyToEmail = filter_var($mappedData['Email'], FILTER_SANITIZE_EMAIL);
            $headers .= "Reply-To: $replyToEmail\r\n";

            if (!mail($to, $subject, $emailBody, $headers)) {
                error_log("Email sending failed for: " . $mappedData['Email']);
                header("Location: /error.html");
                
            }

            header("Location: /thankyou.html");
            exit();
        }
    }
}

// If anything fails, redirect to error page
header("Location: /error.html");
exit();

ob_end_flush();
