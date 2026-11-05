<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/ApiClient.php';
require_once __DIR__ . '/../src/DreamInterpreter.php';
require_once __DIR__ . '/../src/DreamHistory.php';

// Hata raporlamayı aktif et

// Yanıtın UTF-8 formatında dönebilmesi için gerekli header'ı ekle
header('Content-Type: application/json; charset=utf-8');

// /public/index.php/{id}: rüya silme, id yoksa yorumlama
$deleteId = null;
if (preg_match('#index\.php/(\d+)/?(?:\?|$)#', $_SERVER['REQUEST_URI'] ?? '', $m)) {
    $deleteId = (int) $m[1];
} elseif (!empty($_SERVER['PATH_INFO']) && preg_match('#^/(\d+)/?$#', $_SERVER['PATH_INFO'], $m)) {
    $deleteId = (int) $m[1];
}

if ($deleteId !== null) {
    // Silinen rüyanın sahibinin güncel kredisini Token.php biçiminde döndür
    $st = $conn->prepare('SELECT user_id FROM dream_history WHERE id = ?');
    $st->bind_param('i', $deleteId);
    $st->execute();
    $dream = $st->get_result()->fetch_assoc();
    if (!$dream) {
        json_out(false, 'Rüya bulunamadı');
    }
    $st = $conn->prepare('DELETE FROM dream_history WHERE id = ?');
    $st->bind_param('i', $deleteId);
    if (!$st->execute()) {
        json_out(false, 'Rüya silinemedi');
    }
    $st = $conn->prepare('SELECT token, created_at FROM tokens WHERE userId = ? LIMIT 1');
    $st->bind_param('s', $dream['user_id']);
    $st->execute();
    $tok = $st->get_result()->fetch_assoc();
    json_out(true, 'Rüya silindi', [
        'token' => (int) ($tok['token'] ?? 0),
        'created_at' => (string) ($tok['created_at'] ?? date('Y-m-d H:i:s')),
    ]);
}

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
        echo json_encode([
            "status"=> false,
            "message" => "Rüya açıklamanız eksik",
            "parameters" => ""
        ]);
        return;
    }

    // Maksimum karakter sınırı
    $maxLength = 1500;
    if (strlen($dreamDescription) > $maxLength) {
        echo json_encode([
            "status" => false,
            "message" => "Rüya açıklamanız çok uzun. Maksimum $maxLength karakter olmalı.",
            "parameters" => ""
        ]);
        return;
    }

    // DreamHistory nesnesini oluştur
    $dreamHistory = new DreamHistory($conn);
    
    // Rüya tabiri servisini başlatma
    $dreamInterpreter = new DreamInterpreter(API_URL, API_KEY, $dreamHistory);
    $result = $dreamInterpreter->interpretDream($userId, $dreamDescription, $language);

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status"=> false,
        "message" => $e->getMessage(),
        "parameters" => ""
    ]);
}
