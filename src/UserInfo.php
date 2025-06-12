<?php
header('Content-Type: application/json; charset=utf-8');

// Debug için
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Gerekli dosyaları dahil et
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/AuthMiddleware.php';
require_once __DIR__ . '/JWTAuth.php';

use App\AuthMiddleware;
use App\JWTAuth;

// Debug için header bilgilerini yazdır
error_log('Gelen headerlar: ' . print_r(getallheaders(), true));
error_log('SERVER değişkenleri: ' . print_r($_SERVER, true));

// JWT doğrulaması yap
$auth = new AuthMiddleware();
$tokenData = $auth->authenticate();

if (!is_array($tokenData)) {
    error_log('Token doğrulama başarısız');
    exit(); // authenticate metodu zaten hata mesajını yazdırdı
}

error_log('Token doğrulama başarılı: ' . print_r($tokenData, true));

// Mevcut token'ı al
$currentToken = $auth->jwtAuth->getBearerToken();

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

// userId parametresinin eksikliğini kontrol et
if (empty($userId)) {
    echo json_encode(['status' => false, 'message' => 'userId parametresi gereklidir.']);
    exit;
}

// users tablosundan userId'ye göre tüm verileri al
$sql = "SELECT userId, `name`, email, created_at FROM users WHERE userId = '$userId'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Kullanıcı bulundu
    $user = $result->fetch_assoc();    // Yanıt oluştur
    $response = [
        'status' => true,
        'message' => 'Kullanıcı bilgileri alındı.',
        'parameters' => [
            'user' => $user
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
