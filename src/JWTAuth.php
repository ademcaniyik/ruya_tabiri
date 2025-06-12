<?php

namespace App;

require_once __DIR__ . '/../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Config;

class JWTAuth {
    private $secret_key;
    private $algorithm;
    
    public function __construct() {
        require_once __DIR__ . '/../config/EnvConfig.php';
        $this->secret_key = Config::get('JWT_SECRET', 'ruya_tabiri_secret_key_2025');
        $this->algorithm = 'HS256';
    }

    public function generateToken($userId, $email) {
        $issuedAt = time();
        $expiration = Config::get('JWT_EXPIRATION', 86400); // Varsayılan: 24 saat
        $expire = $issuedAt + $expiration;

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
            return (array)$decoded;
        } catch (\Exception $e) {
            error_log('JWT Validation Error: ' . $e->getMessage());
            return null;
        }
    }

    public function getAuthorizationHeader() {
        $headers = null;

        // 1. Apache header
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return trim($_SERVER['HTTP_AUTHORIZATION']);
        }
        
        // 2. Nginx header
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }
        
        // 3. getallheaders() function
        if (function_exists('getallheaders')) {
            $h = getallheaders();
            if (isset($h['Authorization'])) {
                return trim($h['Authorization']);
            }
        }
        
        // 4. apache_request_headers() function
        if (function_exists('apache_request_headers')) {
            $h = apache_request_headers();
            if (isset($h['Authorization'])) {
                return trim($h['Authorization']);
            }
        }
        
        return null;
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