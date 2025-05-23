<?php
require_once __DIR__ . '/../php-jwt-main/src/JWT.php';
require_once __DIR__ . '/../php-jwt-main/src/Key.php';


use Firebase\JWT\JWT;

function getAccessToken($serviceAccountKeyPath) {
    $key = json_decode(file_get_contents($serviceAccountKeyPath), true);
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
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($response, true);
    return $json['access_token'] ?? null;
}