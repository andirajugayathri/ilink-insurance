<?php
// Start output buffering
ob_start(); 

// Include necessary files
include 'db.php';
include '../access-token.php';

//to aboid junk leads


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





// Step 1: Collect form data and map to Zoho CRM fields
function mapFormFields($formData) {
    return [
        'Last_Name' => $formData['name'] ?? '',
        'Phone' => $formData['contact'] ?? '',
        'Email' => $formData['email'] ?? '',
        'Suburb' => $formData['city'] ?? '',
       'Product_Inquiry' => $formData['insurance'] ?? '',
        'Description' => $formData['description'] ?? '',
     'Layout' => [
            'name' => 'Website',
            'id' => '62950000001318018'
            ],
         'Owner'=>[
                'name'=> 'Shalin Shah',
            'id'=> '62950000000229001'
            
                   ],
        'Enquiry_Source' => 'ILink Website - Call Back Form',
        
    ];
}

// Function to insert data into the database
function insertDataIntoDatabase($mappedData, $pdo) {
    try {
        $sql = "INSERT INTO aboutpage(name, email, contact, city, insurance, description) 
                VALUES (:name, :email, :contact, :city, :insurance, :description)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $mappedData['Last_Name'],
            ':email' => $mappedData['Email'],
            ':contact' => $mappedData['Phone'],
            ':city' => $mappedData['Suburb'],
            ':insurance' => $mappedData['Product_Inquiry'],
            ':description' => $mappedData['Description']
        ]);

        return true;
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        return false;
    }
}

// Function to send data to Zoho CRM
function addRecordToZoho($mappedData, $pdo) {
    getAccessToken($pdo);
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
        error_log("Zoho API Error ({$httpCode}): " . $response);
        return false;
    }
}

// Main logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mappedData = mapFormFields($_POST);

    
    // Insert data into the database
    if (insertDataIntoDatabase($mappedData, $pdo)) {
        if (addRecordToZoho($mappedData, $pdo)) {
            // Send Email
            $fullName = htmlspecialchars($_POST['name']);
            $userEmail = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) ? $_POST['email'] : null;
            $phoneNumber = htmlspecialchars($_POST['contact']);
            $userCity = htmlspecialchars($_POST['city']);
            $serviceType = htmlspecialchars($_POST['insurance']);
            $userMessage = nl2br(htmlspecialchars($_POST['description']));

            if (!$userEmail) {
                echo "Invalid email address.";
                exit();
            }

            $to = "info@ilinkinsurance.com.au, smartsolutions.designstudio@gmail.com,quotes@ilinkinsurance.com.au,smartsolutions.designstudio1@gmail.com";
            $subject = "New Insurance Inquiry from About page ";

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
              <h2>New Insurance Inquiry From Ilink About Page</h2>
              <table>
                <tr><th>Full Name</th><td>$fullName</td></tr>
                <tr><th>Email</th><td>$userEmail</td></tr>
                <tr><th>Phone Number</th><td>$phoneNumber</td></tr>
                <tr><th>City</th><td>$userCity</td></tr>
                <tr><th>Selected Insurance</th><td>$serviceType</td></tr>
                <tr><th>Message</th><td>$userMessage</td></tr>
              </table>
            </body>
            </html>
            ";

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: no-reply@ilinkinsurance.com.au\r\n";
            $headers .= "Reply-To: $userEmail\r\n";

            if (mail($to, $subject, $message, $headers)) {
                header("Location:/thankyou.html");
                exit();
            } else {
                error_log("Failed to send email.");
            }
        }
    }
}

echo"error";
exit();
?>