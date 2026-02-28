<?php
// Start output buffering and session
ob_start();
session_start();

// Include necessary files
include 'db.php';
include '../access-token.php';

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

// 3️⃣ Email confirmation
$email = $_POST['email'] ?? '';
$confirmEmail = $_POST['confirmEmail'] ?? '';
if (empty($email) || empty($confirmEmail) || $email !== $confirmEmail) {
    error_log("Email confirmation failed | IP: {$_SERVER['REMOTE_ADDR']}");
    header("Location: /error.html");
    exit();
}

// ------------------- FORM FIELD MAPPING -------------------
function mapFormFields($formData) {
    return [
        'Last_Name' => $formData['name'] ?? '',
        'Phone' => $formData['contact'] ?? '',
        'Email' => $formData['email'] ?? '',
        'Suburb' => $formData['city'] ?? '',
        'Product_Inquiry' => $formData['insurance'] ?? '',
        'Description' => $formData['description'] ?? '',
        'Layout'=> ['name'=> 'Website', 'id' => '62950000001318018'],
        'Owner'=> ['name'=> 'Shalin Shah', 'id'=> '62950000000229001'],
        'Enquiry_Source' => 'ILink Website - Call Back Form'
    ];
}

// ------------------- DATABASE INSERT -------------------
function insertDataIntoDatabase($mappedData, $pdo) {
    try {
        $sql = "INSERT INTO Ilink_insurance_homepage(name, email, contact, city, insurance, description) 
                VALUES (:name, :email, :contact, :city, :insurance, :description)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':name' => $mappedData['Last_Name'],
            ':email' => $mappedData['Email'],
            ':contact' => $mappedData['Phone'],
            ':city' => $mappedData['Suburb'],
            ':insurance' => $mappedData['Product_Inquiry'],
            ':description' => $mappedData['Description']
        ]);
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        return false;
    }
}

// ------------------- ZOHO SUBMISSION -------------------
function addRecordToZoho($mappedData, $pdo) {
    getAccessToken($pdo);
    $accessToken = $_SESSION['access_token'] ?? null;
    if (!$accessToken) {
        error_log("Missing Zoho access token.");
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Zoho-oauthtoken $accessToken",
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode === 201;
}

// ------------------- MAIN LOGIC -------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mappedData = mapFormFields($_POST);

    if (empty($mappedData['Last_Name']) || empty($mappedData['Email']) || empty($mappedData['Phone'])) {
        header("Location: /error.html");
        exit();
    }

    if (insertDataIntoDatabase($mappedData, $pdo)) {
        if (addRecordToZoho($mappedData, $pdo)) {
            // Send email notification
            $fullName = htmlspecialchars($_POST['name']);
            $userEmail = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) ? $_POST['email'] : null;
            $phoneNumber = htmlspecialchars($_POST['contact']);
            $userCity = htmlspecialchars($_POST['city']);
            $serviceType = htmlspecialchars($_POST['insurance']);
            $userMessage = nl2br(htmlspecialchars($_POST['description']));

            if ($userEmail) {
                $to = "info@ilinkinsurance.com.au, quotes@ilinkinsurance.com.au";
                $subject = "New Insurance Inquiry from iLink Insurance";

                $message = "
                <html>
                <head><title>Insurance Inquiry</title></head>
                <body>
                  <h2>New Insurance Inquiry</h2>
                  <p><strong>Name:</strong> $fullName</p>
                  <p><strong>Email:</strong> $userEmail</p>
                  <p><strong>Phone:</strong> $phoneNumber</p>
                  <p><strong>City:</strong> $userCity</p>
                  <p><strong>Insurance:</strong> $serviceType</p>
                  <p><strong>Message:</strong> $userMessage</p>
                </body>
                </html>";

                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8\r\n";
                $headers .= "From: no-reply@ilinkinsurance.com.au\r\n";

                if (mail($to, $subject, $message, $headers)) {
                    header("Location: /thankyou.html");
                    exit();
                }
            }
        }
    }
}

header("Location: /error.html");
exit();
?>
