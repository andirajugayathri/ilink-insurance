<?php
// Including DB File
ob_start(); 
session_start(); // Add this for Zoho access token
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

// Add debugging function
function debugLog($message, $data = null) {
    $logMessage = date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $logMessage .= " - Data: " . print_r($data, true);
    }
    error_log($logMessage);
    // Also output to screen for debugging (remove in production)
    echo "<pre>DEBUG: " . htmlspecialchars($logMessage) . "</pre>";
}

/*****
 * Step 1: Form validation
 * Step 2: Collecting the Form Fields 
 * Step 3: Inserting into DB (cyber_form)
 * Step 4: Sending data to Zoho CRM
 * Step 5: Sending email notification
 */

// Main logic - Check if POST request
debugLog("Request method: " . $_SERVER['REQUEST_METHOD']);
debugLog("POST data received", $_POST);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debugLog("ERROR: Not a POST request");
    header("Location: /error.html");
    exit;
}

// Optional: Math verification (if you want to keep it)
// You can remove this section if you don't need math verification
/*
if (isset($_POST['math_verification']) && isset($_POST['correct_answer'])) {
    $userAnswer = trim($_POST['math_verification']);
    $correctAnswer = trim($_POST['correct_answer']);
    
    if ($userAnswer !== $correctAnswer) {
        debugLog("ERROR: Math verification failed", ["user" => $userAnswer, "correct" => $correctAnswer]);
        header("Location: /error.html");
        exit;
    }
    debugLog("Math verification successful");
}
*/

// Map form field names to match Zoho CRM
function mapFormFields($formData) {
    return [
        'Last_Name' => $formData['full-name'] ?? '',
        'Company' => $formData['company-name'] ?? '',
        'Phone' => $formData['contact'] ?? '',
        'Email' => $formData['email'] ?? '',
        'Contact_Occupation' => $formData['occupation'] ?? '',
        'Estimated_Annual_Turnover_For_The_Business' => $formData['turnover'] ?? '',
        'Total_Number_Of_Employees' => $formData['employees'] ?? '',
        'Layout'=> [
            'name'=> 'Website',
            'id' => '62950000001318018'
        ],
        'Owner'=>[
            'name'=> 'Shalin Shah',
            'id'=> '62950000000229001'
        ],
        'Enquiry_Source' => 'ILink Website - Cyber Insurance Form',
        'Product_Inquiry' => 'Cyber Insurance'
    ];
}

// Function to insert data into the cyber_form database
function insertDataIntoDatabase($mappedData, $pdo) {
    try {
        debugLog("Attempting to insert data into database", $mappedData);
        
        $sql = "INSERT INTO Cyber_insurance(
                    full_name, 
                    company_name, 
                    contact_no, 
                    email, 
                    occupation, 
                    turnover, 
                    employees
                   
                ) 
                VALUES (
                    :full_name, 
                    :company_name, 
                    :contact_no, 
                    :email, 
                    :occupation, 
                    :turnover, 
                    :employees
                    
                )";

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':full_name' => $mappedData['Last_Name'],
            ':company_name' => $mappedData['Company'],
            ':contact_no' => $mappedData['Phone'],
            ':email' => $mappedData['Email'],
            ':occupation' => $mappedData['Contact_Occupation'],
            ':turnover' => $mappedData['Estimated_Annual_Turnover_For_The_Business'],
            ':employees' => $mappedData['Total_Number_Of_Employees']
        ]);

        debugLog("Database insertion result", $result);
        return $result;
    } catch (PDOException $e) {
        debugLog("Database Error: " . $e->getMessage());
        error_log("Database Error: " . $e->getMessage());
        return false;
    }
}

