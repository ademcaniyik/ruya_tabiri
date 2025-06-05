<?php
require_once __DIR__ . '/../config/EnvConfig.php';

// Sadece development ortamında çalıştır
if (Config::get('APP_ENV') === 'development') {
    header('Content-Type: application/json');
    echo json_encode(Config::getAllEnv(), JSON_PRETTY_PRINT);
} else {
    http_response_code(403);
    echo json_encode(['error' => 'Bu endpoint sadece development ortamında kullanılabilir']);
}
