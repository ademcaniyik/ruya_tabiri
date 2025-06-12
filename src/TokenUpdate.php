<?php
header('Content-Type: application/json; charset=utf-8');

// Gerekli dosyaları dahil et
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/AuthMiddleware.php';

use App\AuthMiddleware;

// PHP saat dilimini Türkiye saati (GMT+3) olarak ayarla
date_default_timezone_set('Europe/Istanbul');

// JSON verisini al
$input = json_decode(file_get_contents("php://input"), true);

// JSON verisi düzgün alınmazsa hata döndür
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['status' => false, 'message' => 'Geçersiz JSON verisi']);
    exit;
}

// Bearer token'ı body'den al
$bearerToken = isset($input['bearer_token']) ? $input['bearer_token'] : null;

// JWT doğrulaması yap
$auth = new AuthMiddleware();
$tokenData = $auth->authenticate($bearerToken);
if (!is_array($tokenData)) {
    exit(); // authenticate metodu zaten hata mesajını yazdırdı
}

// userId ve token değerlerini input'tan al
$userId = isset($input['userId']) && is_string($input['userId']) ? $conn->real_escape_string($input['userId']) : null;
$tokenChange = isset($input['token']) ? intval($input['token']) : null;

// userId sadece string olarak kabul ediliyor, aksi durumda hata döndür
if (is_null($userId) ) {
    echo json_encode(['status' => false, 'message' => 'Geçersiz userId. Sadece string ve alfanümerik değerler kabul edilir.']);
    exit;
}

// Gerekli parametrelerin eksikliği durumunda hata döndür
if (is_null($userId) || is_null($tokenChange)) {
    echo json_encode(['status' => false, 'message' => 'userId ve token parametreleri gereklidir.']);
    exit;
}

// Veritabanı bağlantısını kontrol et
if ($conn->connect_error) {
    die("Bağlantı hatası: " . $conn->connect_error);
}

// userId ile mevcut kullanıcıyı kontrol et
$sql = "SELECT token, created_at FROM tokens WHERE userId = '$userId'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Kullanıcı bulundu
    $row = $result->fetch_assoc();
    $currentToken = (int)$row['token']; // Mevcut token değeri
    $currentCreatedAt = $row['created_at']; // Mevcut created_at değeri

    // Token değerini güncelle
    $newToken = $currentToken + $tokenChange;
    if ($newToken < 0) {
        echo json_encode([
            'status' => false,
            'message' => 'Token değeri negatif olamaz.',
            'parameters' => [
                'currentToken' => $currentToken,
                'currentCreatedAt' => $currentCreatedAt
            ]
        ]);
        exit;
    }

    // Veritabanında token ve created_at güncellemesi yap
    $updateSql = "UPDATE tokens SET token = $newToken, created_at = NOW() WHERE userId = '$userId'";
    if ($conn->query($updateSql) === TRUE) {
        // Başarılı yanıt döndür
        echo json_encode([
            'status' => true,
            'message' => 'Token başarıyla güncellendi.',
            'parameters' => [
                'newToken' => $newToken,
                'newCreatedAt' => date("Y-m-d H:i:s") // Güncel oluşturulma tarihi
            ]
        ]);
    } else {
        // Güncelleme hatası
        echo json_encode([
            'status' => false,
            'message' => 'Token güncellenirken bir hata oluştu.',
            'parameters' => [
                'currentToken' => $currentToken,
                'currentCreatedAt' => $currentCreatedAt
            ]
        ]);
    }
} else {
    // Kullanıcı bulunamadı
    echo json_encode([
        'status' => false,
        'message' => 'Kullanıcı bulunamadı.',
        'parameters' => null
    ]);
}

// Bağlantıyı kapat
$conn->close();
?>
