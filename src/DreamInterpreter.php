<?php

class DreamInterpreter {

    private $apiClient;
    private $dreamHistory;
    private $logFile;

    public function __construct($apiUrl, $apiKey, $dreamHistory) {
        $this->apiClient = new ApiClient($apiUrl, $apiKey);
        $this->dreamHistory = $dreamHistory;

        // Log dosyasını üst dizindeki logs klasörüne ayarla
        $this->logFile = dirname(__DIR__) . '/logs/dream_interpreter.log';

        // Eğer logs klasörü yoksa oluştur
        if (!file_exists(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0777, true);
        }
    }

    private function logError($message) {
        // Zaman damgası ekleyerek log yaz
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($this->logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
    }

    public function interpretDream($userId, $dreamDescription, $language = 'tr') {
        $systemMessage = "Sen bir rüya tabircisisin. Kullanıcının yazdığı her şeyin bir rüya tanımı olduğunu varsay ve bu rüyayı anlamlı, derinlemesine, kültürel ve psikolojik perspektiflerden yorumla. Rüyayı samimi bir arkadaş gibi, empatiyle ve pozitif bir dille değerlendir. Kullanıcıya yol gösterici, aydınlatıcı ve ilham verici bir yorum yap.";

        $data = [
            "model" => "gpt-3.5-turbo",
            "messages" => [
                ["role" => "system", "content" => $systemMessage],
                ["role" => "user", "content" => $dreamDescription]
            ],
            "temperature" => 1
        ];

        try {
            $response = $this->apiClient->sendRequest($data);

            if (!$response || !isset($response['choices'])) {
                $this->logError("API'den geçersiz bir yanıt alındı: " . json_encode($response));
                return [
                    "status" => false,
                    "message" => "API hizmeti beklenmeyen bir yanıt döndürdü.",
                    "parameters" => []
                ];
            }

            if (isset($response['choices'][0]['message']['content'])) {
                $interpretation = $response['choices'][0]['message']['content'];
            } else {
                $this->logError("Yanıtta 'choices[0][message][content]' eksik: " . json_encode($response));
                return [
                    "status" => false,
                    "message" => "API yanıtı eksik veya beklenen formatta değil.",
                    "parameters" => []
                ];
            }

            if (!empty($interpretation)) {
                $this->dreamHistory->saveDream($userId, $dreamDescription, $interpretation);
                return [
                    "status" => true,
                    "message" => "Rüya başarı ile yorumlandı.",
                    "parameters" => [
                        "interpretation" => $interpretation
                    ]
                ];
            } else {
                $this->logError("Boş bir yorum döndürüldü: " . json_encode($response));
                return [
                    "status" => false,
                    "message" => "Rüya tabiri yapılamadı.",
                    "parameters" => []
                ];
            }
        } catch (Exception $e) {
            $this->logError("Rüya yorumlama sırasında bir hata oluştu: " . $e->getMessage());
            return [
                "status" => false,
                "message" => "Bir hata oluştu: " . $e->getMessage(),
                "parameters" => []
            ];
        }
    }
}
