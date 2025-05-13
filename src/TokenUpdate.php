<?php
header('Content-Type: application/json; charset=utf-8');
// config.php dosyasını dahil et
include_once __DIR__ . '/../config/config.php';

// PHP saat dilimini Türkiye saati (GMT+3) olarak ayarla
date_default_timezone_set('Europe/Istanbul');

// GET parametrelerini al
$userId = isset($_GET['userId']) && is_string($_GET['userId']) ? $conn->real_escape_string($_GET['userId']) : null;
$tokenChange = isset($_GET['token']) ? intval($_GET['token']) : null;

// userId sadece string olarak kabul ediliyor, aksi durumda hata döndür
if (is_null($userId) || !ctype_alnum($userId)) {
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
