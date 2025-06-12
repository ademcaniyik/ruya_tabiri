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
    }    public function getAuthorizationHeader() {
        // Debug için header bilgilerini logla
        error_log('SERVER variables: ' . print_r($_SERVER, true));
        
        // 1. Doğrudan Authorization header'ı
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            error_log('Found HTTP_AUTHORIZATION: ' . $_SERVER['HTTP_AUTHORIZATION']);
            return trim($_SERVER['HTTP_AUTHORIZATION']);
        }
        
        // 2. Apache mod_rewrite ile gelen header
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            error_log('Found REDIRECT_HTTP_AUTHORIZATION: ' . $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
            return trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }
        
        // 3. Authorization header'ını özel formatta kontrol et
        if (isset($_SERVER['AUTHORIZATION'])) {
            error_log('Found AUTHORIZATION: ' . $_SERVER['AUTHORIZATION']);
            return trim($_SERVER['AUTHORIZATION']);
        }

        // 4. getallheaders() ile kontrol
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            error_log('getallheaders(): ' . print_r($headers, true));
            
            // Case-insensitive olarak Authorization header'ını ara
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    error_log('Found Authorization in getallheaders: ' . $value);
                    return trim($value);
                }
            }
        }
        
        // 5. apache_request_headers() ile kontrol
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            error_log('apache_request_headers(): ' . print_r($headers, true));
            
            // Case-insensitive olarak Authorization header'ını ara
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    error_log('Found Authorization in apache_request_headers: ' . $value);
                    return trim($value);
                }
            }
        }
        
        error_log('No Authorization header found');
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