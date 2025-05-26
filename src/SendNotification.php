<?php
require_once __DIR__ . '/GetAccessToken.php';
include_once __DIR__ . '/../config/config.php';

$serviceAccountKeyPath = __DIR__ . '/php-jwt-main/ruya-tabiri-79c05-ff951a78ed22.json';
$accessToken = getAccessToken($serviceAccountKeyPath);

if (!$accessToken) {
    die('Access token alınamadı.');
}

if ($conn->connect_error) {
    die("Bağlantı hatası: " . $conn->connect_error);
}

$sql = "SELECT device_token FROM device_tokens";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $fcmUrl = "https://fcm.googleapis.com/v1/projects/ruya-tabiri-79c05/messages:send";

    while ($row = $result->fetch_assoc()) {
        $deviceToken = $row['device_token'];

        $notification = [
            'message' => [
                'token' => $deviceToken,
                'notification' => [
                    'title' => '',
                    'body' => '',
                ],
                'data' => [
                    'key1' => 'value1',
                    'key2' => 'value2',
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

        echo "Device Token: $deviceToken<br>";
        echo "Response: $response<br>";
    }
} else {
    echo "Veritabanında kayıtlı device_token bulunamadı.";
}

$conn->close();
?>
