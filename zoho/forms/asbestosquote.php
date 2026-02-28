<?php
// Including DB File
ob_start(); 
include 'db.php';
include '../access-token.php';

// to avoid junk leads

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

// Map form field names to match Zoho CRM
function mapFormFields($formData) {
    return [
        'Last_Name' => isset($formData['full_name']) ? trim($formData['full_name']) : '',
        'Email' => isset($formData['email']) ? trim($formData['email']) : '',
        'Phone' => isset($formData['phone']) ? trim($formData['phone']) : '',
        'Subject' => isset($formData['subject']) ? trim($formData['subject']) : '',
        'Message' => isset($formData['message']) ? trim($formData['message']) : '',
       'Layout'=> [
            'name'=> 'Website',
            'id' => '62950000001318018'
        ],
         'Owner'=>[
                'name'=> 'Shalin Shah',
            'id'=> '62950000000229001'
            
                   ],
        'Enquiry_Source' => 'ILink Website - Call Back Form',
         'Product_Inquiry' => 'Asbestos Insurance'
    ];
}

// Function to insert data into the database
function insertDataIntoDatabase($mappedData, $pdo) {
    try {
        $sql = "INSERT INTO Asbestos_insurance(full_name, email, contact, subject, message) 
                VALUES (:full_name, :email, :contact, :subject, :message)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':full_name' => $mappedData['Last_Name'],
            ':email' => $mappedData['Email'],
            ':contact' => $mappedData['Phone'],
            ':subject' => $mappedData['Subject'],
            ':message' => $mappedData['Message']
        ]);

        return true;
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        return false;
    }
}

// Function to send data to Zoho CRM
function addRecordToZoho($mappedData, $pdo) {
    getAccessToken($pdo); // Ensure access token is fetched

    $accessToken = $_SESSION['access_token'] ?? null;
    if (!$accessToken) {
        error_log("Error: Missing Zoho access token.");
        return false;
    }

    $module = 'Leads';
    $apiUrl = "https://www.zohoapis.com.au/crm/v2/$module";

    $data = ['data' => [$mappedData]];
    $jsonData = json_encode($data);

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

    if ($httpCode === 201) {
        return true;
    } else {
        $errorData = json_decode($response, true);
        error_log("Zoho API Error ({$httpCode}): " . ($errorData['message'] ?? $response));
        return false;
    }
}

// Main logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mappedData = mapFormFields($_POST);

    // Debugging: Check if full name is empty
    if (empty($mappedData['Last_Name'])) {
        error_log("Error: Full Name is missing in form submission.");
    }

    if (empty($mappedData['Last_Name']) || empty($mappedData['Email']) || empty($mappedData['Phone'])) {
        header("Location: /error.html");
        exit;
    }

    // Email function
    $userName = htmlspecialchars($mappedData['Last_Name']);
    $email = htmlspecialchars($mappedData['Email']);
    $contact = htmlspecialchars($mappedData['Phone']);
    $msg = htmlspecialchars($mappedData['Message']);
    $sub = htmlspecialchars($mappedData['Subject']);

    $to = "info@ilinkinsurance.com.au, smartsolutions.designstudio@gmail.com,quotes@ilinkinsurance.com.au,smartsolutions.designstudio1@gmail.com";
    $subject = "New Inquiry from Asbestos Insurance";

    $message = "
    <html>
            <head>
              <title>Insurance Inquiry For Asbestos Insurance</title>
              <style>
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                th { background-color: #f2f2f2; }
              </style>
            </head>
            <body>
              <h2>New Insurance Inquiry For Asbestos Insurance</h2>
              <table>
                <tr><th>Full Name</th><td>$userName</td></tr>
                <tr><th>Email</th><td>$email</td></tr>
                <tr><th>Phone Number</th><td>$contact</td></tr>
                <tr><th>Subject</th><td>$sub</td></tr>
                <tr><th>Message</th><td>$msg</td></tr>
              </table>
            </body>
    </html>";

    $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: no-reply@ilinkinsurance.com.au\r\n";
            $headers .= "Reply-To: $userEmail\r\n";

    if (mail($to, $subject, $message, $headers)) {
        header("Location: /thankyou.html");
        
    } else {
        error_log("Error: Email failed to send.");
        header("Location: /error.html");
        
    }

    // Insert into database and Zoho
    if (insertDataIntoDatabase($mappedData, $pdo)) {
        if (addRecordToZoho($mappedData, $pdo)) {
            header("Location: /thankyou.html");
            
        } else {
            error_log("Error: Failed to add record to Zoho.");
            header("Location: /error.html");
            exit;
            
        }
    } else {
        error_log("Error: Failed to insert into database.");
        header("Location: /error.html");
        
    }
} else {
    header("Location: /error.html");
    
}
