<?php
require_once '../vendor/autoload.php';
require_once '../src/JWTAuth.php';
require_once '../src/AuthMiddleware.php';
require_once '../config/config.php';

use App\AuthMiddleware;
use App\JWTAuth;

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers for JSON response
header('Content-Type: application/json; charset=utf-8');

try {
    // JSON verisini al
    $requestBody = file_get_contents('php://input');
    $data = json_decode($requestBody, true);

    if (!isset($data['bearer_token'])) {
        throw new Exception("Bearer token eksik");
    }

    // Manuel olarak Authorization header'ını ayarla
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $data['bearer_token'];

    // Auth kontrolü
    $auth = new AuthMiddleware();
    $authResult = $auth->authenticate();

    if ($authResult === false) {
        exit(); // authenticate() zaten hata mesajını gönderdi
    }

    // Token doğrulandı, şimdi UserInfo'yu test edelim
    // UserInfo için gerekli veriyi hazırla
    $userInfoData = [
        'userId' => $authResult['user_id']
    ];

    // UserInfo.php'yi çağır
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://acdisoftware.online/ruya_tabiri/src/UserInfo.php");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($userInfoData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $data['bearer_token']
    ]);

    $userInfoResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Sonuçları göster
    echo json_encode([
        'status' => true,
        'message' => 'Test sonuçları',
        'parameters' => [
            'token_validation' => [
                'status' => true,
                'decoded_token' => $authResult
            ],
            'userinfo_test' => [
                'status' => $httpCode === 200,
                'http_code' => $httpCode,
                'response' => json_decode($userInfoResponse, true),
                'curl_error' => $curlError ?: null
            ]
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'message' => $e->getMessage(),
        'parameters' => []
    ]);
}
