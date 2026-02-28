<?php
// Start output buffering
ob_start();

// Including DB Connection & Access Token File
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



// Mapping form fields to match Zoho CRM
function mapFormFields($formData) {
    return [
        'Last_Name' => $formData['full-name'] ?? '',
        'Address_Line1' => $formData['address-line1'] ?? '',
        'Phone' => $formData['contact'] ?? '',
        'Email' => $formData['email'] ?? '',
        'Company' => $formData['company'] ?? '',
        'Occupation' => $formData['occupation'] ?? '',
        'Renewal_date_proposed_start_date' => $formData['renewal_date'] ?? '',
        'Number_of_Trucks' => $formData['number-of-trucks'] ?? '',
        'Base_Operation_Postcode' => $formData['base-operation-postcode'] ?? '',
        'Trailer_Cover_Required' => $formData['trailer-cover'] ?? '',
        'Vehicle_Type' => $formData['vehicle-type'] ?? '',
        'Radius_Operation' => $formData['radius-operation'] ?? '',
        'Public_Liability_Cover' => $formData['public-liability'] ?? '',
        'Years_Business_Established' => $formData['years-business'] ?? '',
        'Truck_Insurance_Claims' => $formData['insurance-claims'] ?? '',
        'Driving_Convictions' => $formData['driving-convictions'] ?? '',
        
        'Layout'=> [
            'name'=> 'Website',
            'id' => '62950000001318018'
        ],
         'Owner'=>[
                'name'=> 'Shalin Shah',
            'id'=> '62950000000229001'
            
                   ],
        'Enquiry_Source' => 'ILink Website - Call Back Form',
        'Product_Inquiry' => 'Multiple Truck Insurance'
    ];
}

// Function to insert data into the database
function insertDataIntoDatabase($mappedData, $pdo) {
    try {
        $sql = "INSERT INTO truck_insurance_multiple (
            full_name, address, contact_number, email, company_name, occupation,
            renewal_date, number_of_trucks, base_operation_postcode, trailer_cover_required,
            vehicle_type, radius_operation, public_liability_cover,
            years_business_established, truck_insurance_claims, driving_convictions
        ) VALUES (
            :full_name, :address, :contact_number, :email, :company_name, :occupation,
            :renewal_date, :number_of_trucks, :base_operation_postcode, :trailer_cover_required,
            :vehicle_type, :radius_operation, :public_liability_cover,
            :years_business_established, :truck_insurance_claims, :driving_convictions
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':full_name' => $mappedData['Last_Name'],
            ':address' => $mappedData['Address_Line1'],
            ':contact_number' => $mappedData['Phone'],
            ':email' => $mappedData['Email'],
            ':company_name' => $mappedData['Company'],
            ':occupation' => $mappedData['Occupation'],
            ':renewal_date' => $mappedData['Renewal_date_proposed_start_date'],
            ':number_of_trucks' => $mappedData['Number_of_Trucks'],
            ':base_operation_postcode' => $mappedData['Base_Operation_Postcode'],
            ':trailer_cover_required' => $mappedData['Trailer_Cover_Required'],
            ':vehicle_type' => $mappedData['Vehicle_Type'],
            ':radius_operation' => $mappedData['Radius_Operation'],
            ':public_liability_cover' => $mappedData['Public_Liability_Cover'],
            ':years_business_established' => $mappedData['Years_Business_Established'],
            ':truck_insurance_claims' => $mappedData['Truck_Insurance_Claims'],
            ':driving_convictions' => $mappedData['Driving_Convictions']
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
        error_log("Zoho API Error ({$httpCode}): " . json_encode($response));
        return false;
    }
}

// Main logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mappedData = mapFormFields($_POST);
    
    if (empty($mappedData['Last_Name']) || empty($mappedData['Email']) || empty($mappedData['Phone'])) {
        header("Location: /ilink/error.html");
        exit;
    }

    $to = "info@ilinkinsurance.com.au, smartsolutions.designstudio@gmail.com, quotes@ilinkinsurance.com.au,smartsolutions.designstudio1@gmail.com";
    $subject = "New Insurance Inquiry from Multiple Truck Insurance";
    $message = "
    <html>
    <head>
        <title>Insurance Inquiry for Multiple Truck Insurance</title>
        <style>
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            th { background-color: #f2f2f2; }
        </style>
    </head>
    <body>
        <h2>New Insurance Inquiry</h2>
        <table>
            <tr><th>Full Name</th><td>{$mappedData['Last_Name']}</td></tr>
            <tr><th>Email</th><td>{$mappedData['Email']}</td></tr>
            <tr><th>Phone Number</th><td>{$mappedData['Phone']}</td></tr>
            <tr><th>Address</th><td>{$mappedData['Address_Line1']}</td></tr>
            <tr><th>Company</th><td>{$mappedData['Company']}</td></tr>
            <tr><th>Occupation</th><td>{$mappedData['Occupation']}</td></tr>
            <tr><th>Renewal Date</th><td>{$mappedData['Renewal_date_proposed_start_date']}</td></tr>
            <tr><th>Number of Trucks</th><td>{$mappedData['Number_of_Trucks']}</td></tr>
            <tr><th>Vehicle Type</th><td>{$mappedData['Vehicle_Type']}</td></tr>
            <tr><th>Trailer Cover Required</th><td>{$mappedData['Trailer_Cover_Required']}</td></tr>
            <tr><th>Base Operation</th><td>{$mappedData['Base_Operation_Postcode']}</td></tr>
            <tr><th>Radius Operation</th><td>{$mappedData['Radius_Operation']}</td></tr>
            <tr><th>Public Liability Cover</th><td>{$mappedData['Public_Liability_Cover']}</td></tr>
            <tr><th>Years Business Established</th><td>{$mappedData['Years_Business_Established']}</td></tr>
            <tr><th>Insurance Claims</th><td>{$mappedData['Truck_Insurance_Claims']}</td></tr>
            <tr><th>Driving Convictions</th><td>{$mappedData['Driving_Convictions']}</td></tr>
        </table>
    </body>
    </html>
    ";

    // Email headers
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: no-reply@ilinkinsurance.com.au\r\n";
    $headers .= "Reply-To: {$mappedData['Email']}\r\n";

    // Sending the email
    mail($to, $subject, $message, $headers);
    
    if (insertDataIntoDatabase($mappedData, $pdo) && addRecordToZoho($mappedData, $pdo)) {
        header("Location: /thankyou.html");
        exit;
    } else {
        header("Location: /error.html");
        exit;
    }
}