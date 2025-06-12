<?php
header('Content-Type: application/json; charset=utf-8');

// Gerekli dosyaları dahil et
require_once __DIR__ . '/../config/config.php';

// PHP saat dilimini Türkiye saati (GMT+3) olarak ayarla
date_default_timezone_set('Europe/Istanbul');

// JSON verisini al
$input = json_decode(file_get_contents("php://input"), true);

// JSON verisi düzgün alınmazsa hata döndür
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['status' => false, 'message' => 'Geçersiz JSON verisi']);
    exit;
}

// Gerekli parametreleri kontrol et
if (!isset($input['userId']) || !isset($input['tokenChange'])) {
    echo json_encode(['status' => false, 'message' => 'userId ve tokenChange parametreleri gereklidir.']);
    exit;
}

$userId = $conn->real_escape_string($input['userId']);
$tokenChange = intval($input['tokenChange']);

// userId sadece string olarak kabul ediliyor, aksi durumda hata döndür
if (!preg_match('/^[\w\-\!\@\#\$\%\^\&\*\(\)\_\+\=\{\}\[\]\:\;\<\>\,\.\?\/]{1,45}$/', $userId)) {
    echo json_encode(['status' => false, 'message' => 'Geçersiz userId. Sadece string ve alfanümerik değerler kabul edilir.']);
    exit;
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
                'currentToken' => $currentToken,
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
