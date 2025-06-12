<?php
header('Content-Type: application/json');

// Tüm headerları görüntüle
$headers = getallheaders();
$serverVars = $_SERVER;

echo json_encode([
    'headers' => $headers,
    'server' => $serverVars,
    'auth_header' => isset($headers['Authorization']) ? $headers['Authorization'] : 'Yok',
    'auth_server' => isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : 'Yok'
], JSON_PRETTY_PRINT);
