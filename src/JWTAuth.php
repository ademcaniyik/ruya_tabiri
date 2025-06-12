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
            return null;
        }
    }

    public function getAuthorizationHeader() {
        $headers = null;

        // 1. Apache header
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
            error_log("Header bulundu: HTTP_AUTHORIZATION");
        }
        // 2. Nginx header
        elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
            error_log("Header bulundu: REDIRECT_HTTP_AUTHORIZATION");
        }
        // 3. getallheaders() function
        elseif (function_exists('getallheaders')) {
            $h = getallheaders();
            if (isset($h['Authorization'])) {
                $headers = trim($h['Authorization']);
                error_log("Header bulundu: getallheaders()");
            }
        }
        // 4. apache_request_headers() function
        elseif (function_exists('apache_request_headers')) {
            $h = apache_request_headers();
            if (isset($h['Authorization'])) {
                $headers = trim($h['Authorization']);
                error_log("Header bulundu: apache_request_headers()");
            }
        }
        
        // 5. JSON request body'den kontrol (son çare)
        if (!$headers && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['authorization'])) {
                $headers = trim($input['authorization']);
                error_log("Header bulundu: JSON body");
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