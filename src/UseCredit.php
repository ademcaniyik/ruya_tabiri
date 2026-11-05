<?php
// Kredi kontrol / kullanım (mobil şu an çağırmıyor). action: "check" | "use"
require_once __DIR__ . '/../config/config.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    json_out(false, 'Geçersiz veri');
}
$userId = trim((string) ($input['userId'] ?? ''));
$action = (string) ($input['action'] ?? 'check');
if ($userId === '') {
    json_out(false, 'userId parametresi gereklidir.');
}

$stmt = $conn->prepare('SELECT token FROM tokens WHERE userId = ? LIMIT 1');
$stmt->bind_param('s', $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) {
    json_out(false, 'Kullanıcı bulunamadı.');
}

if ($action === 'use') {
    $up = $conn->prepare('UPDATE tokens SET token = token - 1, created_at = IF(token = 0, NOW(), created_at) WHERE userId = ? AND token > 0');
    $up->bind_param('s', $userId);
    if (!$up->execute() || $up->affected_rows < 1) {
        json_out(false, 'Yetersiz kredi', ['token' => 0]);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
}

json_out(true, 'OK', ['token' => (int) $row['token']]);
