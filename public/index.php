<?php

require_once '../vendor/autoload.php';
require_once '../config/config.php';
require_once '../src/ApiClient.php';
require_once '../src/DreamInterpreter.php';
require_once '../src/DreamHistory.php';
require_once '../src/AuthMiddleware.php';

use App\AuthMiddleware;

// Hata raporlamayı aktif et
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Auth kontrolü
$auth = new AuthMiddleware();
$authResult = $auth->authenticate();

if ($authResult === false) {
    // authenticate() metodu zaten hata mesajını gönderdi
    exit();
}

// Yanıtın UTF-8 formatında dönebilmesi için gerekli header'ı ekle
header('Content-Type: application/json; charset=utf-8');

try {
    // Kullanıcıdan gelen JSON isteğini alma
    $requestBody = file_get_contents('php://input');
    $data = json_decode($requestBody, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Geçersiz JSON verisi: " . json_last_error_msg());
    }

    $dreamDescription = isset($data['dream_description']) ? $data['dream_description'] : '';
    $language = isset($data['language']) ? $data['language'] : 'tr';
    $userId = isset($data['user_id']) ? $data['user_id'] : null;

    if (empty($dreamDescription)) {
        //throw new Exception("Rüya açıklaması eksik.");
        
        echo json_encode([
            "status"=> false,
            "message" => "Rüya açıklamanız eksik",
            "parameters" => ""
            
            ]);
            return;
    }

    // DreamHistory nesnesini oluştur
    $dreamHistory = new DreamHistory($conn);    // Rüya tabiri servisini başlatma
    $dreamInterpreter = new DreamInterpreter(API_URL, API_KEY, $dreamHistory);
    $result = $dreamInterpreter->interpretDream($userId, $dreamDescription, $language);
    
    // DreamInterpreter sınıfı zaten uygun formatta bir yanıt döndürüyor
    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage(),
        "parameters" => ""
//      "file" => $e->getFile(),
//      "line" => $e->getLine()
    ]);
}
