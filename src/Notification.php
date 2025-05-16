<?php
header('Content-Type: application/json; charset=utf-8');
include_once __DIR__ . '/../config/config.php';

date_default_timezone_set('Europe/Istanbul');

// Firebase server key, bunu Firebase konsolundan alabilirsin.
// Bu örnekte key direkt config dosyasından veya buraya koyabilirsin.
$firebaseServerKey = '873204a366a60fe088acc43134449f048b37fd57';

// Bildirim başlığı ve mesajını al (GET veya POST olabilir, burası GET olarak örnek)
$title = isset($_GET['title']) ? trim($_GET['title']) : null;
$body = isset($_GET['body']) ? trim($_GET['body']) : null;

if (!$title || !$body) {
    echo json_encode([
        'status' => false,
        'message' => 'Siktim seniiii.'
    ]);
    exit;
}

// Veritabanı bağlantı kontrolü
if ($conn->connect_error) {
    echo json_encode([
        'status' => false,
        'message' => 'Veritabanı bağlantı hatası: ' . $conn->connect_error
    ]);
    exit;
}

// devices_tokens tablosundan device_token'ları çek
$sql = "SELECT device_token FROM devices_tokens WHERE device_token IS NOT NULL AND device_token != ''";
$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    echo json_encode([
        'status' => false,
        'message' => 'Kayıtlı cihaz tokenı bulunamadı.'
    ]);
    exit;
}

// Tokenları array'e al
$tokens = [];
while ($row = $result->fetch_assoc()) {
    $tokens[] = $row['device_token'];
}

// Firebase FCM endpoint
$fcmUrl = 'https://fcm.googleapis.com/fcm/send';

// Bildirim verisi (notification kısmı)
$notification = [
    'title' => $title,
    'body' => $body,
    'sound' => 'default'
];

// FCM payload
$payload = [
    'registration_ids' => $tokens,
    'notification' => $notification,
    'priority' => 'high'
];

// HTTP başlıkları
$headers = [
    'Authorization: key=' . $firebaseServerKey,
    'Content-Type: application/json'
];

// cURL ile POST isteği yap
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $fcmUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo json_encode([
        'status' => false,
        'message' => 'cURL hatası: ' . curl_error($ch)
    ]);
    curl_close($ch);
    exit;
}

curl_close($ch);

// Sonucu JSON olarak döndür
echo json_encode([
    'status' => $httpcode === 200,
    'http_code' => $httpcode,
    'response' => json_decode($response, true)
]);

$conn->close();
