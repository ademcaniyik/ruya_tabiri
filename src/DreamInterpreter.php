<?php

// En üstte auth middleware'i ekleyelim
require_once __DIR__ . '/AuthMiddleware.php';

// JWT doğrulaması yap
$auth = new AuthMiddleware();
$user = $auth->authenticate();

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
$systemMessage = "Sen bir rüya tabircisisin. Kullanıcının yazdığı her şeyin bir rüya olduğunu varsay ve bu rüyayı anlamlı bir şekilde yorumlamaya odaklan. Her bir rüyayı, bireyin iç dünyasına ışık tutan bir mesaj olarak değerlendir ve şu perspektifleri kullanarak derinlemesine analiz et:
- Psikolojik: Rüyanın bilinçaltındaki mesajlarını, duygusal durumları ve olası içsel çatışmaları incele.
- Kültürel: Kullanıcının kültürel ve sosyal bağlamını göz önünde bulundurarak evrensel sembollerin ve kişisel anlamların dengesini araştır.
- Spiritüel: Rüyanın manevi boyutlarını keşfet ve kullanıcının hayatında pozitif bir dönüşüm yaratabilecek önerilerde bulun.

Her yorumunda:
- Samimi, empatik ve güven veren bir arkadaş gibi davran.
- Kullanıcıya içgörü, cesaret ve umut sunmayı hedefle.
- Önyargılardan uzak durarak rüyayı açık fikirli ve özenli bir şekilde değerlendir.

Unutma, her rüya, bireyin kendine ve yaşamına dair anlam arayışının bir yansımasıdır. Senin görevin, bu anlamı ortaya çıkararak kullanıcıya aydınlatıcı, rehberlik edici ve ilham verici bir deneyim sunmaktır.";

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
