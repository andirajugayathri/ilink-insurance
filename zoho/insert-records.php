<?php
ob_start(); 
include 'zoho/db.php';
include 'access-token.php';


// Map form field names to the keys required by Zoho
function mapFormFields($formData) {
    return [
        'Last_Name' => $formData['Full_Name'] ?? '',
        'Company' => $formData['Company_Name'] ?? '',
        'Phone' => $formData['Contact_Number'] ?? '',
        'Email' => $formData['Email'] ?? '',
        'Public_Liability_cover_required' => $formData['Cover_Required'] ?? '',
        'Coverage_Required' => [$formData['Scaffolding_Type_Conducted']] ?? '',
        'Is_Work_Carried_Out_at_Heights_Exceeding_10_Meters' => $formData['Is_Work_Carried_Out_at_Heights_Exceeding_10_Meters'] ?? '',
        'Do_You_Currently_Have_Insurance_In_Place' => $formData['Do_you_currently_have_insurance'] ?? '',
        'Product_Inquiry' => $formData['Product_Inquiry'] ?? '',
        'Sales_Team' => $formData['Sales_Team'] ?? '',
        'Service_Team' => $formData['Service_Team'] ?? '',
        'Layout'=> [
            'name'=> 'Website',
            'id' => '62950000001318018'
                    ],
                   'Owner'=>[
                'name'=> 'Shalin Shah',
            'id'=> '62950000000229001',
            'email'=> 'shalin@ilinkinsurance.com.au'
                   ]
    ];
}

// Function to insert data into the database
function insertDataIntoDatabase($mappedData, $pdo) {
    try {
        // Update the SQL query to include new columns
        $sql = "INSERT INTO quote (
                    Full_Name, 
                    Company_Name, 
                    Email, 
                    Contact_Number, 
                    Cover_Required, 
                    Scaffolding_Type_Conducted, 
                    Is_Work_Carried_Out_at_Heights_Exceeding_10_Meters, 
                    Do_you_currently_have_insurance, 
                    Product_Inquiry, 
                    Sales_Team, 
                    Service_Team
                ) 
                VALUES (
                    :Full_Name, 
                    :Company_Name, 
                    :Email, 
                    :Contact_Number, 
                    :Cover_Required, 
                    :Scaffolding_Type_Conducted, 
                    :Is_Work_Carried_Out_at_Heights_Exceeding_10_Meters, 
                    :Do_you_currently_have_insurance, 
                    :Product_Inquiry, 
                    :Sales_Team, 
                    :Service_Team
                )";

        // Prepare the SQL statement
        $stmt = $pdo->prepare($sql);

        // Bind values for execution
        $stmt->execute([
            ':Full_Name' => $mappedData['Last_Name'],
            ':Company_Name' => $mappedData['Company'],
            ':Email' => $mappedData['Email'],
            ':Contact_Number' => $mappedData['Phone'],
            ':Cover_Required' => $mappedData['Public_Liability_cover_required'],
            ':Scaffolding_Type_Conducted' => json_encode($mappedData['Coverage_Required']),
            ':Is_Work_Carried_Out_at_Heights_Exceeding_10_Meters' => $mappedData['Is_Work_Carried_Out_at_Heights_Exceeding_10_Meters'],
            ':Do_you_currently_have_insurance' => $mappedData['Do_You_Currently_Have_Insurance_In_Place'],
            ':Product_Inquiry' => $mappedData['Product_Inquiry'],
            ':Sales_Team' => $mappedData['Sales_Team'],
            ':Service_Team' => $mappedData['Service_Team']
        ]);

        return true; // Return true if the insert was successful
    } catch (PDOException $e) {
        echo "Database Error: " . $e->getMessage();
        return false;
    }
}


