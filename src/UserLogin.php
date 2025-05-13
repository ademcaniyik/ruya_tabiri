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
$name = $conn->real_escape_string($input['name']);
$email = $conn->real_escape_string($input['email']);

// userId formatını kontrol et
if (!preg_match('/^\d{1,21}$/', $userId)) {
    echo json_encode(['status' => false, 'message' => 'Geçersiz userId formatı.']);
    exit;
}

// 1. Adım: Aynı userId ile bir kayıt var mı kontrol et
$sql = "SELECT * FROM users WHERE userId = '$userId'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Kullanıcı zaten var, sadece mesaj döndür
    $response = [
        'status' => true,
        'message' => 'Kullanıcı zaten mevcut.',
        'parameters' => null
    ];
} else {
    // Kullanıcı yok, yeni kayıt oluştur
    // Yeni kullanıcıyı ekle
    $insertUserSql = "INSERT INTO users (userId, name, email) VALUES ('$userId', '$name', '$email')";
    if ($conn->query($insertUserSql) === TRUE) {
        // Kullanıcı kaydedildi, şimdi tokens tablosuna giriş ekle
        $token = 1; // İlk token değeri
        $createdAt = date('Y-m-d H:i:s');
        $insertTokenSql = "INSERT INTO tokens (userId, token, created_at) VALUES ('$userId', '$token', '$createdAt')";

        if ($conn->query($insertTokenSql) === TRUE) {
            $response = [
                'status' => true,
                'message' => 'Kullanıcı ve token başarıyla kaydedildi.',
                'parameters' => null
            ];
        } else {
            $response = [
                'status' => false,
                'message' => 'Kullanıcı kaydedildi ancak token eklenirken bir hata oluştu.',
                'parameters' => null
            ];
        }
    } else {
        $response = [
            'status' => false,
            'message' => 'Kullanıcı kaydedilirken bir hata oluştu.',
            'parameters' => null
        ];
    }
}

// JSON yanıtını döndür
echo json_encode($response, JSON_UNESCAPED_UNICODE);

// Bağlantıyı kapat
$conn->close();
?>
