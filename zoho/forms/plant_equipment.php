<?php
session_start();
ob_start();
include 'db.php';
include '../access-token.php';

// to avoid junk leads


function mapFormFields($formData) {
    return [
        'Last_Name' => $formData['fullName'] ?? '',
        'Company' => $formData['companyName'] ?? '',
        'Phone' => $formData['contactNo'] ?? '',
        'Email' => $formData['emailID'] ?? '',
        'Contact_Occupation' => $formData['occupation'] ?? '',
        'Coverage_Section_Required_Refer_Combined_Packag' => $formData['liabilityCover'] ?? '',
        'Details_Of_The_Plant_Equipment_Machinery' => $formData['equipmentDetails'] ?? '',
        'Description' => $formData['otherInsurance'] ?? '',
        'Layout' => [
            'name' => 'Website',
            'id' => '62950000001318018'
        ],
        'Owner' => [
            'name' => 'Shalin Shah',
            'id' => '62950000000229001'
        ],
        'Enquiry_Source' => 'ILink Website - Plant & Equipment Insurance Form',
        'Product_Inquiry' => 'Plant & Equipment Insurance'
    ];
}

function insertDataIntoDatabase($mappedData, $pdo) {
    try {
        // Try with full table structure first
        $sql = "INSERT INTO plant_equipment(
                    full_name, contact_number, email, company_name, occupation,
                    liability_cover, equipment_details, other_insurance
                ) 
                VALUES (
                    :full_name, :contact_number, :email, :company_name, :occupation, 
                    :liability_cover, :equipment_details, :other_insurance
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':full_name' => $mappedData['Last_Name'],
            ':email' => $mappedData['Email'],
            ':contact_number' => $mappedData['Phone'],
            ':company_name' => $mappedData['Company'],
            ':occupation' => $mappedData['Contact_Occupation'],
            ':liability_cover' => $mappedData['Coverage_Section_Required_Refer_Combined_Packag'],
            ':equipment_details' => $mappedData['Details_Of_The_Plant_Equipment_Machinery'],
            ':other_insurance' => $mappedData['Description'],
        ]);

        return true;
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        // Try with a simpler table structure if the above fails
        try {
            $sql = "INSERT INTO plant_equipment(
                        full_name, contact_number, email, company_name, machinery_details
                    ) 
                    VALUES (
                        :full_name, :contact_number, :email, :company_name, :machinery_details
                    )";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':full_name' => $mappedData['Last_Name'],
                ':email' => $mappedData['Email'],
                ':contact_number' => $mappedData['Phone'],
                ':company_name' => $mappedData['Company'],
                ':machinery_details' => $mappedData['Details_Of_The_Plant_Equipment_Machinery'],
            ]);
            return true;
        } catch (PDOException $e2) {
            error_log("Database Error (fallback): " . $e2->getMessage());
            // Still return true to allow Zoho submission even if DB fails
            return true;
        }
    }
}

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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Zoho-oauthtoken $accessToken",
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        error_log("cURL Error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);

    $responseData = json_decode($response, true);

    if ($httpCode === 201) {
        return true;
    } else {
        $errorData = json_decode($response, true);
        error_log("Zoho API Error ($httpCode): " . print_r($responseData, true));
        return false;
    }
}

// Main logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $formData = array_map(function($value) {
        return is_array($value) ? $value : trim($value);
    }, $_POST);

    $mappedData = mapFormFields($formData);

    // Validate required fields
    if (empty($mappedData['Last_Name']) || empty($mappedData['Email']) || empty($mappedData['Phone'])) {
        error_log("Validation failed - missing required fields");
        header("Location: /error.html");
        exit;
    }

    // Insert into DB
    if (insertDataIntoDatabase($mappedData, $pdo)) {
        // Send to Zoho
        if (addRecordToZoho($mappedData, $pdo)) {
            // Send Email
            $userName = htmlspecialchars($mappedData['Last_Name']);
            $company = htmlspecialchars($mappedData['Company']);
            $contact = htmlspecialchars($mappedData['Phone']);
            $email = htmlspecialchars($mappedData['Email']);
            $occupation = htmlspecialchars($mappedData['Contact_Occupation']);
            $liabilityCover = htmlspecialchars($mappedData['Coverage_Section_Required_Refer_Combined_Packag']);
            $equipmentDetails = htmlspecialchars($mappedData['Details_Of_The_Plant_Equipment_Machinery']);
            $otherInsurance = htmlspecialchars($mappedData['Description']);

            $to = "info@ilinkinsurance.com.au, smartsolutions.designstudio@gmail.com, quotes@ilinkinsurance.com.au, smartsolutions.designstudio1@gmail.com";
            $subject = "New Insurance Inquiry from Plant & Equipment Insurance";
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
                    <h2>New Insurance Inquiry from Plant & Equipment Insurance</h2>
                    <table>
                        <tr><th>Full Name</th><td>$userName</td></tr>
                        <tr><th>Company Name</th><td>$company</td></tr>
                        <tr><th>Phone Number</th><td>$contact</td></tr>
                        <tr><th>Email</th><td>$email</td></tr>
                        <tr><th>Occupation/Business Activities</th><td>$occupation</td></tr>
                        <tr><th>Public Liability Cover Required</th><td>$liabilityCover</td></tr>
                        <tr><th>Equipment/Machinery Details</th><td>$equipmentDetails</td></tr>
                        <tr><th>Other Insurance Required</th><td>$otherInsurance</td></tr>
                    </table>
                </body>
            </html>";

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: no-reply@ilinkinsurance.com.au\r\n";
            $headers .= "Reply-To: $email\r\n";

            if (!mail($to, $subject, $message, $headers)) {
                error_log("Error sending email to $to");
            }

            header("Location: /thankyou.html");
            exit;
        } else {
            error_log("Zoho submission failed");
            header("Location: /error.html");
            exit;
        }
    } else {
        error_log("Database insertion failed");
        header("Location: /error.html");
        exit;
    }
} else {
    header("Location: /error.html");
    exit;
}
?>

