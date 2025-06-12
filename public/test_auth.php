<?php
require_once '../vendor/autoload.php';
require_once '../src/JWTAuth.php';
require_once '../src/AuthMiddleware.php';

use App\AuthMiddleware;
use App\JWTAuth;

// Set headers for JSON response
header('Content-Type: application/json; charset=utf-8');

// Manually set the Authorization header for testing
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpYXQiOjE3NDk3MjM4OTMsImV4cCI6MTc0OTgxMDI5MywidXNlcl9pZCI6IjAwMTQxMS41MTM3MDcwMDQzZTQ0Y2NiYTgxMmIzMWUwMTJkMGNkMy4xMzQ4IiwiZW1haWwiOiJtdXJhdGNhbHMyQGljbG91ZC5jb20ifQ.RXpz2xCyT8lxxiDT0Khc3ADkoRUoSD433n9tkAAGKEY';

// Initialize AuthMiddleware
$auth = new AuthMiddleware();

// Try to authenticate
$result = $auth->authenticate();

if ($result === false) {
    // Authentication failed - the error response is already sent by authenticate()
    exit();
}

// If we get here, authentication was successful
echo json_encode([
    'status' => true,
    'message' => 'Token doğrulama başarılı',
    'parameters' => [
        'decoded_token' => $result,
        'user_id' => $result['user_id'] ?? null,
        'email' => $result['email'] ?? null
    ]
]);
