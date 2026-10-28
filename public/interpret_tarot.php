<?php

// public/interpret_tarot.php

require_once '../config/config.php';
require_once '../src/ApiClient.php';
require_once '../src/DreamHistory.php';
require_once '../src/TarotInterpreter.php';

// Hata raporlamayı aktif et (Geliştirme aşamasında)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// JSON başlığı
header('Content-Type: application/json; charset=utf-8');

try {
    // 1. İsteği Al
    $requestBody = file_get_contents('php://input');
    $data = json_decode($requestBody, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Geçersiz JSON verisi: " . json_last_error_msg());
    }

    // 2. User ID Kontrolü
    $userId = $data['userId'] ?? $data['user_id'] ?? null;

    if (empty($userId)) {
        throw new Exception("Kullanıcı ID (userId) eksik.");
    }

    // 3. Bağlantıları Kur
    // API_URL ve API_KEY config.php'den geliyor
    // TarotInterpreter artık doğrudan DB bağlantısı alıyor
    $tarotInterpreter = new TarotInterpreter(API_URL, API_KEY, $conn);

    // 4. Yorumla
    $result = $tarotInterpreter->interpret($userId, $data);

    // 5. Yanıtı Döndür
    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Sunucu Hatası: " . $e->getMessage(),
        "parameters" => []
    ]);
}
