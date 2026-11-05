<?php
// Kullanıcı sorgulama. Kullanıcı yoksa status:true ve parameters olmadan döner
// (mobil bunu "kullanıcı silinmiş" sayıp oturumu temizler).
require_once __DIR__ . '/../config/config.php';

$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
    json_out(false, 'Geçersiz JSON verisi');
}

$userId = trim((string) ($input['userId'] ?? ''));
if ($userId === '') {
    json_out(false, 'userId parametresi gereklidir.');
}

$stmt = $conn->prepare('SELECT userId FROM users WHERE userId = ? LIMIT 1');
$stmt->bind_param('s', $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    json_out(true, 'Kullanıcı bulunamadı');
}

json_out(true, 'OK', ['user' => $row['userId']]);
