<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/GetAccessToken.php';

$serviceAccountKeyPath = __DIR__ . '/php-jwt-main/ruya-tabiri-79c05-ff951a78ed22.json';
$accessToken = getAccessToken($serviceAccountKeyPath);
$fcmUrl = "https://fcm.googleapis.com/v1/projects/ruya-tabiri-79c05/messages:send";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedTokens = $_POST['device_tokens'] ?? [];
    $notificationTitle = $_POST['notification_title'] ?? '';
    $notificationBody = $_POST['notification_body'] ?? '';

    if (empty($selectedTokens)) {
        echo "<p style='color: red;'>Lütfen en az bir cihaz seçin.</p>";
    } elseif (empty($notificationTitle) || empty($notificationBody)) {
        echo "<p style='color: red;'>Başlık ve içerik boş olamaz.</p>";
    } else {
        foreach ($selectedTokens as $deviceToken) {
            $notification = [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $notificationTitle,
                        'body' => $notificationBody,
                    ],
                    'data' => [
                        'extra_info' => 'Bu bir test mesajıdır',
                    ],
                ],
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $fcmUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $accessToken",
                "Content-Type: application/json",
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notification));

            $response = curl_exec($ch);
            curl_close($ch);

            echo "<p>Device Token: $deviceToken - Response: $response</p>";
        }
    }
}

// Veritabanından cihaz tokenlarını çek
$sql = "SELECT device_token FROM device_tokens";
$result = $conn->query($sql);
$deviceTokens = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $deviceTokens[] = $row['device_token'];
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bildirim Gönderme Paneli</title>
</head>
<body>
    <h1>Bildirim Gönderme Paneli</h1>

    <form method="POST" action="">
        <h3>Bildirimi Kimlere Göndermek İstiyorsunuz?</h3>
        <label>
            <input type="checkbox" id="select_all"> Hepsini Seç
        </label>
        <div style="margin: 10px 0;">
            <?php foreach ($deviceTokens as $token): ?>
                <label style="display: block;">
                    <input type="checkbox" name="device_tokens[]" value="<?= htmlspecialchars($token) ?>">
                    <?= htmlspecialchars($token) ?>
                </label>
            <?php endforeach; ?>
        </div>

        <h3>Bildirim İçeriği</h3>
        <label for="notification_title">Başlık:</label><br>
        <input type="text" id="notification_title" name="notification_title" style="width: 100%; margin-bottom: 10px;" required><br>

        <label for="notification_body">İçerik:</label><br>
        <textarea id="notification_body" name="notification_body" style="width: 100%; height: 100px; margin-bottom: 10px;" required></textarea><br>

        <button type="submit" style="padding: 10px 20px;">Bildirimi Gönder</button>
    </form>

    <script>
        document.getElementById('select_all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="device_tokens[]"]');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
    </script>
</body>
</html>
