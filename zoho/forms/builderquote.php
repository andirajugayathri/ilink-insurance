<?php
// Start session for Zoho token
session_start();
ob_start();
include 'db.php';
include '../access-token.php';

/*****
 * Step 1: Collecting the Form Fields 
 * Step 2: Inserting into DB
 * Step 3: Sending data to Zoho CRM
 * Step 4: Sending Email Notification
 */
 
 //to avoid junk leads
 
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
        'Last_Name' => $formData['full_name'] ?? '',
        'Email' => $formData['email'] ?? '',
        'Phone' => $formData['contact'] ?? '',
        'Subject' => $formData['subject'] ?? '',
        'Message' => $formData['message'] ?? '',
      'Layout'=> [
            'name'=> 'Website',
            'id' => '62950000001318018'
        ],
         'Owner'=>[
                'name'=> 'Shalin Shah',
            'id'=> '62950000000229001'
            
                   ],
        'Enquiry_Source' => 'ILink Website - Call Back Form',
'Product_Inquiry' => 'Builder insurnace'
    ];
}

// Function to insert data into the database
function insertDataIntoDatabase($mappedData, $pdo) {
    try {
        $sql = "INSERT INTO Builder_insurance(
                    full_name, 
                    email, 
                    contact, 
                    subject, 
                    message
                ) 
                VALUES (
                    :name, 
                    :email, 
                    :contact, 
                    :subject, 
                    :message
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $mappedData['Last_Name'],
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

    // Retrieve access token from session
    $accessToken = $_SESSION['access_token'] ?? null;
    if (!$accessToken) {
        error_log("Error: Missing Zoho access token.");
        return false;
    }

    // Zoho API endpoint
    $module = 'Leads';
    $apiUrl = "https://www.zohoapis.com.au/crm/v2/$module";

    // Prepare the data
    $data = ['data' => [$mappedData]];
    $jsonData = json_encode($data);

    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Zoho-oauthtoken $accessToken",
        "Content-Type: application/json"
    ]);

    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 201 || $httpCode === 202) {
        return true;
    } else {
        error_log("Zoho API Error ({$httpCode}): " . ($curlError ?: $response));
        return false;
    }
}

// Function to send an email
function sendMail($mappedData) {
    $to = "info@ilinkinsurance.com.au, smartsolutions.designstudio@gmail.com, quotes@ilinkinsurance.com.au,smartsolutions.designstudio1@gmail.com"; // Change to recipient email
    $subject = "New Builder Form Submission";
    $message = "<html><body>";
    $message="<h1>New Insurance Inquery For Builder Insurance</h1>";
    $message .= "<table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
    $message .= "<tr><th style='background-color: #f2f2f2;'>Field</th><th>Value</th></tr>";
    $message .= "<tr><td><strong>Name:</strong></td><td>" . $mappedData['Last_Name'] . "</td></tr>";
    $message .= "<tr><td><strong>Email:</strong></td><td>" . $mappedData['Email'] . "</td></tr>";
    $message .= "<tr><td><strong>Phone:</strong></td><td>" . $mappedData['Phone'] . "</td></tr>";
    $message .= "<tr><td><strong>Subject:</strong></td><td>" . $mappedData['Subject'] . "</td></tr>";
    $message .= "<tr><td><strong>Message:</strong></td><td>" . nl2br($mappedData['Message']) . "</td></tr>";
    $message .= "</table>";
    $message .= "</body></html>";
    
      $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: no-reply@ilinkinsurance.com.au\r\n";
            $headers .= "Reply-To: $userEmail\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Main logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mappedData = mapFormFields($_POST);

    // Validate required fields
    if (empty($mappedData['Last_Name']) || empty($mappedData['Email']) || empty($mappedData['Phone'])) {
        header("Location: error.html");
        exit;
    }

    // Step 1: Insert data into the database
    if (insertDataIntoDatabase($mappedData, $pdo)) {
        // Step 2: Add record to Zoho CRM
        if (addRecordToZoho($mappedData, $pdo)) {
            sendMail($mappedData); // Send email notification
            header("Location: /thankyou.html");
            exit;
        } else {
            header("Location: /error.html");
            exit;
        }
    } else {
        header("Location: /error.html");
        exit;
    }
} else {
    header("Location: /error.html");
    exit;
}
