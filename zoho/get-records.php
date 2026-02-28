<?php

session_start();
// Zoho API configuration
$accessToken = $_SESSION['access_token']; // Replace with your actual access token
$apiUrl = 'https://www.zohoapis.com.au/crm/v2/Leads'; // Replace with the desired API endpoint

// Initialize cURL session
$ch = curl_init();

// Set cURL options
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Zoho-oauthtoken $accessToken",
    "Content-Type: application/json"
]);

// Execute cURL request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Check for errors
if (curl_errno($ch)) {
    echo 'cURL error: ' . curl_error($ch);
} else {
    if ($httpCode == 200) {
        // Parse the response
        $data = json_decode($response, true);
        print_r($data); // Print or process the data as needed
    } else {
        echo "Error: HTTP Code $httpCode\n";
        echo "Response: $response\n";
    }
}

// Close cURL session
curl_close($ch);
?>
