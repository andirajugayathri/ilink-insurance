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




// Map form fields
function mapFormFields($formData) {
    return [
        'Last_Name' => $formData['full_name'] ?? '',
        'Phone' => $formData['contact'] ?? '',
        'Email' => $formData['email'] ?? '',
        'Address_Line1' => $formData['full_address'] ?? '',
        'Company' => $formData['company'] ?? '',
        'No_Of_Years_In_Current_Business' => $formData['years_in_business'] ?? '',
        'Estimated_Annual_Turnover_For_The_Business' => $formData['turnover'] ?? '',
        'Total_Number_Of_Employees' => $formData['employees'] ?? '',
        'Name_of_current_insurer' => $formData['current_insurer'] ?? '',
        'Details_Of_The_Plant_Equipment_Machinery' => $formData['machinery_details'] ?? '',
        'Is_The_Item_Registered' => $formData['item_registered'] ?? '',
        'Coverage_Section_Required_Refer_Combined_Packag' => $formData['coverage_sections'] ?? '',
        'Layout'=> [
            'name'=> 'Website',
            'id' => '62950000001318018'
        ],
         'Owner'=>[
                'name'=> 'Shalin Shah',
            'id'=> '62950000000229001'
            
                   ],
        'Enquiry_Source' => 'ILink Website - Call Back Form',
'Product_Inquiry' => 'Plant Machinery  Insurance'
    ];
}

// Insert into database
function insertDataIntoDatabase($mappedData, $pdo) {
    try {
        $sql = "INSERT INTO Plant_machinery_insurance(
                    full_name, contact_number, email, full_address, years_in_business,
                    turnover, employees, current_insurer, machinery_details,
                    item_registered, coverage_sections
                ) 
                VALUES (
                    :full_name, :contact_number, :email, :full_address, :years_in_business, 
                    :turnover, :employees, :current_insurer, :machinery_details, 
                    :item_registered, :coverage_sections
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':full_name' => $mappedData['Last_Name'],
            ':email' => $mappedData['Email'],
            ':contact_number' => $mappedData['Phone'],
            ':full_address' => $mappedData['Address_Line1'],
            ':years_in_business' => $mappedData['No_Of_Years_In_Current_Business'],
            ':turnover' => $mappedData['Estimated_Annual_Turnover_For_The_Business'],
            ':employees' => $mappedData['Total_Number_Of_Employees'],
            ':current_insurer' => $mappedData['Name_of_current_insurer'],
            ':machinery_details' => $mappedData['Details_Of_The_Plant_Equipment_Machinery'],
            ':item_registered' => $mappedData['Is_The_Item_Registered'],
            ':coverage_sections' => $mappedData['Coverage_Section_Required_Refer_Combined_Packag'],
        ]);

        return true;
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        return false;
    }
}

// Send data to Zoho CRM
function addRecordToZoho($mappedData, $pdo) {
    getAccessToken($pdo);
    $accessToken = $_SESSION['access_token'] ?? null;

    if (!$accessToken) {
        error_log("Error: Missing Zoho access token.");
        return false;
    }

    $module = 'Leads';
    $apiUrl = "https://www.zohoapis.com.au/crm/v2/$module";
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

    if ($response === false) {
        error_log("cURL Error: " . curl_error($ch));
        return false;
    }

    return ($httpCode === 201);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mappedData = mapFormFields($_POST);

   

    // Send email notification
    $to = "info@ilinkinsurance.com.au, smartsolutions.designstudio@gmail.com,quotes@ilinkinsurance.com.au,smartsolutions.designstudio1@gmail.com";
    $subject = "New Inquiry from Plant Machinery Insurance";
    
   $message = "<html>
<head>
    <title>Insurance Inquiry</title>
    <style>
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h2>New Insurance Inquiry from Plant & Machinery Insurance</h2>
    <table>
        <tr><th>Full Name</th><td>{$mappedData['Last_Name']}</td></tr>
        <tr><th>Email</th><td>{$mappedData['Email']}</td></tr>
        <tr><th>Phone Number</th><td>{$mappedData['Phone']}</td></tr>
        <tr><th>Address</th><td>{$mappedData['Address_Line1']}</td></tr>
        <tr><th>Company</th><td>{$mappedData['Company']}</td></tr>
        <tr><th>Years in Business</th><td>{$mappedData['No_Of_Years_In_Current_Business']}</td></tr>
        <tr><th>Annual Turnover</th><td>{$mappedData['Estimated_Annual_Turnover_For_The_Business']}</td></tr>
        <tr><th>Total Employees</th><td>{$mappedData['Total_Number_Of_Employees']}</td></tr>
        <tr><th>Current Insurer</th><td>{$mappedData['Name_of_current_insurer']}</td></tr>
        <tr><th>Machinery Details</th><td>{$mappedData['Details_Of_The_Plant_Equipment_Machinery']}</td></tr>
        <tr><th>Item Registered</th><td>{$mappedData['Is_The_Item_Registered']}</td></tr>
        <tr><th>Coverage Required</th><td>{$mappedData['Coverage_Section_Required_Refer_Combined_Packag']}</td></tr>
    </table>
</body>
</html>";

$headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: no-reply@ilinkinsurance.com.au\r\n";
            $headers .= "Reply-To:{$mappedData['Email']} \r\n";
    
    if (!mail($to, $subject, $message, $headers)) {
        error_log("Error sending email to $to");
    }

    if (insertDataIntoDatabase($mappedData, $pdo) && addRecordToZoho($mappedData, $pdo)) {
        header("Location: /thankyou.html");
        exit;
    } else {
        header("Location: /error.html");
        exit;
    }
}
