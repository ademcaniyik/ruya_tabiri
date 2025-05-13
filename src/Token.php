<?php
header('Content-Type: application/json; charset=utf-8');
// config.php dosyasını dahil et
include_once __DIR__ . '/../config/config.php';

// JSON verisini al
$input = json_decode(file_get_contents("php://input"), true);

// JSON verisi düzgün alınmazsa hata döndür
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['status' => false, 'message' => 'Geçersiz JSON verisi']);
    exit;
}

// Veritabanı bağlantısını kontrol et
if ($conn->connect_error) {
    die("Bağlantı hatası: " . $conn->connect_error);
}

// Gerekli alanlar
$userId = $conn->real_escape_string($input['userId']);

// 1. Adım: Veritabanında userId ile token değerini kontrol et (tokens tablosundan)
$sql = "SELECT t.token, t.created_at
        FROM tokens t
        WHERE t.userId = '$userId' 
        ORDER BY t.created_at DESC LIMIT 1"; // Son token kaydını alıyoruz

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Kullanıcı bulundu, token ve created_at verisi alındı
    $row = $result->fetch_assoc();
    $token = (int)$row['token']; // Veritabanından gelen token değeri
    $created_at = $row['created_at']; // Token oluşturulma zamanı

    // Yanıt oluştur
    $response = [
        'status' => true,
        'message' => 'Token sorgulandı.',
        'parameters' => [
            'token' => $token,
            'created_at' => $created_at
        ]
    ];
} else {
    // Kullanıcı bulunamadı
    $response = [
        'status' => false,
        'message' => 'Kullanıcı bulunamadı.',
        'parameters' => null
    ];
}

// JSON yanıtını döndür
echo json_encode($response, JSON_UNESCAPED_UNICODE);

// Bağlantıyı kapat
$conn->close();
?>
