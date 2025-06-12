<?php
header('Content-Type: application/json');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Raw request headers'ı al
$rawHeaders = [];
if (function_exists('getallheaders')) {
    $rawHeaders = getallheaders();
}

// Apache request headers
$apacheHeaders = [];
if (function_exists('apache_request_headers')) {
    $apacheHeaders = apache_request_headers();
}

// Authorization header'ı özel olarak kontrol et
$authHeader = null;
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
} elseif (isset($rawHeaders['Authorization'])) {
    $authHeader = $rawHeaders['Authorization'];
} elseif (isset($apacheHeaders['Authorization'])) {
    $authHeader = $apacheHeaders['Authorization'];
}

echo json_encode([
    'raw_headers' => $rawHeaders,
    'apache_headers' => $apacheHeaders,
    'server_vars' => $_SERVER,
    'auth_header_found' => $authHeader ?? 'Yok',
    'input_data' => json_decode(file_get_contents('php://input'), true)
], JSON_PRETTY_PRINT);
