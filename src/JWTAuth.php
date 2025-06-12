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
        error_log('Looking for Authorization header...');

        // 1. Apache CGI/FastCGI için özel kontrol
        foreach ($_SERVER as $key => $value) {
            error_log("Checking SERVER variable: $key");
            if (substr($key, 0, 5) == 'HTTP_') {
                $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                if ($header === 'Authorization') {
                    error_log("Found in SERVER variables: $value");
                    return $value;
                }
            }
        }

        // 2. apache_request_headers() ile kontrol
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            error_log('Apache headers found: ' . print_r($headers, true));
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    error_log("Found in apache_request_headers: $value");
                    return $value;
                }
            }
        }

        // 3. getallheaders() ile kontrol
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            error_log('PHP headers found: ' . print_r($headers, true));
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    error_log("Found in getallheaders: $value");
                    return $value;
                }
            }
        }

        // 4. HTTP_AUTHORIZATION veya REDIRECT_HTTP_AUTHORIZATION kontrolü
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            error_log("Found in HTTP_AUTHORIZATION: " . $_SERVER['HTTP_AUTHORIZATION']);
            return $_SERVER['HTTP_AUTHORIZATION'];
        }

        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            error_log("Found in REDIRECT_HTTP_AUTHORIZATION: " . $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        error_log('No Authorization header found after all attempts');
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