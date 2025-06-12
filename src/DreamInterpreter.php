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

    private function checkUserTokens($userId) {
        // Token kontrolü
        $sql = "SELECT token FROM tokens WHERE userId = ? ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->dreamHistory->db->prepare($sql);
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $tokenCount = (int)$row['token'];
            
            if ($tokenCount <= 0) {
                return [
                    "status" => false,
                    "message" => "Yetersiz rüya yorumlama hakkı",
                    "parameters" => [
                        "currentToken" => $tokenCount,
                        "required" => 1
                    ]
                ];
            }
            return ["status" => true, "tokenCount" => $tokenCount];
        }
        
        return [
            "status" => false,
            "message" => "Kullanıcı token bilgisi bulunamadı",
            "parameters" => null
        ];
    }

    private function decreaseUserToken($userId) {
        $sql = "UPDATE tokens SET token = token - 1, created_at = NOW() WHERE userId = ?";
        $stmt = $this->dreamHistory->db->prepare($sql);
        $stmt->bind_param("s", $userId);
        return $stmt->execute();
    }

    public function interpretDream($userId, $dreamDescription, $language = 'tr') {
        // Token kontrolü yap
        $tokenCheck = $this->checkUserTokens($userId);
        if (!$tokenCheck["status"]) {
            return $tokenCheck;
        }

        $systemMessage = "Sen bir rüya tabircisisin. Kullanıcının yazdığı her şeyin bir rüya olduğunu varsay ve bu rüyayı anlamlı bir şekilde yorumlamaya odaklan. Her bir rüyayı, bireyin iç dünyasına ışık tutan bir mesaj olarak değerlendir ve şu perspektifleri kullanarak derinlemesine analiz et:
- Psikolojik: Rüyanın bilinçaltındaki mesajlarını, duygusal durumları ve olası içsel çatışmaları incele.
- Kültürel: Kullanıcının kültürel ve sosyal bağlamını göz önünde bulundurarak evrensel sembollerin ve kişisel anlamların dengesini araştır.";

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
                
                // Yorumlama başarılı olduysa token azalt
                if (!empty($interpretation) && $this->decreaseUserToken($userId)) {
                    $this->dreamHistory->saveDream($userId, $dreamDescription, $interpretation);
                    return [
                        "status" => true,
                        "message" => "Rüya başarı ile yorumlandı.",
                        "parameters" => [
                            "interpretation" => $interpretation,
                            "remainingTokens" => $tokenCheck["tokenCount"] - 1
                        ]
                    ];
                }
            }

            $this->logError("Yanıtta 'choices[0][message][content]' eksik: " . json_encode($response));
            return [
                "status" => false,
                "message" => "API yanıtı eksik veya beklenen formatta değil.",
                "parameters" => []
            ];

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
