<?php
ob_start();
session_start();

include 'db.php';
include '../access-token.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/multiple_truck_error.log');
error_reporting(E_ALL);

// ------------------- CONFIG -------------------
$allowed_referers = ['ilinkinsurance.com.au', 'www.ilinkinsurance.com.au'];
// ---------------------------------------------

/*
|--------------------------------------------------------------------------
| Map Form Fields
|--------------------------------------------------------------------------
*/
function mapFormFields($formData)
{
    $coverageOptions = isset($formData['coverage_options']) && is_array($formData['coverage_options'])
        ? implode(', ', $formData['coverage_options'])
        : '';

    return [
        // Lead fields
        'Last_Name' => trim($formData['full-name'] ?? ''),
        'Address_Line1' => trim($formData['address'] ?? ''),
        'Phone' => trim($formData['contact-number'] ?? ''),
        'Email' => trim($formData['email'] ?? ''),
        'Company' => trim($formData['company-name'] ?? ''),
        'Number_of_Trucks' => trim($formData['number-of-trucks'] ?? ''),
        'Type_of_Coverage_required' => $coverageOptions,
        'Additional_Information_or_Coverage_Request' => trim($formData['additional_info'] ?? ''),

        // Storing "Additional Info" + "Coverage Options" in Description
        'Description' => "Coverage Options: $coverageOptions\nAdditional Info: " . trim($formData['additional_info'] ?? ''),

        // Zoho system fields
        'Product_Inquiry' => 'Multiple Truck Insurance',
        'Sales_Team' => 'Shalin Shah - AR: 418137',
        'Service_Team' => 'Shalin Shah',
        'Layout' => [
            'name' => 'Website',
            'id' => '62950000001318018'
        ],
        'Owner' => [
            'name' => 'Shalin Shah',
            'id' => '62950000000229001',
            'email' => 'shalin@ilinkinsurance.com.au'
        ],
        'Enquiry_Source' => 'ILink Website - Call Back Form'
    ];
}

/*
|--------------------------------------------------------------------------
| Insert Into Database
|--------------------------------------------------------------------------
*/
function insertDataIntoDatabase($data, $pdo)
{
    try {
        // Clean INSERT using correctly named columns from schema update
        $sql = "INSERT INTO truck_insurance_multiple (
            full_name,
            address,
            contact_number,
            email,
            company_name,
            number_of_trucks,
            coverage_options,
            additional_info
        ) VALUES (
            :full_name,
            :address,
            :contact_number,
            :email,
            :company_name,
            :number_of_trucks,
            :coverage_options, 
            :additional_info
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':full_name' => $data['Last_Name'],
            ':address' => $data['Address_Line1'],
            ':contact_number' => $data['Phone'],
            ':email' => $data['Email'],
            ':company_name' => $data['Company'],
            ':number_of_trucks' => $data['Number_of_Trucks'],
            ':coverage_options' => $data['Type_of_Coverage_required'],
            ':additional_info' => $data['Additional_Information_or_Coverage_Request'],
        ]);

        return true;
    } catch (PDOException $e) {
        error_log('DB Error: ' . $e->getMessage());
        return false;
    }
}

/*
|--------------------------------------------------------------------------
| Send Lead to Zoho CRM (STRICT)
|--------------------------------------------------------------------------
*/
function addRecordToZoho($data, $pdo)
{
    getAccessToken($pdo);
    $accessToken = $_SESSION['access_token'] ?? null;

    if (!$accessToken) {
        error_log('Zoho Error: Access token missing');
        return false;
    }

    $zohoData = $data;
    unset($zohoData['Type_of_Coverage_required']);
    unset($zohoData['Additional_Information_or_Coverage_Request']);
    $zohoData = $data;

    $payload = json_encode(['data' => [$zohoData]]);

    $ch = curl_init("https://www.zohoapis.com.au/crm/v2/Leads");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Zoho-oauthtoken $accessToken",
            "Content-Type: application/json"
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 201) {
        error_log("Zoho HTTP Error ($httpCode): " . substr($response, 0, 500));
        return false;
    }

    $decoded = json_decode($response, true);

    if (
        isset($decoded['data'][0]['code']) &&
        $decoded['data'][0]['code'] === 'SUCCESS'
    ) {
        return true;
    }

    error_log('Zoho API Error: ' . $response);
    return false;
}

/*
|--------------------------------------------------------------------------
| Main Logic
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $referer = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
    if (empty($referer) || !in_array($referer, $allowed_referers)) {
        // Permissive
    }

    $mappedData = mapFormFields($_POST);

    if (
        empty($mappedData['Last_Name']) ||
        empty($mappedData['Email']) ||
        empty($mappedData['Phone'])
    ) {
        header("Location: /error.html");
        exit;
    }

    if (!insertDataIntoDatabase($mappedData, $pdo)) {
        header("Location: /error.html");
        exit;
    }

    if (!addRecordToZoho($mappedData, $pdo)) {
        header("Location: /error.html");
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Send Email (only after success)
    |--------------------------------------------------------------------------
    */
    $to = "info@ilinkinsurance.com.au, quotes@ilinkinsurance.com.au, smartsolutions.designstudio@gmail.com, smartsolutions.designstudio1@gmail.com";
    $subject = "New Insurance Inquiry from Multiple Truck Insurance";

    $safe = function ($v) {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    };

    $message = "
    <html><body>
    <h2>New Multiple Truck Insurance Inquiry</h2>
    <table border='1' cellpadding='8' cellspacing='0' width='100%'>
        <tr><th>Full Name</th><td>{$safe($mappedData['Last_Name'])}</td></tr>
        <tr><th>Email</th><td>{$safe($mappedData['Email'])}</td></tr>
        <tr><th>Phone</th><td>{$safe($mappedData['Phone'])}</td></tr>
        <tr><th>Company</th><td>{$safe($mappedData['Company'])}</td></tr>
        <tr><th>Address</th><td>{$safe($mappedData['Address_Line1'])}</td></tr>
        <tr><th>Number of Trucks</th><td>{$safe($mappedData['Number_of_Trucks'])}</td></tr>
        <tr><th>Coverage Options</th><td>{$safe($mappedData['Type_of_Coverage_required'])}</td></tr>
        <tr><th>Additional Info</th><td>{$safe($mappedData['Additional_Information_or_Coverage_Request'])}</td></tr>
    </table>
    </body></html>";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: no-reply@ilinkinsurance.com.au\r\n";
    $headers .= "Reply-To: {$mappedData['Email']}\r\n";

    mail($to, $subject, $message, $headers);

    header("Location: /thankyou.html");
    exit;
}

header("Location: /error.html");
exit;
?>