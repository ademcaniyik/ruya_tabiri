<?php
// Mobil log ucu: her istekte çağrılır, hızlı ve hatasız dönmeli. Log web kökünün DIŞINDA tutulur.
$dir = dirname(__DIR__) . '/logs';
if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
}

$raw = (string) file_get_contents('php://input');
$decoded = json_decode($raw, true);
$text = (is_array($decoded) && isset($decoded['log'])) ? (string) $decoded['log'] : $raw;
// Tek kayıt 20 KB ile sınırlı (kötüye kullanıma karşı)
$text = mb_substr($text, 0, 20000, 'UTF-8');

@file_put_contents($dir . '/app_log.txt', '[' . date('Y-m-d H:i:s') . '] ' . $text . "\n" . str_repeat('-', 40) . "\n", FILE_APPEND | LOCK_EX);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['status' => true, 'message' => 'OK'], JSON_UNESCAPED_UNICODE);
