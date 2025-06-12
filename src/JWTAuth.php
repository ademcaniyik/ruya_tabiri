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
    }    public function getAuthorizationHeader() {
        $headers = null;
        
        // Debug için tüm header'ları logla
        $allHeaders = getallheaders();
        error_log("Gelen Tüm Headers: " . print_r($allHeaders, true));
        
        // Tüm olası header kombinasyonlarını dene
        $possibleHeaders = [
            'HTTP_AUTHORIZATION',
            'Authorization',
            'REDIRECT_HTTP_AUTHORIZATION',
            'HTTP_X_AUTHORIZATION',
            'X-Authorization'
        ];

        // $_SERVER'dan kontrol
        foreach ($possibleHeaders as $headerKey) {
            if (isset($_SERVER[$headerKey])) {
                $headers = trim($_SERVER[$headerKey]);
                error_log("Header bulundu: $headerKey = $headers");
                break;
            }
        }

        // getallheaders() ile kontrol
        if (!$headers && function_exists('getallheaders')) {
            foreach ($allHeaders as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    $headers = trim($value);
                    error_log("getallheaders() ile bulundu: $headers");
                    break;
                }
            }
        }

        // apache_request_headers() ile son kontrol
        if (!$headers && function_exists('apache_request_headers')) {
            $apacheHeaders = apache_request_headers();
            foreach ($apacheHeaders as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    $headers = trim($value);
                    error_log("apache_request_headers() ile bulundu: $headers");
                    break;
                }
            }
        }
        
        error_log("Final Authorization Header: " . ($headers ?: 'Bulunamadı'));
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
