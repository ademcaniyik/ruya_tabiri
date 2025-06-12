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

// Raw HTTP isteğini kontrol et
$rawHeaders = apache_request_headers();
error_log('Raw HTTP Headers: ' . print_r($rawHeaders, true));

// Authorization header'ını farklı yöntemlerle kontrol et
$authHeader = null;

// 1. Apache raw headers
if (isset($rawHeaders['Authorization'])) {
    $authHeader = $rawHeaders['Authorization'];
    error_log('Found in apache_request_headers()');
}

// 2. PHP $_SERVER değişkenleri
if (!$authHeader) {
    $possibleKeys = [
        'HTTP_AUTHORIZATION',
        'REDIRECT_HTTP_AUTHORIZATION',
        'Authorization'
    ];
    
    foreach ($possibleKeys as $key) {
        if (isset($_SERVER[$key])) {
            $authHeader = $_SERVER[$key];
            error_log("Found in \$_SERVER[$key]");
            break;
        }
    }
}

// 3. getallheaders() kontrolü
if (!$authHeader && function_exists('getallheaders')) {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
        error_log('Found in getallheaders()');
    }
}

error_log('Final Authorization Header: ' . ($authHeader ?? 'Not found'));

// Debug için header bilgilerini yazdır
error_log('Checking raw headers from request...');

// Apache headers
$apache_headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
error_log('Apache headers: ' . print_r($apache_headers, true));

// PHP headers
$php_headers = function_exists('getallheaders') ? getallheaders() : [];
error_log('PHP headers: ' . print_r($php_headers, true));

// Server variables
error_log('SERVER variables: ' . print_r($_SERVER, true));

// Authorization header specific check
$auth_header = null;
if (isset($apache_headers['Authorization'])) {
    $auth_header = $apache_headers['Authorization'];
} elseif (isset($php_headers['Authorization'])) {
    $auth_header = $php_headers['Authorization'];
} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
}

error_log('Final Authorization header found: ' . ($auth_header ?? 'Not found'));

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
