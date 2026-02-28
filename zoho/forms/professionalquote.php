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
        'Renewal_date_proposed_start_date' => $formData['date-input'] ?? '',
        'Last_Name' => $formData['full-name'] ?? '',
        'Company' => $formData['business-name'] ?? '',
        'Email' => $formData['email'] ?? '',
        'Phone' => $formData['contact'] ?? '',
        'Address_Line1' => $formData['business-address'] ?? '',
        'Description' => $formData['business-description'] ?? '',
        'Profession_Indemnity_PI_Required' => $formData['pi-required'] ?? '',
        'Public_Liability_PL_Required' => $formData['pl-required'] ?? '',
        'Layout'=> [
            'name'=> 'Website',
            'id' => '62950000001318018'
        ],
         'Owner'=>[
                'name'=> 'Shalin Shah',
            'id'=> '62950000000229001'
        ],
        'Enquiry_Source' => 'ILink Website - Call Back Form',
        'Product_Inquiry' => 'Professional Indemnity Insurance'
    ];
}

// Function to insert data into the hotel_insurance database
function insertDataIntoDatabase($mappedData, $pdo) {
    try {
        $sql = "INSERT INTO Professional_Indemnity_Insurance(
                    date_input, 
                    full_name, 
                    company_name, 
                    email, 
                    contact_number, 
                    business_address,
                    business_description,
                    pi_required,
                    pl_required
                ) 
                VALUES (
                    :date_input, 
                    :full_name, 
                    :company_name, 
                    :email, 
                    :contact_number, 
                    :business_address,
                    :business_description,
                    :pi_required,
                    :pl_required
                )";

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':date_input' => $mappedData['Renewal_date_proposed_start_date'],
            ':full_name' => $mappedData['Last_Name'],
            ':company_name' => $mappedData['Company'],
            ':email' => $mappedData['Email'],
            ':contact_number' => $mappedData['Phone'],
            ':business_address' => $mappedData['Address_Line1'],
            ':business_description' => $mappedData['Description'],
            ':pi_required' => $mappedData['Profession_Indemnity_PI_Required'],
            ':pl_required' => $mappedData['Public_Liability_PL_Required']
        ]);

        logError("Database insertion successful");
        return true;
    } catch (PDOException $e) {
        logError("Database Error: " . $e->getMessage());
        echo "Database Error: " . $e->getMessage();
        return false;
    }
}

// Function to send data to Zoho CRM
function addRecordToZoho($mappedData, $pdo) {
    try {
        getAccessToken($pdo); // Ensure access token is fetched

        // Retrieve access token from session
        $accessToken = $_SESSION['access_token'] ?? null;
        if (!$accessToken) {
            logError("Error: Missing Zoho access token");
            echo "Error: Missing Zoho access token.";
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
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Zoho-oauthtoken $accessToken",
            "Content-Type: application/json"
        ]);

        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 201) {
            logError("Zoho API success: " . $httpCode);
            return true;
        } else {
            $errorData = json_decode($response, true);
            $errorMsg = "Zoho API Error ({$httpCode}): " . ($errorData['message'] ?? $response);
            logError($errorMsg);
            echo $errorMsg;
            return false;
        }
    } catch (Exception $e) {
        logError("Zoho API Exception: " . $e->getMessage());
        return false;
    }
}

// Main logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Log the received POST data
    logError("POST data received: " . print_r($_POST, true));
    
    $mappedData = mapFormFields($_POST);
    
    // Debug: Log the mapped data
    logError("Mapped data: " . print_r($mappedData, true));

    // Validate required fields
    if (empty($mappedData['Last_Name']) || empty($mappedData['Email']) || empty($mappedData['Phone'])) {
        logError("Validation failed - missing required fields: Name=" . $mappedData['Last_Name'] . ", Email=" . $mappedData['Email'] . ", Phone=" . $mappedData['Phone']);
        header("Location: error.html");
        exit;
    }

    // Step 1: Insert data into the database
    if (insertDataIntoDatabase($mappedData, $pdo)) {
        logError("Database insertion completed successfully");

        // Prepare email data
        $userName = htmlspecialchars($mappedData['Last_Name']);
        $business = htmlspecialchars($mappedData['Company']);
        $email = htmlspecialchars($mappedData['Email']);
        $contact = htmlspecialchars($mappedData['Phone']);
        $business_address = htmlspecialchars($mappedData['Address_Line1']);
        $business_des = htmlspecialchars($mappedData['Description']);
        $pi = htmlspecialchars($mappedData['Profession_Indemnity_PI_Required']);
        $pl = htmlspecialchars($mappedData['Public_Liability_PL_Required']);

        $to = "info@ilinkinsurance.com.au, smartsolutions.designstudio@gmail.com,quotes@ilinkinsurance.com.au,smartsolutions.designstudio1@gmail.com";
        $subject = "New inquiry from Professional Indemnity Insurance";

        $message = " <html>
            <head>
              <title>Insurance Inquiry for Professional Indemnity Insurance</title>
              <style>
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                th { background-color: #f2f2f2; }
              </style>
            </head>
            <body>
              <h2>New Insurance Inquiry for Professional Indemnity Insurance</h2>
              <table>
                <tr><th>Full Name</th><td>$userName</td></tr>
                <tr><th>Business Name</th><td>$business</td></tr>
                <tr><th>Email</th><td>$email</td></tr>
                <tr><th>Phone Number</th><td>$contact</td></tr>
                <tr><th>Business Address</th><td>$business_address</td></tr>
                <tr><th>Business Description</th><td>$business_des</td></tr>
                <tr><th>Professional Indemnity (PI) Required</th><td>$pi</td></tr>
                <tr><th>Public Liability (PL) Required</th><td>$pl</td></tr>
              </table>
            </body>
            </html>";

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: no-reply@ilinkinsurance.com.au\r\n";
        $headers .= "Reply-To: $email\r\n"; // Fixed: was using undefined $userEmail

        // Send email
        if (mail($to, $subject, $message, $headers)) {
            logError("Email sent successfully");
        } else {
            logError("Email sending failed");
            echo "Mail sending failed.";
        }

        // Step 2: Add record to Zoho CRM
        if (addRecordToZoho($mappedData, $pdo)) {
            logError("Zoho CRM record added successfully - redirecting to thank you page");
            header("Location: /thankyou.html");
            exit;
        } else {
            logError("Zoho CRM failed - redirecting to error page");
            header("Location: /error.html");
            exit;
        }
    } else {
        logError("Database insertion failed - redirecting to error page");
        header("Location: /error.html");
        exit;
    }
} else {
    logError("Not a POST request - redirecting to error page");
    header("Location: /error.html");
    exit;
}
?>