// Function to add a record to Zoho CRM
function addRecordToZoho($mappedData, $pdo) {
    getAccessToken($pdo);
    
    // Retrieve the access token from session
    $accessToken = $_SESSION['access_token'];

    // Specify the Zoho CRM module (e.g., Leads)
    $module = 'Leads';
    $apiUrl = "https://www.zohoapis.com.au/crm/v2/$module";

    // Prepare the data for the API
    $data = ['data' => [$mappedData]];
    $jsonData = json_encode($data);

    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Zoho-oauthtoken $accessToken",
        "Content-Type: application/json"
    ]);

    // Execute the request
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'cURL Error: ' . curl_error($ch);
        curl_close($ch);
        return false;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Handle the response
    if ($httpCode === 201) {
        // echo "Record inserted successfully into Zoho!";
  

        return true;
    } else {
        $errorData = json_decode($response, true);
        echo "API Error ({$httpCode}): " . ($errorData['message'] ?? $response);
        return false;
    }
}

// Function to send an email with form data
function sendEmail($mappedData) {
    $to = "quotes@ilinkinsurance.com.au,info@ilinkinsurance.com.au,smartsolutions.designstudio@gmail.com,narasimha@smartsolutionsdigi.com";
    $subject = " ilink Insurance quote - ".$mappedData['Last_Name'];

    // Construct the email message
    $message = "You have received a new quote request:\n\n";
    $message .= "Full Name: " . ($mappedData['Last_Name'] ?? 'N/A') . "\n";
    $message .= "Company Name: " . ($mappedData['Company'] ?? 'N/A') . "\n";
    $message .= "Email: " . ($mappedData['Email'] ?? 'N/A') . "\n";
    $message .= "Contact Number: " . ($mappedData['Phone'] ?? 'N/A') . "\n";
    $message .= "Cover Required: " . ($mappedData['Public_Liability_cover_required'] ?? 'N/A') . "\n";
    $message .= "Scaffolding Works Conducted: " . ($mappedData['Coverage_Required'] ?? 'N/A') . "\n";
    $message .= "Work At Heights Exceeding 10 Meters: " . ($mappedData['Is_Work_Carried_Out_at_Heights_Exceeding_10_Meters'] ?? 'N/A') . "\n";
    $message .= "Insurance Status: " . ($mappedData['Do_You_Currently_Have_Insurance_In_Place'] ?? 'N/A') . "\n";
    $message .= "Product Inquiry: " . ($mappedData['Product_Inquiry'] ?? 'N/A') . "\n";
    $message .= "Sales Team: " . ($mappedData['Sales_Team'] ?? 'N/A') . "\n";
    $message .= "Service Team: " . ($mappedData['Service_Team'] ?? 'N/A') . "\n";

    // Email headers
    $headers = "From: no-reply@ilinkinsurance.com.au\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    // Send the email and return the result
    return mail($to, $subject, $message, $headers);
}


// Main logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Map form data
    $mappedData = mapFormFields($_POST);

    // Validate required fields
    if (empty($mappedData['Last_Name']) || empty($mappedData['Email']) || empty($mappedData['Phone'])) {
        echo "Error: Missing required fields.";
        exit;
    }

    // Step 1: Insert data into the database
    if (insertDataIntoDatabase($mappedData, $pdo)) {
        // Step 2: Add record to Zoho CRM
        if (addRecordToZoho($mappedData, $pdo)) {
            // Step 3: Send email
            echo 'Email Test';
            $mailsent=sendEmail($mappedData);
            print_r($mailsent);
            
            if ($mailsent) {
                echo 'Inside conEmail Test';
                // Redirect to thankyou.html after successful data insertions
                header("Location: https://scaffoldinginsurance.com.au/thank.html");
                exit; // Ensure no further code is executed
            } else {
                echo "Error: Failed to send email.";
            }
        } else {
            echo "Error: Failed to insert data into Zoho.";
        }
    } else {
        echo "Error: Failed to insert data into the database.";
    }
} else {
    echo "Invalid request method.";
}
?>
