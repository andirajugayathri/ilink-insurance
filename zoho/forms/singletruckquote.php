<?php
// Including DB File
ob_start();
include 'db.php';
include '../access-token.php';

/*****
 * Step 1: Collecting the Form Fields 
 * Step 2: Inserting into DB
 * Step 3: Sending data to Zoho CRM
 * Step 4: Sending Email Notification
 */

// Map form field names to match Zoho CRM


// Example usage


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
        'Address_Line1' => $formData['address'] ?? '',
        'Phone' => $formData['contact'] ?? '',
        'Email' => $formData['email'] ?? '',
        'Company' => $formData['company_name'] ?? '',
        'Occupation' => $formData['occupation'] ?? '',
        'Renewal_date_proposed_start_date' => $formData['renewal_date'] ?? '', 
        'Type_of_Vehicle' => $formData['vehicle_type'] ?? '',
        'Carrying_Capacity_GVM_TARE_WEIGHT' => $formData['carrying_capacity'] ?? '',
        'Trailer_cover_required' => $formData['trailer_cover_required'] ?? '',
        'Year_Make_and_Model_Truck_details' => $formData['truck_details'] ?? '',
        'Base_operation_postcode_and_Suburb' => $formData['base_operation'] ?? '',
        'Public_Liability_cover_required' => $formData['public_liability_cover'] ?? '',
        'Sum_Insured' => $formData['sum_insured'] ?? '',
        'Radius_of_operation' => $formData['radius_operation'] ?? '',
        'Marine_goods_in_transit_cover' => $formData['marine_cover'] ?? '',
        'Registration_Number' => $formData['registration_number'] ?? '',
        'Type_of_goods_carried' => $formData['type_goods_carried'] ?? '',
        'Do_you_require_finance_for_the_vehicle' => $formData['require_finance'] ?? '',
        'Number_of_years_business_established' => $formData['business_established'] ?? '',
        'Name_of_current_insurer' => $formData['current_insurer'] ?? '',
        'Age_of_the_regular_driver' => $formData['driver_age'] ?? '',
        'Number_of_years_continuously_insured' => $formData['year_continuously_insured'] ?? '',
        'No_of_truck_insurance_claims_made_in_last_5_years' => $formData['number_truck_insurance'] ?? '',
        'Number_of_years_relavant_truck_licence_held' => $formData['year_truck_licence_held'] ?? '',
        'Any_driving_convictions_in_the_last_5_years' => $formData['driving_convictions'] ?? '',
        'Layout'=> [
            'name'=> 'Website',
            'id' => '62950000001318018'
        ],
         'Owner'=>[
                'name'=> 'Shalin Shah',
            'id'=> '62950000000229001'
            
                   ],
        'Enquiry_Source' => 'ILink Website - Call Back Form',
        'Product_Inquiry' => 'Single Truck Insurance'
    ];
}


// Function to insert data into the database
function insertDataIntoDatabase($mappedData, $pdo) {
    try {
        $sql = "INSERT INTO truck_insurance(
                    full_name, address, contact_number, email, company_name, occupation, renewal_date, 
                    vehicle_type, carrying_capacity, trailer_cover_required, truck_details, base_operation, 
                    public_liability_cover, sum_insured, radius_operation, marine_cover, registration_number, 
                    type_goods_carried, require_finance, business_established, current_insurer, driver_age, 
                    year_continuously_insured, number_truck_insurance, year_truck_licence_held, driving_convictions
                ) 
                VALUES (
                    :full_name, :address, :contact_number, :email, :company_name, :occupation, :renewal_date, 
                    :vehicle_type, :carrying_capacity, :trailer_cover_required, :truck_details, :base_operation, 
                    :public_liability_cover, :sum_insured, :radius_operation, :marine_cover, :registration_number, 
                    :type_goods_carried, :require_finance, :business_established, :current_insurer, :driver_age, 
                    :year_continuously_insured, :number_truck_insurance, :year_truck_licence_held, :driving_convictions
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
            ':vehicle_type' => $mappedData['Type_of_Vehicle'],
            ':carrying_capacity' => $mappedData['Carrying_Capacity_GVM_TARE_WEIGHT'],
            ':trailer_cover_required' => $mappedData['Trailer_cover_required'],
            ':truck_details' => $mappedData['Year_Make_and_Model_Truck_details'],
            ':base_operation' => $mappedData['Base_operation_postcode_and_Suburb'],
            ':public_liability_cover' => $mappedData['Public_Liability_cover_required'],
            ':sum_insured' => $mappedData['Sum_Insured'],
            ':radius_operation' => $mappedData['Radius_of_operation'],
            ':marine_cover' => $mappedData['Marine_goods_in_transit_cover'],
            ':registration_number' => $mappedData['Registration_Number'],
            ':type_goods_carried' => $mappedData['Type_of_goods_carried'],
            ':require_finance' => $mappedData['Do_you_require_finance_for_the_vehicle'],
            ':business_established' => $mappedData['Number_of_years_business_established'],
            ':current_insurer' => $mappedData['Name_of_current_insurer'],
            ':driver_age' => $mappedData['Age_of_the_regular_driver'],
            ':year_continuously_insured' => $mappedData['Number_of_years_continuously_insured'],
            ':number_truck_insurance' => $mappedData['No_of_truck_insurance_claims_made_in_last_5_years'],
            ':year_truck_licence_held' => $mappedData['Number_of_years_relavant_truck_licence_held'],
            ':driving_convictions' => $mappedData['Any_driving_convictions_in_the_last_5_years']
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
        error_log("Zoho API Error ({$httpCode}): " . ($errorData['message'] ?? $response));
        return false;
    }
}






