<?php

require_once __DIR__ . '/../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTAuth {
    private $secret_key;
    private $algorithm;      public function __construct() {
        require_once __DIR__ . '/../config/EnvConfig.php';
        $this->secret_key = Config::get('JWT_SECRET', 'ruya_tabiri_secret_key_2025');
        $this->algorithm = 'HS256';
    }

    public function generateToken($userId, $email) {
        $issuedAt = time();
        $expire = $issuedAt + (60 * 60 * 24); // 24 saat geçerli

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'user_id' => $userId,
            'email' => $email
        ];

        return JWT::encode($payload, $this->secret_key, $this->algorithm);
    }

    public function validateToken($token) {
        try {
            $decoded = JWT::decode($token, new Key($this->secret_key, $this->algorithm));
            return $decoded;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getAuthorizationHeader() {
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        return $headers;
    }

    public function getBearerToken() {
        $headers = $this->getAuthorizationHeader();
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }
}
