<?php

// public/dream_history.php

require_once '../config/config.php';
require_once '../src/DreamHistory.php';

// Hata raporlamayı aktif et
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Yanıtın UTF-8 formatında dönebilmesi için gerekli header'ı ekle
header('Content-Type: application/json; charset=utf-8');

try {
    // Kullanıcıdan gelen JSON isteğini alma
    $requestBody = file_get_contents('php://input');
    $data = json_decode($requestBody, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Geçersiz JSON verisi: " . json_last_error_msg());
    }

    $userId = isset($data['user_id']) ? $data['user_id'] : null;

    if (empty($userId)) {
        throw new Exception("Kullanıcı ID eksik.");
    }

    // DreamHistory nesnesini oluştur
    $dreamHistory = new DreamHistory($conn);
    
    // Kullanıcının rüya geçmişini al
    $dreams = $dreamHistory->getUserDreams($userId);

    // Parametreler düzleştirilmiş olarak hazırlanıyor
    $parameters = [];
    foreach ($dreams as $dream) {
        $parameters[] = [
            "id" => $dream['id'],
            "user_id" => $dream['user_id'],
            "dream" => $dream['dream'],
            "interpretation" => $dream['interpretation'],
            "created_at" => $dream['created_at'],
        ];
    }

    echo json_encode([
        "status" => true,
        "message" => "Kullanıcının rüya geçmişi başarıyla alındı.",
        "parameters" => $parameters
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine()
    ]);
}