// Function to send data to Zoho CRM
function addRecordToZoho($mappedData, $pdo) {
    debugLog("Starting Zoho CRM integration");
    
    try {
        getAccessToken($pdo); // Ensure access token is fetched
    } catch (Exception $e) {
        debugLog("Error getting access token: " . $e->getMessage());
        return false;
    }

    // Retrieve access token from session
    $accessToken = $_SESSION['access_token'] ?? null;
    debugLog("Access token retrieved", $accessToken ? "Token exists" : "No token");
    
    if (!$accessToken) {
        debugLog("Error: Missing Zoho access token.");
        error_log("Error: Missing Zoho access token.");
        return false;
    }

    // Zoho API endpoint
    $module = 'Leads';
    $apiUrl = "https://www.zohoapis.com.au/crm/v2/$module";

    // Prepare the data
    $data = ['data' => [$mappedData]];
    $jsonData = json_encode($data);

    debugLog("Zoho API URL", $apiUrl);
    debugLog("Zoho data to send", $data);

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
    
    debugLog("Zoho API HTTP Code", $httpCode);
    debugLog("Zoho API Response", $response);
    
    if (curl_error($ch)) {
        debugLog("cURL Error: " . curl_error($ch));
    }
    
    curl_close($ch);

    if ($httpCode === 201) {
        debugLog("Zoho CRM record created successfully");
        return true;
    } else {
        $errorData = json_decode($response, true);
        $errorMessage = "Zoho API Error ({$httpCode}): " . ($errorData['message'] ?? $response);
        debugLog($errorMessage);
        error_log($errorMessage);
        return false;
    }
}

// Process the form
debugLog("Processing form data");
$mappedData = mapFormFields($_POST);
debugLog("Mapped form data", $mappedData);

// Validate required fields
$requiredFields = ['Last_Name', 'Email', 'Phone', 'Contact_Occupation', 'Estimated_Annual_Turnover_For_The_Business', 'Total_Number_Of_Employees'];
$missingFields = [];

foreach ($requiredFields as $field) {
    if (empty($mappedData[$field])) {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    debugLog("ERROR: Missing required fields", $missingFields);
    header("Location: /error.html");
    exit;
}

debugLog("All required fields present");

// Step 1: Insert data into the database
debugLog("Step 1: Inserting data into database");
if (insertDataIntoDatabase($mappedData, $pdo)) {
    debugLog("Database insertion successful");
    
    // Step 2: Add record to Zoho CRM
    debugLog("Step 2: Adding record to Zoho CRM");
    if (addRecordToZoho($mappedData, $pdo)) {
        debugLog("Zoho CRM integration successful");
        
        // Step 3: Send email notification
        debugLog("Step 3: Sending email notification");
        $userName = htmlspecialchars($mappedData['Last_Name']);
        $company = htmlspecialchars($mappedData['Company']);
        $contact = htmlspecialchars($mappedData['Phone']);
        $email = htmlspecialchars($mappedData['Email']);
        $occupation = htmlspecialchars($mappedData['Contact_Occupation']);
        $turnover = htmlspecialchars($mappedData['Estimated_Annual_Turnover_For_The_Business']);
        $total_emp = htmlspecialchars($mappedData['Total_Number_Of_Employees']);

        $to = "info@ilinkinsurance.com.au, smartsolutions.designstudio@gmail.com, quotes@ilinkinsurance.com.au, smartsolutions.designstudio1@gmail.com";
        $subject = "New enquiry from cyber insurance";
        $message = "
        <html>
                <head>
                  <title>Insurance Inquiry for cyber insurance</title>
                  <style>
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                    th { background-color: #f2f2f2; }
                  </style>
                </head>
                <body>
                  <h2>New Insurance Inquiry - Cyber Insurance</h2>
                  <table>
                    <tr><th>Full Name</th><td>$userName</td></tr>
                    <tr><th>Company</th><td>$company</td></tr>
                    <tr><th>Phone Number</th><td>$contact</td></tr>
                    <tr><th>Email</th><td>$email</td></tr>
                    <tr><th>Occupation</th><td>$occupation</td></tr>
                    <tr><th>Estimated Annual Turnover</th><td>$turnover</td></tr>
                    <tr><th>Total Employees</th><td>$total_emp</td></tr>
                  </table>
                </body>
                </html>
       ";

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: no-reply@ilinkinsurance.com.au\r\n";
        $headers .= "Reply-To: $email\r\n";

        // Send email and redirect to success page
        $emailResult = mail($to, $subject, $message, $headers);
        debugLog("Email send result", $emailResult ? "Success" : "Failed");
        
        debugLog("Redirecting to success page");
        header("Location: /thankyou.html");
        exit;
    } else {
        debugLog("ERROR: Zoho CRM integration failed");
        header("Location: /error.html");
        exit;
    }
} else {
    debugLog("ERROR: Database insertion failed");
    header("Location: /error.html");
    exit;
}
?>