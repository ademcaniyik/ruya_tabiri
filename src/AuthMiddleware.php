<?php

namespace App;

require_once __DIR__ . '/JWTAuth.php';

class AuthMiddleware {
    public $jwtAuth; // Token'a erişim için public yaptık
    
    public function __construct() {
        $this->jwtAuth = new JWTAuth();
    }    /**
     * JWT token'ı doğrular ve kullanıcı bilgilerini döndürür
     * @return array|false Token geçerliyse kullanıcı bilgileri, değilse false
     */
    public function authenticate() {
        $token = $this->jwtAuth->getBearerToken();

        if (!$token) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => false,
                'message' => 'Yetkilendirme hatası',
                'parameters' => [
                    'error' => 'Token bulunamadı',
                    'error_code' => 'AUTH_NO_TOKEN',
                    'status_code' => 401
                ]
            ]);
            return false;
        }

        $decoded = $this->jwtAuth->validateToken($token);
        if (!$decoded) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => false,
                'message' => 'Yetkilendirme hatası',
                'parameters' => [
                    'error' => 'Geçersiz veya süresi dolmuş token',
                    'error_code' => 'AUTH_INVALID_TOKEN',
                    'status_code' => 401
                ]
            ]);
            return false;
        }

        return $decoded;
    }
}
