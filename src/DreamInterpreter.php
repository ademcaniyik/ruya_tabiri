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
            "model" => "gpt-3.5-turbo",
            "messages" => [
                ["role" => "system", "content" => $systemMessage],
                ["role" => "user", "content" => $dreamDescription]
            ],
            "temperature" => 1
        ];

        try {
            // API'ye istek gönderme
            $response = $this->apiClient->sendRequest($data);

            // Yanıt kontrolü
            if (!$response || !isset($response['choices'])) {
                error_log("API'den geçersiz bir yanıt alındı: " . json_encode($response));
                return "API hizmeti beklenmeyen bir yanıt döndürdü. Lütfen daha sonra tekrar deneyin.";
            }

            // Yanıt içeriğini kontrol et
            if (isset($response['choices'][0]['message']['content'])) {
                $interpretation = $response['choices'][0]['message']['content'];
            } else {
                error_log("Yanıtta 'choices[0][message][content]' eksik: " . json_encode($response));
                return "API yanıtı eksik veya beklenen formatta değil.";
            }

            // Rüyayı veritabanına kaydet
            if (!empty($interpretation)) {
                $this->dreamHistory->saveDream($userId, $dreamDescription, $interpretation);
                return $interpretation;
            } else {
                error_log("Boş bir yorum döndürüldü. Yanıt: " . json_encode($response));
                return "Rüya tabiri yapılamadı. Lütfen tekrar deneyin.";
            }

        } catch (Exception $e) {
            // Hata durumunda hata günlüğü
            error_log("Rüya yorumlama sırasında bir hata oluştu: " . $e->getMessage());
            return "Rüya tabiri sırasında bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
        }
    }
}
