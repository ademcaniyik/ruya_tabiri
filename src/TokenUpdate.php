<?php
header('Content-Type: application/json; charset=utf-8');

// Veritabanı bağlantısı ve diğer yapılandırmalar bu dosyadan yüklenir.
// $conn değişkeninin bu dosya içinde tanımlanmış olduğu varsayılır.
require_once __DIR__ . '/../config/config.php';

// JSON verilerini al (POST/PUT/PATCH istekleri için)
$input = json_decode(file_get_contents("php://input"), true);

// GET veya POST/JSON parametrelerini kontrol et
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

// -------------------------------------------------------------------------
// 1. Kullanıcının mevcut token bilgisini kontrol et (Son Kaydı Al)
// -------------------------------------------------------------------------

// Not: Bu sorgu, kullanıcının en son token değerini almak için doğru bir yaklaşımdır.
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
            'parameters' => [
                'current_token' => $currentToken,
                'token_change' => (int)$tokenChange
            ]
        ]);
        exit;
    }
    
    // -------------------------------------------------------------------------
    // 2. Token tablosundaki MEVCUT kaydı GÜNCELLE (INSERT yerine UPDATE kullanıldı)
    // -------------------------------------------------------------------------
    // HATA DÜZELTİLDİ: 'UPDATE ... SET ... WHERE' yapısı kullanıldı.
    $updateSql = "UPDATE tokens SET token = ?, created_at = NOW() WHERE userId = ?";
    $updateStmt = $conn->prepare($updateSql);
    
    // Bağlama SIRASI VE TİPİ DÜZELTİLDİ: integer ($newToken) sonra string ($userId)
    $updateStmt->bind_param("is", $newToken, $userId); 
    
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
        // execute() başarısız olursa
        echo json_encode([
            'status' => false,
            'message' => 'Token güncellenirken hata oluştu: ' . $updateStmt->error,
            'parameters' => null
        ]);
    }
} else {
    // Kullanıcı bulunamazsa
    echo json_encode([
        'status' => false,
        'message' => 'Kullanıcı bulunamadı veya hiç token kaydı yok',
        'parameters' => null
    ]);
}

// -------------------------------------------------------------------------
// Bağlantıyı kapat
// -------------------------------------------------------------------------
if (isset($conn)) {
    $conn->close();
}
?>