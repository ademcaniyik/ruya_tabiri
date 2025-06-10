<?php
header('Content-Type: application/json; charset=utf-8');

// config.php dosyasını dahil et
include_once __DIR__ . '/../config/config.php';

// JSON verisini al
$input = json_decode(file_get_contents("php://input"), true);

// JSON verisi düzgün alınmazsa hata döndür
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['status' => false, 'message' => 'Geçersiz JSON verisi', 'parameters' => null]);
    exit;
}

// Veritabanı bağlantısını kontrol et
if ($conn->connect_error) {
    echo json_encode(['status' => false, 'message' => 'Veritabanı bağlantı hatası: ' . $conn->connect_error, 'parameters' => null]);
    exit;
}

// Gerekli alanları al - sadece userId zorunlu
$userId = $conn->real_escape_string($input['userId']);

// Diğer alanlar opsiyonel
$name = isset($input['name']) ? $conn->real_escape_string($input['name']) : null;
$email = isset($input['email']) ? $conn->real_escape_string($input['email']) : null;
$deviceToken = isset($input['device_token']) ? $conn->real_escape_string($input['device_token']) : null;

// JWT için gerekli
require_once __DIR__ . '/JWTAuth.php';
$jwtAuth = new JWTAuth();

// userId formatını kontrol et
if (!preg_match('/^[\w\-\!\@\#\$\%\^\&\*\(\)\_\+\=\{\}\[\]\:\;\<\>\,\.\?\/]{1,45}$/', $userId)) {
    echo json_encode(['status' => false, 'message' => 'Geçersiz userId formatı.', 'parameters' => null]);
    exit;
}

// 1. Adım: Aynı userId ile bir kayıt var mı kontrol et
$sql = "SELECT id FROM users WHERE userId = '$userId'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Kullanıcı zaten var
    $user = $result->fetch_assoc();
    $userIdInserted = $user['id'];
    
    // Eğer ek bilgiler geldiyse güncelle
    if ($name !== null && $email !== null) {
        $updateSql = "UPDATE users SET name = '$name', email = '$email' WHERE id = '$userIdInserted'";
        $conn->query($updateSql);
    }
} else {
    // Sadece userId ile geldiyse hata dön
    if ($name === null || $email === null) {
        echo json_encode([
            'status' => false,
            'message' => 'Yeni kullanıcı kaydı için name ve email zorunludur.',
            'parameters' => null
        ]);
        exit;
    }
    
    // Kullanıcı yok, yeni kayıt oluştur
    $createdAt = date('Y-m-d H:i:s');
    $insertUserSql = "INSERT INTO users (userId, name, email, created_at) VALUES ('$userId', '$name', '$email', '$createdAt')";
    if ($conn->query($insertUserSql) === TRUE) {
        // Yeni eklenen kullanıcının id'sini al
        $userIdInserted = $conn->insert_id;

        // tokens tablosuna giriş ekle
        $token = 1; // İlk token değeri
        $insertTokenSql = "INSERT INTO tokens (userId, token, created_at) VALUES ('$userId', '$token', '$createdAt')";
        if ($conn->query($insertTokenSql) !== TRUE) {
            echo json_encode(['status' => false, 'message' => 'Kullanıcı tokeni eklenirken bir hata oluştu: ' . $conn->error, 'parameters' => null]);
            exit;
        }
    } else {
        echo json_encode(['status' => false, 'message' => 'Kullanıcı kaydedilirken bir hata oluştu: ' . $conn->error, 'parameters' => null]);
        exit;
    }
}

// 2. Adım: device_tokens tablosuna cihaz token ekle
$createdAt = date('Y-m-d H:i:s');
$insertDeviceTokenSql = "INSERT INTO device_tokens (user_id, device_token, created_at) 
                         VALUES ('$userIdInserted', '$deviceToken', '$createdAt') 
                         ON DUPLICATE KEY UPDATE created_at = '$createdAt'";

if ($conn->query($insertDeviceTokenSql) === TRUE || $deviceToken === null) {
        // Kullanıcı bilgilerini al
        $userSql = "SELECT userId, email FROM users WHERE id = '$userIdInserted'";
        $userResult = $conn->query($userSql);
        $userData = $userResult->fetch_assoc();
        
        // JWT token oluştur
        $jwtToken = $jwtAuth->generateToken($userData['userId'], $userData['email']);

        $response = [
            'status' => true,
            'message' => $deviceToken === null ? 'Giriş başarılı.' : 'Kullanıcı başarıyla kaydedildi.',
            'parameters' => [
                'token' => $jwtToken
            ]
        ];
} else {
    $response = [
        'status' => false,
        'message' => 'Cihaz token eklenirken bir hata oluştu: ' . $conn->error,
        'parameters' => null
    ];
}

// JSON yanıtını döndür
echo json_encode($response, JSON_UNESCAPED_UNICODE);

// Bağlantıyı kapat
$conn->close();
?>