// Main logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mappedData = mapFormFields($_POST);

    
    //sending mail
    
   // Constructing the email message using mapped data
$to = "info@ilinkinsurance.com.au, smartsolutions.designstudio@gmail.com, quotes@ilinkinsurance.com.au,smartsolutions.designstudio1@gmail.com";
$subject = "New Insurance Inquiry from Single Truck Insurance";
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
  <h2>New Insurance Inquiry For Single Truck Insurance</h2>
  <table>
    <tr><th>Full Name</th><td>{$mappedData['Last_Name']}</td></tr>
    <tr><th>Email</th><td>{$mappedData['Email']}</td></tr>
    <tr><th>Phone Number</th><td>{$mappedData['Phone']}</td></tr>
    <tr><th>Company</th><td>{$mappedData['Company']}</td></tr>
    <tr><th>Occupation</th><td>{$mappedData['Occupation']}</td></tr>
     <tr><th>Renewal Date</th><td>{$mappedData['Renewal_date_proposed_start_date']}</td></tr>
    <tr><th>Vehicle Type</th><td>{$mappedData['Type_of_Vehicle']}</td></tr>
    <tr><th>Carrying Capacity</th><td>{$mappedData['Carrying_Capacity_GVM_TARE_WEIGHT']}</td></tr>
    <tr><th>Trailer Cover Required</th><td>{$mappedData['Trailer_cover_required']}</td></tr>
    <tr><th>Truck Details</th><td>{$mappedData['Year_Make_and_Model_Truck_details']}</td></tr>
    <tr><th>Base Operation</th><td>{$mappedData['Base_operation_postcode_and_Suburb']}</td></tr>
    <tr><th>Public Liability Cover</th><td>{$mappedData['Public_Liability_cover_required']}</td></tr>
    <tr><th>Sum Insured</th><td>{$mappedData['Sum_Insured']}</td></tr>
    <tr><th>Radius of Operation</th><td>{$mappedData['Radius_of_operation']}</td></tr>
    <tr><th>Marine Cover</th><td>{$mappedData['Marine_goods_in_transit_cover']}</td></tr>
    <tr><th>Registration Number</th><td>{$mappedData['Registration_Number']}</td></tr>
    <tr><th>Type of Goods Carried</th><td>{$mappedData['Type_of_goods_carried']}</td></tr>
    <tr><th>Finance Required</th><td>{$mappedData['Do_you_require_finance_for_the_vehicle']}</td></tr>
    <tr><th>Business Established</th><td>{$mappedData['Number_of_years_business_established']}</td></tr>
    <tr><th>Current Insurer</th><td>{$mappedData['Name_of_current_insurer']}</td></tr>
    <tr><th>Driver Age</th><td>{$mappedData['Age_of_the_regular_driver']}</td></tr>
    <tr><th>Years Continuously Insured</th><td>{$mappedData['Number_of_years_continuously_insured']}</td></tr>
    <tr><th>Truck Insurance Claims (Last 5 Years)</th><td>{$mappedData['No_of_truck_insurance_claims_made_in_last_5_years']}</td></tr>
    <tr><th>Truck License Years Held</th><td>{$mappedData['Number_of_years_relavant_truck_licence_held']}</td></tr>
    <tr><th>Driving Convictions (Last 5 Years)</th><td>{$mappedData['Any_driving_convictions_in_the_last_5_years']}</td></tr>
  </table>
</body>
</html>
";

// Email headers
   $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: no-reply@ilinkinsurance.com.au\r\n";
            $headers .= "Reply-To: $userEmail\r\n";

// Sending the email
mail($to, $subject, $message, $headers);


    if (insertDataIntoDatabase($mappedData, $pdo)) {
       
        if (addRecordToZoho($mappedData, $pdo)) {
            header("Location: /thankyou.html");
            exit;
        }
    }

    header("Location: /error.html");
    exit;
}
?>
