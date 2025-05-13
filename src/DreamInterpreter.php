<?php

// src/DreamInterpreter.php

class DreamInterpreter {

    private $apiClient;
    private $dreamHistory;

    public function __construct($apiUrl, $apiKey, $dreamHistory) {
        $this->apiClient = new ApiClient($apiUrl, $apiKey);
        $this->dreamHistory = $dreamHistory;
    }

    public function interpretDream($userId, $dreamDescription, $language = 'tr') {
        // Rüya tabiri için sistem mesajı
        $systemMessage = "Sen bir rüya tabircisisin. Kullanıcının yazdığı her şeyin bir rüya tanımı olduğunu varsay ve bu rüyayı anlamlı, derinlemesine, kültürel ve psikolojik perspektiflerden yorumla. Rüyayı samimi bir arkadaş gibi, empatiyle ve pozitif bir dille değerlendir. Kullanıcıya yol gösterici, aydınlatıcı ve ilham verici bir yorum yap.";

        // API'ye gönderilecek veri
        $data = [
            "model" => "gpt-3.5-turbo",  // ya da gpt-3.5-turbo
            "messages" => [
                ["role" => "system", "content" => $systemMessage],
                ["role" => "user", "content" => $dreamDescription]
            ],
            "temperature" => 1
        ];
        
        // API'ye istek gönderme
        $response = $this->apiClient->sendRequest($data);
        
        if ($response) {
            $interpretation = $response['choices'][0]['message']['content'];
            
            // Rüyayı veritabanına kaydet
            $this->dreamHistory->saveDream($userId, $dreamDescription, $interpretation);
            
            return $interpretation;
        }
        
        return "Rüya tabiri yapılamadı.";
    }
}
