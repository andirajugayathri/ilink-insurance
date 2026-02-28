<?php
include 'forms/db.php';

// session_start();

function loadaccesstoken($pdo) {
    $sql = "SELECT * FROM oauthtoken";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($result)) {
        return $result[0]['refresh_token'];
    }

    return null; // Handle case where no token is found
}

function getAccessToken($pdo) {
    $rftoken = loadaccesstoken($pdo);
    if (!$rftoken) {
        error_log("Error: No refresh token found.");
        return;
    }

    $client_id = '1000.GG2IXVZ43EFIQRRPWMS3NA1BXKNWFO';
    $client_secret = 'd78fdcd9665e9304fe42f687f6aec208d829a568f6';

    $token_url = "https://accounts.zoho.com.au/oauth/v2/token";

    $post_fields = http_build_query([
        'refresh_token' => $rftoken,
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'grant_type' => 'refresh_token',
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    $tokens = json_decode($response, true);

    if (isset($tokens['access_token'])) {
        $_SESSION['access_token'] = $tokens['access_token'];
    } else {
        error_log("Error refreshing token: " . $response);
    }
}

// Fetch and store access token
getAccessToken($pdo);

ob_clean(); // Ensure no output before redirection
?>
