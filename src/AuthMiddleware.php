<?php

require_once __DIR__ . '/JWTAuth.php';

class AuthMiddleware {
    private $jwtAuth;
    
    public function __construct() {
        $this->jwtAuth = new JWTAuth();
    }

    public function authenticate() {
        $token = $this->jwtAuth->getBearerToken();

        if (!$token) {
            http_response_code(401);
            echo json_encode([
                'status' => false,
                'message' => 'Token bulunamadı',
                'parameters' => null
            ]);
            exit();
        }

        $decoded = $this->jwtAuth->validateToken($token);
        if (!$decoded) {
            http_response_code(401);
            echo json_encode([
                'status' => false,
                'message' => 'Geçersiz token',
                'parameters' => null
            ]);
            exit();
        }

        return $decoded;
    }
}
