<?php
// Including DB File
ob_start();
include 'db.php';
include '../access-token.php';

/*****
 * Step 1: Collecting the Form Fields 
 * Step 2: Inserting into DB (scaffolding)
 * Step 3: Sending data to Zoho CRM
 */


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
function mapFormFields($formData)
{
    return [
        'Last_Name' => $formData['full-name'] ?? '',
        'Company' => $formData['company-name'] ?? '',
        'Phone' => $formData['contact-number'] ?? '',
        'Email' => $formData['email'] ?? '',
        'Public_Liability_cover_required' => $formData['liability-cover'] ?? '',
        'Any_Other_Insurances_You_Need' => isset($formData['other-insurances']) && is_array($formData['other-insurances']) ? implode(", ", $formData['other-insurances']) : ($formData['other-insurances'] ?? ''),
        'Layout' => [
            'name' => 'Website',
            'id' => '62950000001318018'
        ],
        'Owner' => [
            'name' => 'Shalin Shah',
            'id' => '62950000000229001'

        ],
        'Enquiry_Source' => 'ILink Website - Call Back Form',
        'Product_Inquiry' => 'Scaffolding Insurance'
    ];
}

// Function to insert data into the scaffolding database
function insertDataIntoDatabase($mappedData, $pdo)
{
    try {
        $sql = "INSERT INTO scaffolding_insurance(
                    full_name, 
                    company_name, 
                    contact_number, 
                    email, 
                    liability_cover,
                    other_insurances
                   
                ) 
                VALUES (
                    :full_name, 
                    :company_name, 
                    :contact_number, 
                    :email, 
                    :liability_cover,
                    :other_insurances
                    
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':full_name' => $mappedData['Last_Name'],
            ':company_name' => $mappedData['Company'],
            ':contact_number' => $mappedData['Phone'],
            ':email' => $mappedData['Email'],
            ':liability_cover' => $mappedData['Public_Liability_cover_required'],
            ':other_insurances' => $mappedData['Any_Other_Insurances_You_Need']
        ]);

        return true;
    } catch (PDOException $e) {
        echo "Database Error: " . $e->getMessage();
        return false;
    }
}

// Function to send data to Zoho CRM
function addRecordToZoho($mappedData, $pdo)
{
    getAccessToken($pdo); // Ensure access token is fetched

    // Retrieve access token from session
    $accessToken = $_SESSION['access_token'] ?? null;
    if (!$accessToken) {
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
        return true;
    } else {
        $errorData = json_decode($response, true);
        echo "Zoho API Error ({$httpCode}): " . ($errorData['message'] ?? $response);
        return false;
    }
}

// Main logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mappedData = mapFormFields($_POST);

    // Validate required fields
    if (empty($mappedData['Last_Name']) || empty($mappedData['Email']) || empty($mappedData['Phone'])) {
        header("Location: error.html");
        exit;
    }
    // Sending Email
    $userName = htmlspecialchars($mappedData['Last_Name']);
    $company = htmlspecialchars($mappedData['Company']);
    $contact = htmlspecialchars($mappedData['Phone']);
    $email = htmlspecialchars($mappedData['Email']);
    $turnover = ""; // Removed
    $pl = htmlspecialchars($mappedData['Public_Liability_cover_required']);
    $employees = ""; // Removed
    $otherInsurances = htmlspecialchars($mappedData['Any_Other_Insurances_You_Need']);

    $to = "quotes@ilinkinsurance.com.au, smartsolutions.designstudio@gmail.com,smartsolutions.designstudio1@gmail.com";
    $subject = "New Enquiry from scaffold insurance page";

    $message = "
<html>
    <head>
        <title>Insurance Inquiry For Scaffolding Insurance</title>
        <style>
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            th { background-color: #f2f2f2; }
        </style>
    </head>
    <body>
        <h2>New Insurance Inquiry</h2>
        <table>
            <tr><th>Full Name</th><td>$userName</td></tr>
            <tr><th>Email</th><td>$email</td></tr>
            <tr><th>Phone Number</th><td>$contact</td></tr>
            <tr><th>Company</th><td>$company</td></tr>
            <tr><th>Public Liability Cover</th><td>$pl</td></tr>
            <tr><th>Any Other Insurance</th><td>$otherInsurances</td></tr>
        </table>
    </body>
</html>
";

    // Corrected Headers
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: no-reply@ilinkinsurance.com.au\r\n";
    $headers .= "Reply-To: $userEmail\r\n";

    if (mail($to, $subject, $message, $headers)) {
        header("Location:/thankyou.html");
    } else {
        echo "Error sending email.";
    }







    // Step 1: Insert data into the database
    if (insertDataIntoDatabase($mappedData, $pdo)) {
        // Step 2: Add record to Zoho CRM
        if (addRecordToZoho($mappedData, $pdo)) {
            header("Location: /thankyou.html");

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
