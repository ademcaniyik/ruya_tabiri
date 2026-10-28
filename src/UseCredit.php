<?php
header('Content-Type: application/json; charset=utf-8');

// ==== LOG DOSYASI ===
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/credit_usage.log';

function writeLog($message)
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    file_put_contents($logFile, "[$timestamp] [IP: $ip] $message\n", FILE_APPEND | LOCK_EX);
}

// Config dosyasını dahil et
require_once __DIR__ . '/../config/config.php';

// POST Verisini Al
$input = json_decode(file_get_contents("php://input"), true);
writeLog("Credit Use İsteği: " . json_encode($input));

if (!$input) {
    echo json_encode(['status' => false, 'message' => 'Geçersiz veri']);
    exit;
}

$userId = $conn->real_escape_string($input['userId'] ?? '');
$action = $conn->real_escape_string($input['action'] ?? 'use'); // 'use' veya 'check'

if (empty($userId)) {
    echo json_encode(['status' => false, 'message' => 'Kullanıcı ID gerekli']);
    exit;
}

// Mevcut hak sayısını kontrol et
$checkQuery = "SELECT credits FROM user_credits WHERE user_id = '$userId'";
$result = $conn->query($checkQuery);

if (!$result || $result->num_rows == 0) {
    writeLog("Kullanıcı $userId için hak bulunamadı");
    echo json_encode(['status' => false, 'message' => 'Hak bulunamadı', 'credits' => 0]);
    $conn->close();
    exit;
}

$row = $result->fetch_assoc();
$currentCredits = (int) $row['credits'];

// Sadece kontrol istiyorsa
if ($action === 'check') {
    writeLog("Kullanıcı $userId hak kontrolü: $currentCredits");
    echo json_encode(['status' => true, 'credits' => $currentCredits]);
    $conn->close();
    exit;
}

// Hak kullanımı
if ($currentCredits <= 0) {
    writeLog("Kullanıcı $userId hakkı yetersiz: $currentCredits");
    echo json_encode(['status' => false, 'message' => 'Yeterli hak yok', 'credits' => 0]);
    $conn->close();
    exit;
}

// Hakkı düş
$updateQuery = "UPDATE user_credits SET credits = credits - 1, updated_at = NOW() WHERE user_id = '$userId' AND credits > 0";
if ($conn->query($updateQuery) && $conn->affected_rows > 0) {
    $newCredits = $currentCredits - 1;
    writeLog("Kullanıcı $userId için 1 hak kullanıldı. Kalan: $newCredits");
    echo json_encode([
        'status' => true,
        'message' => 'Hak kullanıldı',
        'credits' => $newCredits,
        'used' => 1
    ]);
} else {
    writeLog("Hak kullanım hatası: " . $conn->error);
    echo json_encode(['status' => false, 'message' => 'Hak kullanılamadı']);
}

$conn->close();
?>