<?php
header('Content-Type: application/json; charset=utf-8');

// PHP varsayılan zaman dilimini UTC yap
date_default_timezone_set('UTC');

// Veritabanı bağlantısı
require_once __DIR__ . '/../config/config.php';

// JSON al
$input = json_decode(file_get_contents("php://input"), true);

// Girdi kontrolü
if (json_last_error() !== JSON_ERROR_NONE || !isset($input['userId'])) {
    echo json_encode([
        'status' => false, 
        'message' => 'Geçersiz JSON verisi veya userId parametresi eksik.'
    ]);
    exit;
}

$userId = $input['userId'];

// Yardımcı: UTC'yi Türkiye saatine çevir
function get_local_time_string($utcDate = null) {
    $dt = $utcDate 
        ? new DateTime($utcDate, new DateTimeZone('UTC')) 
        : new DateTime('now', new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('Europe/Istanbul'));
    return $dt->format('Y-m-d H:i:s');
}

// Token süresi (24 saat)
$expiry_seconds = 24 * 60 * 60; 
$response = [];

// Mevcut token'ı getir
$selectSql = "SELECT token, created_at FROM tokens WHERE userId = ? LIMIT 1";
$stmt = $conn->prepare($selectSql);

if (!$stmt) {
    echo json_encode(['status' => false, 'message' => 'Sorgu hazırlanamadı: ' . $conn->error]);
    exit;
}

$stmt->bind_param("s", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Kullanıcı bulundu
    $row = $result->fetch_assoc();
    $currentToken = (int)$row['token'];
    $created_at_db = $row['created_at'];

    $newTokenValue = $currentToken;
    $shouldUpdate = false;

    if ($currentToken < 1) {
        $timestamp_db = strtotime($created_at_db);
        $timestamp_now = time();

        // Token 24 saatten eskiyse yenile
        if ($timestamp_now >= ($timestamp_db + $expiry_seconds)) {
            $newTokenValue = 1;
            $shouldUpdate = true;
            $message_status = 'Token süresi dolduğu için 1 olarak güncellendi.';
        } else {
            $remainingSeconds = ($timestamp_db + $expiry_seconds) - $timestamp_now;
            $remainingMinutes = ceil($remainingSeconds / 60);
            $message_status = "Token 1'den az ancak süresi dolmadı. Kalan süre: $remainingMinutes dakika.";
        }
    } else {
        $message_status = 'Token geçerli (1). Güncelleme yapılmadı.';
    }

    // Gerekirse token güncelle
    if ($shouldUpdate) {
        $updateSql = "UPDATE tokens SET token = ?, created_at = NOW() WHERE userId = ?";
        $updateStmt = $conn->prepare($updateSql);

        if ($updateStmt) {
            $updateStmt->bind_param("is", $newTokenValue, $userId);
            if ($updateStmt->execute()) {
                $response = [
                    'status' => true,
                    'message' => $message_status,
                    'parameters' => [
                        'token' => $newTokenValue,
                        'created_at' => get_local_time_string() // UTC+3
                    ]
                ];
            } else {
                $response = ['status' => false, 'message' => 'Token güncellenirken DB hatası: ' . $updateStmt->error];
            }
            $updateStmt->close();
        } else {
            $response = ['status' => false, 'message' => 'Güncelleme sorgusu hazırlanamadı: ' . $conn->error];
        }
    } else {
        // Güncelleme yapılmadı, var olan created_at UTC+3 döndürülür
        $response = [
            'status' => true,
            'message' => $message_status,
            'parameters' => [
                'token' => $currentToken,
                'created_at' => get_local_time_string($created_at_db)
            ]
        ];
    }
} else {
    // Kullanıcı token tablosunda yok — yeni ekle
    $insertSql = "INSERT INTO tokens (userId, token, created_at) VALUES (?, 1, NOW())";
    $insertStmt = $conn->prepare($insertSql);

    if ($insertStmt) {
        $insertStmt->bind_param("s", $userId);
        if ($insertStmt->execute()) {
            $response = [
                'status' => true,
                'message' => 'İlk token verildi.',
                'parameters' => [
                    'token' => 1,
                    'created_at' => get_local_time_string() // UTC+3
                ]
            ];
        } else {
            $response = ['status' => false, 'message' => 'Token verilemedi: ' . $insertStmt->error];
        }
        $insertStmt->close();
    } else {
        $response = ['status' => false, 'message' => 'Ekleme sorgusu hazırlanamadı: ' . $conn->error];
    }
}

$stmt->close();
$conn->close();

// JSON çıktısı
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
