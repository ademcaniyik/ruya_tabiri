<?php
// Hesap silme. Mobil gövdeli GET gönderir; gövde (php://input) ve query string birlikte okunur.
require_once __DIR__ . '/../config/config.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}
$userId = trim((string) ($input['userId'] ?? ($_GET['userId'] ?? '')));
if ($userId === '') {
    json_out(false, 'userId parametresi gereklidir.');
}

$stmt = $conn->prepare('SELECT id FROM users WHERE userId = ?');
$stmt->bind_param('s', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) {
    json_out(false, 'Kullanıcı bulunamadı.');
}
$userDbId = (int) $user['id'];

$deleted = ['users' => 0, 'device_tokens' => 0, 'tokens' => 0, 'dream_history' => 0];
$steps = [
    'device_tokens' => ['DELETE FROM device_tokens WHERE user_id = ?', 'i', $userDbId],
    'tokens'        => ['DELETE FROM tokens WHERE userId = ?', 's', $userId],
    'dream_history' => ['DELETE FROM dream_history WHERE user_id = ?', 's', $userId],
    'users'         => ['DELETE FROM users WHERE userId = ?', 's', $userId],
];

$conn->begin_transaction();
foreach ($steps as $key => [$sql, $type, $value]) {
    $st = $conn->prepare($sql);
    if (!$st || !$st->bind_param($type, $value) || !$st->execute()) {
        $conn->rollback();
        error_log('UserDelete hatasi (' . $key . '): ' . $conn->error);
        json_out(false, 'Hesap silinemedi');
    }
    $deleted[$key] = (int) $st->affected_rows;
}
$conn->commit();

json_out(true, 'Hesap silindi', ['userId' => $userId, 'deleted_records' => $deleted]);
