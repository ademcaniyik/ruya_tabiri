<?php

namespace App;

require_once __DIR__ . '/JWTAuth.php';

class AuthMiddleware {
    public $jwtAuth;

    public function __construct() {
        $this->jwtAuth = new JWTAuth();
    }

    /**
     * @return array|false
     */    public function authenticate($token = null) {
        // Eğer token parametre olarak gelmişse onu kullan, 
        // gelmemişse header'dan almayı dene
        if (!$token) {
            $token = $this->jwtAuth->getBearerToken();
        }

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

        $tokenData = $this->jwtAuth->validateToken($token);
        
        if ($tokenData === null) {
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

        return $tokenData;
    }
}
