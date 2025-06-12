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
        error_log('REQUEST headers: ' . print_r(getallheaders(), true));
        
        // HTTP_AUTHORIZATION headerını kontrol et (standart yöntem)
        $auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : (
            isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) ? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] : null
        );
        
        if ($auth) {
            error_log('Found direct Authorization header: ' . $auth);
            return trim($auth);
        }

        // CGI/FastCGI ile gelen headerları kontrol et
        if (isset($_SERVER['AUTHORIZATION'])) {
            error_log('Found CGI Authorization: ' . $_SERVER['AUTHORIZATION']);
            return trim($_SERVER['AUTHORIZATION']);
        }

        // PHP input stream'den raw headerları oku
        if (!$auth) {
            $requestHeaders = apache_request_headers();
            error_log('Apache request headers: ' . print_r($requestHeaders, true));
            
            foreach ($requestHeaders as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    error_log('Found Authorization in request headers: ' . $value);
                    return trim($value);
                }
            }
        }

        // HTTP_ALL_HEADERS'dan kontrol et
        if (isset($_SERVER['HTTP_ALL_HEADERS'])) {
            $allHeaders = json_decode($_SERVER['HTTP_ALL_HEADERS'], true);
            if (isset($allHeaders['Authorization'])) {
                error_log('Found Authorization in ALL_HEADERS: ' . $allHeaders['Authorization']);
                return trim($allHeaders['Authorization']);
            }
        }

        // En son çare olarak raw input'u kontrol et
        $headers = trim(getallheaders()['Authorization'] ?? '');
        if ($headers) {
            error_log('Found Authorization in raw headers: ' . $headers);
            return trim($headers);
        }

        error_log('No Authorization header found after all attempts');
        error_log('Available SERVER variables for debugging: ' . print_r(array_keys($_SERVER), true));
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