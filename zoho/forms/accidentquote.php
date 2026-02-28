<?php
// Enable output buffering
ob_start();

// Include database and Zoho access token scripts
include 'db.php';
include '../access-token.php';

/*****
 * Step 1: Collecting the Form Fields 
 * Step 2: Inserting into DB
 * Step 3: Sending data to Zoho CRM
 * Step 4: Sending email notification
 */
 


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
        'Address_Line1' => $formData['address'] ?? '',
        'Phone' => $formData['contact'] ?? '',
        'Email' => filter_var($formData['email'] ?? '', FILTER_VALIDATE_EMAIL),
        'Contact_Occupation' => $formData['occupation'] ?? '',
        'Self_Employed' => $formData['self_employed'] ?? '',
        'How_long_in_this_occupation' => $formData['experience_years'] ?? '',
        'Type_of_Coverage_required' => $formData['coverage_type'] ?? '',
        'Accident_Weekly_Benefit' => $formData['accident_benefit'] ?? '',
        'Sickness_Weekly_Benefit' => $formData['sickness_benefit'] ?? '',
        'Benefit_Period_Weeks' => $formData['benefit_period'] ?? '',
        'Death_Capital_Benefits' => $formData['capital_benefit'] ?? '',
        'Waiting_Period_Excess_days' => $formData['waiting_period'] ?? '',
       'Layout'=> [
            'name'=> 'Website',
            'id' => '62950000001318018'
        ],
         'Owner'=>[
                'name'=> 'Shalin Shah',
            'id'=> '62950000000229001'
            
                   ],
        'Enquiry_Source' => 'ILink Website - Call Back Form',
'Product_Inquiry' => ' Accident and sickness Insurance'
    ];
}

// Function to insert data into the database
function insertDataIntoDatabase($mappedData, $pdo){
    try {
        $sql = "INSERT INTO Accident_insurance (
                    full_name, address, contact, email, occupation, self_employed, experience,
                    coverage_type, accident_benefit, sickness_benefit, benefit_period,
                    capital_benefit, waiting_period
                ) 
                VALUES (
                    :full_name, :address, :contact, :email, :occupation, :self_employed, :experience,
                    :coverage_type, :accident_benefit, :sickness_benefit, :benefit_period,
                    :capital_benefit, :waiting_period
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':full_name' => $mappedData['Last_Name'],
            ':address' => $mappedData['Address_Line1'],
            ':contact' => $mappedData['Phone'],
            ':email' => $mappedData['Email'],
            ':occupation' => $mappedData['Contact_Occupation'],
            ':self_employed' => $mappedData['Self_Employed'],
            ':experience' => $mappedData['How_long_in_this_occupation'],
            ':coverage_type' => $mappedData['Type_of_Coverage_required'],
            ':accident_benefit' => $mappedData['Accident_Weekly_Benefit'],
            ':sickness_benefit' => $mappedData['Sickness_Weekly_Benefit'],
            ':benefit_period' => $mappedData['Benefit_Period_Weeks'],
            ':capital_benefit' => $mappedData['Death_Capital_Benefits'],
            ':waiting_period' => $mappedData['Waiting_Period_Excess_days']
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

    return $httpCode === 201;
}

// Function to send email notification
function sendEmailNotification($mappedData) {
    $to = "info@ilinkinsurance.com.au, smartsolutions.designstudio@gmail.com,  quotes@ilinkinsurance.com.au,smartsolutions.designstudio1@gmail.com";
    $subject = "Insurance Inquiry For Accident Insurance";

    $message = "
    <html>
    <head>
        <title>Insurance Inquiry For Accident Insurance</title>
        <style>
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
        </style>
    </head>
    <body>
        <h2>Insurance Inquiry Details For Accident Insurance</h2>
        <table>
            <tr><th>Field</th><th>Value</th></tr>
            <tr><td>Full Name</td><td>{$mappedData['Last_Name']}</td></tr>
            <tr><td>Address</td><td>{$mappedData['Address_Line1']}</td></tr>
            <tr><td>Contact</td><td>{$mappedData['Phone']}</td></tr>
            <tr><td>Email</td><td>{$mappedData['Email']}</td></tr>
            <tr><td>Occupation</td><td>{$mappedData['Contact_Occupation']}</td></tr>
            <tr><td>Self Employed</td><td>{$mappedData['Self_Employed']}</td></tr>
            <tr><td>Experience</td><td>{$mappedData['How_long_in_this_occupation']}</td></tr>
            <tr><td>Coverage Type</td><td>{$mappedData['Type_of_Coverage_required']}</td></tr>
            <tr><td>Accident Benefit</td><td>{$mappedData['Accident_Weekly_Benefit']}</td></tr>
            <tr><td>Sickness Benefit</td><td>{$mappedData['Sickness_Weekly_Benefit']}</td></tr>
            <tr><td>Benefit Period</td><td>{$mappedData['Benefit_Period_Weeks']}</td></tr>
            <tr><td>Capital Benefit</td><td>{$mappedData['Death_Capital_Benefits']}</td></tr>
            <tr><td>Waiting Period</td><td>{$mappedData['Waiting_Period_Excess_days']}</td></tr>
        </table>
    </body>
    </html>";

   $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: no-reply@ilinkinsurance.com.au\r\n";
            $headers .= "Reply-To: $userEmail\r\n";

    return mail($to, $subject, $message, $headers);
}

// Main logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mappedData = mapFormFields($_POST);

    if (insertDataIntoDatabase($mappedData, $pdo)) {
        addRecordToZoho($mappedData, $pdo);
        sendEmailNotification($mappedData);
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
?>
