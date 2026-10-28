<?php
header('Content-Type: application/json; charset=utf-8');

// ==== LOG DOSYASI YOLU ====
$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/requests.log';

// Klasör yoksa oluştur
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

// Log yazma fonksiyonu
function writeLog($message)
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $logMessage = "[$timestamp] [IP: $ip] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

writeLog("=== Yeni istek alındı ===");

// config.php dosyasını dahil et
include_once __DIR__ . '/../config/config.php';

// JSON verisini al
$rawInput = file_get_contents("php://input");
writeLog("Gelen ham veri: " . $rawInput);

$input = json_decode($rawInput, true);

// JSON verisi düzgün alınmazsa hata döndür
if (json_last_error() !== JSON_ERROR_NONE) {
    $errorMsg = 'Geçersiz JSON verisi';
    writeLog("Hata: $errorMsg");
    echo json_encode(['status' => false, 'message' => $errorMsg, 'parameters' => null]);
    exit;
}

// Veritabanı bağlantısını kontrol et
if ($conn->connect_error) {
    $errorMsg = 'Veritabanı bağlantı hatası: ' . $conn->connect_error;
    writeLog("Hata: $errorMsg");
    echo json_encode(['status' => false, 'message' => $errorMsg, 'parameters' => null]);
    exit;
}

// Gerekli alanları al
if (!isset($input['userId'])) {
    $errorMsg = 'userId alanı gereklidir.';
    writeLog("Hata: $errorMsg");
    echo json_encode(['status' => false, 'message' => $errorMsg, 'parameters' => null]);
    exit;
}

$userId = $conn->real_escape_string($input['userId']);
$name = isset($input['name']) ? $conn->real_escape_string($input['name']) : null;
$email = isset($input['email']) ? $conn->real_escape_string($input['email']) : null;
$deviceToken = isset($input['device_token']) ? $conn->real_escape_string($input['device_token']) : null;

// userId formatını kontrol et
if (!preg_match('/^[\w\-\!\@\#\$\%\^\&\*\(\)\_\+\=\{\}\[\]\:\;\<\>\,\.\?\/]{1,45}$/', $userId)) {
    $errorMsg = 'Geçersiz userId formatı.';
    writeLog("Hata: $errorMsg");
    echo json_encode(['status' => false, 'message' => $errorMsg, 'parameters' => null]);
    exit;
}

// Kullanıcı kontrolü
$sql = "SELECT id FROM users WHERE userId = '$userId'";
$result = $conn->query($sql);

if ($result === false) {
    $errorMsg = 'SQL Hatası: ' . $conn->error;
    writeLog($errorMsg);
    echo json_encode(['status' => false, 'message' => $errorMsg, 'parameters' => null]);
    exit;
}

$createdAt = date('Y-m-d H:i:s');

if ($result->num_rows > 0) {
    // Kullanıcı zaten var
    $user = $result->fetch_assoc();
    $userIdDb = $user['id'];
    writeLog("Kullanıcı bulundu: ID=$userIdDb");

    if ($deviceToken !== null) {
        // === Yeni kontrol: aynı device token başka kullanıcıda varsa sil ===
        $checkExistingToken = "SELECT user_id FROM device_tokens WHERE device_token = '$deviceToken' AND user_id != '$userIdDb'";
        $checkRes = $conn->query($checkExistingToken);
        if ($checkRes && $checkRes->num_rows > 0) {
            $oldUser = $checkRes->fetch_assoc()['user_id'];
            $conn->query("DELETE FROM device_tokens WHERE device_token = '$deviceToken'");
            writeLog("Device token ($deviceToken) eski kullanıcı ($oldUser) kaydından silindi.");
        }

        // Şimdi mevcut kullanıcı için token güncelle veya ekle
        $checkDeviceTokenSql = "SELECT id FROM device_tokens WHERE user_id = '$userIdDb'";
        $deviceResult = $conn->query($checkDeviceTokenSql);
        $updatedAt = date('Y-m-d H:i:s');

        if ($deviceResult->num_rows > 0) {
            $updateDeviceTokenSql = "UPDATE device_tokens SET device_token = '$deviceToken', created_at = '$updatedAt' WHERE user_id = '$userIdDb'";
        } else {
            $updateDeviceTokenSql = "INSERT INTO device_tokens (user_id, device_token, created_at) VALUES ('$userIdDb', '$deviceToken', '$updatedAt')";
        }

        if ($conn->query($updateDeviceTokenSql) !== TRUE) {
            $errorMsg = 'Device token güncellenirken hata oluştu: ' . $conn->error;
            writeLog($errorMsg);
            echo json_encode(['status' => false, 'message' => $errorMsg, 'parameters' => null]);
            exit;
        }

        writeLog("Device token işlendi: $deviceToken");
    }

    $successMsg = 'Giriş başarılı.' . ($deviceToken ? ' Device token güncellendi.' : '');
    writeLog($successMsg);
    echo json_encode(['status' => true, 'message' => $successMsg, 'parameters' => ['userId' => $userId]]);
    exit;
}

// Yeni kullanıcı oluştur
$insertUserSql = "INSERT INTO users (userId, name, email, created_at) VALUES ('$userId', " .
    ($name ? "'$name'" : "NULL") . ", " .
    ($email ? "'$email'" : "NULL") . ", '$createdAt')";

if ($conn->query($insertUserSql) === TRUE) {
    $userIdInserted = $conn->insert_id;
    writeLog("Yeni kullanıcı oluşturuldu: ID=$userIdInserted, userId=$userId");

    // Token ekle
    $token = 1;
    $insertTokenSql = "INSERT INTO tokens (userId, token, created_at) VALUES ('$userId', '$token', '$createdAt')";
    if ($conn->query($insertTokenSql) !== TRUE) {
        $errorMsg = 'Kullanıcı tokeni eklenirken hata: ' . $conn->error;
        writeLog($errorMsg);
        echo json_encode(['status' => false, 'message' => $errorMsg, 'parameters' => null]);
        exit;
    }

    // Device token ekle (opsiyonel)
    if ($deviceToken !== null) {
        // === Aynı token varsa eskiyi sil ===
        $conn->query("DELETE FROM device_tokens WHERE device_token = '$deviceToken'");
        writeLog("Device token ($deviceToken) eski kayıtlardan temizlendi (yeni kullanıcı ekleniyor).");

        $insertDeviceTokenSql = "INSERT INTO device_tokens (user_id, device_token, created_at) VALUES ('$userIdInserted', '$deviceToken', '$createdAt')";
        if ($conn->query($insertDeviceTokenSql) !== TRUE) {
            $errorMsg = 'Cihaz tokeni eklenirken hata: ' . $conn->error;
            writeLog($errorMsg);
            echo json_encode(['status' => false, 'message' => $errorMsg, 'parameters' => null]);
            exit;
        }
        writeLog("Device token eklendi: $deviceToken");
    }

    $successMsg = 'Kullanıcı başarıyla kaydedildi.';
    writeLog($successMsg);
    echo json_encode(['status' => true, 'message' => $successMsg, 'parameters' => ['userId' => $userId]]);
} else {
    $errorMsg = 'Kullanıcı kaydedilirken hata: ' . $conn->error;
    writeLog($errorMsg);
    echo json_encode(['status' => false, 'message' => $errorMsg, 'parameters' => null]);
    exit;
}

$conn->close();
writeLog("=== İstek tamamlandı ===\n");
?>
