<?php
header('Content-Type: application/json; charset=utf-8');

// config.php dosyasını dahil et
require_once __DIR__ . '/../config/config.php';

// JSON verisini al
$input = json_decode(file_get_contents("php://input"), true);

// JSON verisi düzgün alınmazsa hata döndür
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'status' => false,
        'message' => 'Geçersiz JSON verisi',
        'parameters' => null
    ]);
    exit;
}

// Veritabanı bağlantısını kontrol et
if ($conn->connect_error) {
    echo json_encode([
        'status' => false,
        'message' => 'Veritabanı bağlantı hatası: ' . $conn->connect_error,
        'parameters' => null
    ]);
    exit;
}

// userId parametresini kontrol et
if (!isset($input['userId']) || empty($input['userId'])) {
    echo json_encode([
        'status' => false,
        'message' => 'userId parametresi gereklidir.',
        'parameters' => null
    ]);
    exit;
}

$userId = $conn->real_escape_string($input['userId']);

// Kullanıcının var olup olmadığını kontrol et
$checkUserSql = "SELECT id FROM users WHERE userId = ?";
$checkStmt = $conn->prepare($checkUserSql);
$checkStmt->bind_param("s", $userId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    echo json_encode([
        'status' => false,
        'message' => 'Kullanıcı bulunamadı.',
        'parameters' => null
    ]);
    exit;
}

$user = $checkResult->fetch_assoc();
$userDbId = $user['id'];

// Transaction başlat
$conn->autocommit(FALSE);

try {
    $deletedRecords = [
        'users' => 0,
        'device_tokens' => 0,
        'tokens' => 0,
        'dream_history' => 0
    ];

    // 1. device_tokens tablosundan sil
    $deleteDeviceTokensSql = "DELETE FROM device_tokens WHERE user_id = ?";
    $deleteDeviceStmt = $conn->prepare($deleteDeviceTokensSql);
    $deleteDeviceStmt->bind_param("i", $userDbId);
    $deleteDeviceStmt->execute();
    $deletedRecords['device_tokens'] = $deleteDeviceStmt->affected_rows;

    // 2. tokens tablosundan sil
    $deleteTokensSql = "DELETE FROM tokens WHERE userId = ?";
    $deleteTokensStmt = $conn->prepare($deleteTokensSql);
    $deleteTokensStmt->bind_param("s", $userId);
    $deleteTokensStmt->execute();
    $deletedRecords['tokens'] = $deleteTokensStmt->affected_rows;

    // 3. dream_history tablosundan sil
    $deleteDreamHistorySql = "DELETE FROM dream_history WHERE user_id = ?";
    $deleteDreamStmt = $conn->prepare($deleteDreamHistorySql);
    $deleteDreamStmt->bind_param("s", $userId);
    $deleteDreamStmt->execute();
    $deletedRecords['dream_history'] = $deleteDreamStmt->affected_rows;

    // 4. users tablosundan sil
    $deleteUserSql = "DELETE FROM users WHERE userId = ?";
    $deleteUserStmt = $conn->prepare($deleteUserSql);
    $deleteUserStmt->bind_param("s", $userId);
    $deleteUserStmt->execute();
    $deletedRecords['users'] = $deleteUserStmt->affected_rows;

    // Transaction'ı onayla
    $conn->commit();

    echo json_encode([
        'status' => true,
        'message' => 'Kullanıcının tüm verileri başarıyla silindi.',
        'parameters' => [
            'userId' => $userId,
            'deleted_records' => $deletedRecords
        ]
    ]);

} catch (Exception $e) {
    // Hata durumunda transaction'ı geri al
    $conn->rollback();
    
    echo json_encode([
        'status' => false,
        'message' => 'Kullanıcı verileri silinirken hata oluştu: ' . $e->getMessage(),
        'parameters' => null
    ]);
}

// Autocommit'i geri aç
$conn->autocommit(TRUE);

// Bağlantıyı kapat
$conn->close();
?>