<?php
session_start();
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

function mapFormFields($formData) {
    return [
        'Last_Name' => $formData['full_name'] ?? '',
        'Company' => $formData['company_name'] ?? '',
        'Phone' => $formData['contact'] ?? '',
        'Email' => $formData['email'] ?? '',
        'Coverage_Required' => isset($formData['coverage']) ? $formData['coverage'] : [],
        'Layout' => [
            'name' => 'Website',
            'id' => '62950000001318018'
        ],
        'Owner' => [
            'name' => 'Shalin Shah',
            'id' => '62950000000229001'
        ],
        'Enquiry_Source' => 'ILink Website - Call Back Form',
        'Product_Inquiry' => 'Commercial Property Insurance'
    ];
}

function insertDataIntoDatabase($mappedData, $pdo) {
    try {
        $sql = "INSERT INTO Commercial_property_insurance (
                    full_name, company_name, contact, email, coverage
                ) VALUES (
                    :full_name, :company_name, :contact, :email, :coverage
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':full_name' => $mappedData['Last_Name'],
            ':company_name' => $mappedData['Company'],
            ':contact' => $mappedData['Phone'],
            ':email' => $mappedData['Email'],
            ':coverage' => json_encode($mappedData['Coverage_Required'])
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        return false;
    }
}

function addRecordToZoho($mappedData, $pdo) {
    getAccessToken($pdo);
    $accessToken = $_SESSION['access_token'] ?? null;

    if (!$accessToken) {
        error_log("Missing Zoho access token.");
        return false;
    }

    $apiUrl = "https://www.zohoapis.com.au/crm/v2/Leads";
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

// Main logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
   $formData = array_map(function($value) {
    return is_array($value) ? $value : trim($value);
}, $_POST);


    // CAPTCHA validation
    $userAnswer = $formData['math_verification'] ?? '';
    $correctAnswer = $formData['correct_answer'] ?? '';
    if ($userAnswer !== $correctAnswer) {
        header("Location: /error.html");
        exit;
    }

    $mappedData = mapFormFields($formData);

    if (empty($mappedData['Last_Name']) || empty($mappedData['Email']) || empty($mappedData['Phone'])) {
        header("Location: /error.html");
        exit;
    }

    // Insert into DB
    if (insertDataIntoDatabase($mappedData, $pdo)) {
        // Send to Zoho
        if (addRecordToZoho($mappedData, $pdo)) {
            // Send Email
            $userName = htmlspecialchars($mappedData["Last_Name"]);
            $company = htmlspecialchars($mappedData["Company"]);
            $contact = htmlspecialchars($mappedData["Phone"]);
            $email = htmlspecialchars($mappedData["Email"]);
            $coverage = is_array($mappedData['Coverage_Required']) 
                        ? implode(', ', array_map('htmlspecialchars', $mappedData['Coverage_Required'])) 
                        : htmlspecialchars($mappedData['Coverage_Required']);

            $to = "info@ilinkinsurance.com.au, smartsolutions.designstudio@gmail.com, quotes@ilinkinsurance.com.au, smartsolutions.designstudio1@gmail.com";
            $subject = "New Insurance Inquiry from Commercial Property Insurance";
            $message = "
            <html>
                <head>
                    <title>Insurance Inquiry</title>
                    <style>
                        table { width: 100%; border-collapse: collapse; }
                        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                        th { background-color: #f2f2f2; }
                    </style>
                </head>
                <body>
                    <h2>New Insurance Inquiry from Commercial Property Insurance</h2>
                    <table>
                        <tr><th>Full Name</th><td>$userName</td></tr>
                        <tr><th>Company</th><td>$company</td></tr>
                        <tr><th>Phone Number</th><td>$contact</td></tr>
                        <tr><th>Email</th><td>$email</td></tr>
                        <tr><th>Coverage</th><td>$coverage</td></tr>
                    </table>
                </body>
            </html>";

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: no-reply@ilinkinsurance.com.au\r\n";
            $headers .= "Reply-To: $email\r\n";

            mail($to, $subject, $message, $headers);

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
