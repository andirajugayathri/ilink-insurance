<?php
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

// Map form data to Zoho-compatible structure
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
        'Enquiry_Source' => 'ILink Website - Call Back Form',
'Product_Inquiry' => 'Cyber Insurance'
    ];
}

function insertDataIntoDatabase($mappedData, $pdo) {
    try {
        $sql = "INSERT INTO Cyber_insurance(
                    full_name,
                    company_name,
                    contact_no,
                    email,
                    occupation,
                    turnover,
                    employees
                ) VALUES (
                    :full_name,
                    :company_name,
                    :contact,
                    :email,
                    :occupation,
                    :turnover,
                    :employees
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':full_name' => $mappedData['Last_Name'],
            ':company_name' => $mappedData['Company'],
            ':contact' => $mappedData['Phone'],
            ':email' => $mappedData['Email'],
            ':occupation' => $mappedData['Contact_Occupation'],
            ':turnover' => $mappedData['Estimated_Annual_Turnover_For_The_Business'],
            ':employees' => $mappedData['Total_Number_Of_Employees']
        ]);

        return true;
    } catch (PDOException $err) {
        error_log("DB Error: " . $err->getMessage());
        return false;
    }
}

function addRecordToZoho($mappedData, $pdo) {
    getAccessToken($pdo);
    $accessToken = $_SESSION['access_token'] ?? null;

    if (!$accessToken) {
        error_log("Zoho token not available.");
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
        error_log("Zoho API Error ($httpCode): " . ($errorData['message'] ?? $response));
        return false;
    }
}

function sendEmailNotification($mappedData) {
    $userEmail = $mappedData['Email']; // Fixed field reference

    $to = "info@ilinkinsurance.com.au, smartsolutions.designstudio@gmail.com, quotes@ilinkinsurance.com.au,madhu@smartsolutionsdigi.com";
    $subject = "New Cyber Insurance Inquiry";
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: no-reply@ilinkinsurance.com.au\r\n";
    $headers .= "Reply-To: $userEmail\r\n";

    $message = "
    <html>
    <head>
        <title>New Cyber Insurance Inquiry</title>
        <style>
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 10px;
                text-align: left;
            }
            th {
                background-color: #f2f2f2;
            }
            body {
                font-family: Arial, sans-serif;
            }
        </style>
    </head>
    <body>
        <h2>New Cyber Insurance Inquiry Received</h2>
        <table>
            <tr><th>Field</th><th>Details</th></tr>
            <tr><td><strong>Full Name</strong></td><td>{$mappedData['Last_Name']}</td></tr>
            <tr><td><strong>Email</strong></td><td>{$mappedData['Email']}</td></tr>
            <tr><td><strong>Phone</strong></td><td>{$mappedData['Phone']}</td></tr>
            <tr><td><strong>Occupation</strong></td><td>{$mappedData['Contact_Occupation']}</td></tr>
            <tr><td><strong>Estimated Turnover of Business</strong></td><td>{$mappedData['Estimated_Annual_Turnover_For_The_Business']}</td></tr>
            <tr><td><strong>Total Number of Employees</strong></td><td>{$mappedData['Total_Number_Of_Employees']}</td></tr>   
        </table>
    </body>
    </html>";

    return mail($to, $subject, $message, $headers);
}

// Main logic
$mappedData = mapFormFields($_POST);

if (insertDataIntoDatabase($mappedData, $pdo)) {
    if (sendEmailNotification($mappedData) && addRecordToZoho($mappedData, $pdo)) {
        header("Location: /thankyou.html");
        error_log("Data sent to Zoho and email successfully.");
        exit;
    } else {
        header("Location: /error.html");
        error_log("Data failed to send to email or Zoho.");
        exit;
    }
} else {
    header("Location: /error.html");
    error_log("Database insertion failed.");
    exit;
}
?>
