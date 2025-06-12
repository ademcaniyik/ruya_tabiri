<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';

// JSON verilerini al
$input = json_decode(file_get_contents("php://input"), true);

// GET veya POST parametrelerini kontrol et
$userId = $_GET['userId'] ?? $input['userId'] ?? null;
$tokenChange = $_GET['token'] ?? $input['token'] ?? null;

// Parametreleri kontrol et
if (!$userId || !isset($tokenChange)) {
    echo json_encode([
        'status' => false,
        'message' => 'userId ve token parametreleri gereklidir.'
    ]);
    exit;
}

// Kullanıcının token bilgisini kontrol et
$sql = "SELECT t.token FROM tokens t WHERE t.userId = ? ORDER BY t.created_at DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $currentToken = (int)$row['token'];
    $newToken = $currentToken + (int)$tokenChange;
    
    // Token negatif olamaz kontrolü
    if ($newToken < 0) {
        echo json_encode([
            'status' => false,
            'message' => 'Token değeri negatif olamaz',
            'parameters' => null
        ]);
        exit;
    }    // Token tablosuna yeni kayıt ekle
    $updateSql = "INSERT INTO tokens (userId, token, created_at) VALUES (?, ?, NOW())";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("si", $userId, $newToken);
    
    if ($updateStmt->execute()) {
        echo json_encode([
            'status' => true,
            'message' => 'Token güncellendi',
            'parameters' => [
                'userId' => $userId,
                'newTokenValue' => $newToken,
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        echo json_encode([
            'status' => false,
            'message' => 'Token güncellenirken hata oluştu',
            'parameters' => null
        ]);
    }
} else {
    echo json_encode([
        'status' => false,
        'message' => 'Kullanıcı bulunamadı',
        'parameters' => null
    ]);
}

$conn->close();
?>