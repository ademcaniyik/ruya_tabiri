<?php
require_once __DIR__ . '/php-jwt-main/src/JWT.php';
require_once __DIR__ . '/php-jwt-main/src/Key.php';

use Firebase\JWT\JWT;

function getAccessToken($serviceAccountKeyPath) {
    if (!file_exists($serviceAccountKeyPath)) {
        throw new Exception("Service account key file not found: $serviceAccountKeyPath");
    }

    $key = json_decode(file_get_contents($serviceAccountKeyPath), true);

    if (!isset($key['private_key'], $key['client_email'])) {
        throw new Exception('Invalid service account key file structure.');
    }

    $privateKey = $key['private_key'];
    $clientEmail = $key['client_email'];

    $token = [
        "iss" => $clientEmail,
        "sub" => $clientEmail,
        "aud" => "https://oauth2.googleapis.com/token",
        "iat" => time(),
        "exp" => time() + 3600,
        "scope" => "https://www.googleapis.com/auth/firebase.messaging",
    ];

    $jwt = JWT::encode($token, $privateKey, 'RS256');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]));

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        throw new Exception('cURL error: ' . curl_error($ch));
    }

    curl_close($ch);

    $json = json_decode($response, true);

    if (empty($json['access_token'])) {
        throw new Exception('Failed to retrieve access token. Response: ' . $response);
    }

    return $json['access_token'];
}
