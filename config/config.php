<?php

// config.php

define('API_KEY', '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');  // OpenAI API anahtar覺n覺z覺 buraya ekleyin
define('API_URL', 'https://api.openai.com/v1/chat/completions');

$servername = "localhost:3306";
$username = "acd1f4ftwarecom_root"; // Veritabanı kullanıcı adı
$password = "acdi'root321."; // Veritabanı şifresi
$dbname = "acd1f4ftwarecom_ruyaTabiri";

// Bağlantı oluştur
$conn = new mysqli($servername, $username, $password, $dbname);

// Bağlantıyı kontrol et
if ($conn->connect_error) {
    die("Bağlantı hatası: " . $conn->connect_error);
}
if (!$conn->set_charset("utf8")) {
    die("Karakter seti ayarlanamadı: " . $conn->error);
}