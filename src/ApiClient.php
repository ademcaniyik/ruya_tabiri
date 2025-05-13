<?php

// src/ApiClient.php

class ApiClient {
    
    private $apiUrl;
    private $apiKey;

    public function __construct($apiUrl, $apiKey) {
        $this->apiUrl = $apiUrl;
        $this->apiKey = $apiKey;
    }

    public function sendRequest($data) {
        // cURL ile istek gönderme
        $ch = curl_init($this->apiUrl);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . $this->apiKey
        ]);
        
        $response = curl_exec($ch);
        
        if ($response === false) {
            $this->logError(curl_error($ch));
            return false;
        }
        
        curl_close($ch);
        
        return json_decode($response, true);
    }

    private function logError($message) {
        // Hata mesajlarını log dosyasına yazma
        file_put_contents(__DIR__ . '/../logs/error_log.txt', $message . PHP_EOL, FILE_APPEND);
    }
}
