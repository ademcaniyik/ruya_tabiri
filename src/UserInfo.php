<?php
header('Content-Type: application/json; charset=utf-8');

// Gerekli dosyaları dahil et
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/AuthMiddleware.php';

// JWT doğrulaması yap
$auth = new AuthMiddleware();
$user = $auth->authenticate();

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
    $user = $result->fetch_assoc();

    // Yanıt oluştur
    $response = [
        'status' => true,
        'message' => 'Kullanıcı bilgileri alındı.',
        'parameters' => $user // users tablosundaki tüm kolonlar burada yer alacak
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
