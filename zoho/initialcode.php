<?php
include 'forms/db.php';
session_start();

$client_id = '1000.GG2IXVZ43EFIQRRPWMS3NA1BXKNWFO';
$client_secret = 'd78fdcd9665e9304fe42f687f6aec208d829a568f6';
$redirect_uri = 'https://ilinkinsurance.com.au/zoho/authcode.php';
$grant_token = '1000.d05668fb6949a7d02553a07f3d9e9a7b.bbf36626b8d79ed162c4060f97bb30fe'; // Updated auth code

$token_url = "https://accounts.zoho.com.au/oauth/v2/token";

$post_fields = http_build_query([
    'grant_type' => 'authorization_code',
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri' => $redirect_uri,
    'code' => $grant_token,
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$tokens = json_decode($response, true);

if (isset($tokens['error']) && $tokens['error'] === "invalid_code"){
    echo "Invalid authorization code. Please generate a new one.";
    die;
}

// Ensure we got the tokens
if (!isset($tokens['access_token']) || !isset($tokens['refresh_token'])) {
    echo "Failed to get tokens.";
    die;
}

// Save tokens securely in the database
if (isset($tokens['refresh_token'])) {
    $sql = "INSERT INTO oauthtoken (client_id, refresh_token, access_token, grant_token, expiry_time) VALUES 
    (:client_id, :refresh_token, :access_token, :grant_token, :expiry_time)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'client_id' => $client_id,
        'refresh_token' => $tokens['refresh_token'],
        'access_token' => $tokens['access_token'],
        'grant_token' => $grant_token,
        'expiry_time' => $tokens["expires_in"]
    ]);

    if ($stmt) {
        echo "Token saved successfully.";
    } else {
        echo "Failed to save token.";
    }
}
?>
