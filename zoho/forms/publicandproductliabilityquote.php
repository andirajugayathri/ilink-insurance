<?php
ob_start();
session_start();

include 'db.php';
include '../access-token.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/public_products_error.log');
error_reporting(E_ALL);

/*
|--------------------------------------------------------------------------
| BASIC BOT PROTECTION
|--------------------------------------------------------------------------
*/
$minTime = 5;
$formStartTime = $_POST['form_start_time'] ?? time();
if ((time() - $formStartTime) < $minTime) {
    error_log("BOT BLOCKED: Fast submit | IP: {$_SERVER['REMOTE_ADDR']}");
    header("Location: /error.html");
    exit;
}

// Honeypot
if (!empty($_POST['website'])) {
    error_log("HONEYPOT HIT | IP: {$_SERVER['REMOTE_ADDR']}");
    header("Location: /error.html");
    exit;
}

/*
|--------------------------------------------------------------------------
| MAP FORM FIELDS
|--------------------------------------------------------------------------
*/
function mapFormFields($formData)
{
    // ✅ CHECKBOX ARRAY → STRING
    $coverage = isset($formData['coverage']) && is_array($formData['coverage'])
        ? implode(', ', $formData['coverage'])
        : '';

    return [
        'Last_Name' => trim($formData['full-name'] ?? ''),
        'Company' => trim($formData['company-name'] ?? ''),
        'Phone' => trim($formData['contact'] ?? ''),
        'Email' => trim($formData['email'] ?? ''),
        'Address_Line1' => trim($formData['business-address'] ?? ''),
        'Description' => trim($formData['business-description'] ?? ''),
        'No_of_Employees' => trim($formData['number-of-directors-employees'] ?? ''),
        'Estimated_Annual_Turnover_For_The_Business' => trim($formData['estimated-annual-turnover'] ?? ''),
        'Payments_to_Subcontractors' => trim($formData['payments-to-subcontractors'] ?? ''),

        // DB column
        'coverage' => $coverage,

        // Zoho field
        'Type_of_Coverage_required' => $coverage,

        // Zoho fixed fields
        'Product_Inquiry' => 'Public and Product Liability Insurance',
        'Enquiry_Source' => 'ILink Website',
        'Layout' => [
            'name' => 'Website',
            'id' => '62950000001318018'
        ],
        'Owner' => [
            'name' => 'Shalin Shah',
            'id' => '62950000000229001'
        ]
    ];
}

/*
|--------------------------------------------------------------------------
| INSERT INTO DATABASE (MATCHES TABLE)
|--------------------------------------------------------------------------
*/
function insertDataIntoDatabase($data, $pdo)
{
    try {
        $sql = "INSERT INTO public_products_liability_insurance (
            full_name,
            company_name,
            contact_number,
            email,
            business_address,
            business_description,
            number_of_directors_employees,
            estimated_annual_turnover,
            payments_to_subcontractors,
            coverage
        ) VALUES (
            :full_name,
            :company_name,
            :contact_number,
            :email,
            :business_address,
            :business_description,
            :number_of_directors_employees,
            :estimated_annual_turnover,
            :payments_to_subcontractors,
            :coverage
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':full_name' => $data['Last_Name'],
            ':company_name' => $data['Company'],
            ':contact_number' => $data['Phone'],
            ':email' => $data['Email'],
            ':business_address' => $data['Address_Line1'],
            ':business_description' => $data['Description'],
            ':number_of_directors_employees' => $data['No_of_Employees'],
            ':estimated_annual_turnover' => $data['Estimated_Annual_Turnover_For_The_Business'],
            ':payments_to_subcontractors' => $data['Payments_to_Subcontractors'],
            ':coverage' => $data['coverage']
        ]);

        return true;

    } catch (PDOException $e) {
        error_log("DB ERROR: " . $e->getMessage());
        error_log("DATA: " . json_encode($data));
        return false;
    }
}

/*
|--------------------------------------------------------------------------
| SEND TO ZOHO CRM
|--------------------------------------------------------------------------
*/
function addRecordToZoho($data, $pdo)
{
    getAccessToken($pdo);
    $token = $_SESSION['access_token'] ?? null;

    if (!$token) {
        error_log("ZOHO ERROR: Missing access token");
        return false;
    }

    $payload = json_encode(['data' => [$data]]);

    $ch = curl_init("https://www.zohoapis.com.au/crm/v2/Leads");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Zoho-oauthtoken $token",
            "Content-Type: application/json"
        ]
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 201) {
        error_log("ZOHO ERROR ($status): $response");
        return false;
    }

    return true;
}

/*
|--------------------------------------------------------------------------
| EMAIL (AFTER SUCCESS)
|--------------------------------------------------------------------------
*/
function sendEmail($data)
{
    $to = "info@ilinkinsurance.com.au,quotes@ilinkinsurance.com.au";
    $subject = "New Public & Product Liability Insurance Enquiry";

    $message = "
    <html><body>
    <h2>New Public & Product Liability Enquiry</h2>
    <table border='1' cellpadding='8'>
        <tr><th>Name</th><td>{$data['Last_Name']}</td></tr>
        <tr><th>Company</th><td>{$data['Company']}</td></tr>
        <tr><th>Phone</th><td>{$data['Phone']}</td></tr>
        <tr><th>Email</th><td>{$data['Email']}</td></tr>
        <tr><th>Address</th><td>{$data['Address_Line1']}</td></tr>
        <tr><th>Description</th><td>{$data['Description']}</td></tr>
        <tr><th>Employees</th><td>{$data['No_of_Employees']}</td></tr>
        <tr><th>Turnover</th><td>{$data['Estimated_Annual_Turnover_For_The_Business']}</td></tr>
        <tr><th>Subcontractors</th><td>{$data['Payments_to_Subcontractors']}</td></tr>
        <tr><th>Coverage</th><td>{$data['Type_of_Coverage_required']}</td></tr>
    </table>
    </body></html>";

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: ILink Insurance <no-reply@ilinkinsurance.com.au>\r\n";

    mail($to, $subject, $message, $headers);
}

/*
|--------------------------------------------------------------------------
| MAIN EXECUTION
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data = mapFormFields($_POST);

    if (empty($data['Last_Name']) || empty($data['Phone']) || empty($data['Email'])) {
        header("Location: /error.html");
        exit;
    }

    if (!insertDataIntoDatabase($data, $pdo)) {
        header("Location: /error.html");
        exit;
    }

    if (!addRecordToZoho($data, $pdo)) {
        header("Location: /error.html");
        exit;
    }

    sendEmail($data);

    ob_end_clean();
    header("Location: /thankyou.html");
    exit;
}

header("Location: /error.html");
exit;
