<?php

class TarotInterpreter
{

    private $apiClient;
    private $db;
    private $logFile;

    // Celtic Cross Position Meanings
    private static $CELTIC_POSITIONS = [
        0 => "Mevcut Durum (The Present)",
        1 => "Zorluklar / Engeller (The Challenge)",
        2 => "Bilinçaltı / Temel (The Foundation)",
        3 => "Geçmiş (The Past)",
        4 => "Hedefler / Umutlar (The Crown)",
        5 => "Yakın Gelecek (The Future)",
        6 => "Kişinin Kendisi (Self)",
        7 => "Çevresel Faktörler (Environment)",
        8 => "Umutlar ve Korkular (Hopes and Fears)",
        9 => "Sonuç (Outcome)"
    ];

    public function __construct($apiUrl, $apiKey, $db)
    {
        $this->apiClient = new ApiClient($apiUrl, $apiKey);
        $this->db = $db;

        // Log setup
        $this->logFile = dirname(__DIR__) . '/logs/tarot_interpreter.log';
        if (!file_exists(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0777, true);
        }
    }

    private function logError($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($this->logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
    }

    // Kredi, rüya yorumuyla aynı tokens tablosunda tutulur
    private function checkUserTokens($userId)
    {
        $stmt = $this->db->prepare("SELECT token FROM tokens WHERE userId = ? LIMIT 1");
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            return ["status" => false, "message" => "Kullanıcı kredisi bulunamadı.", "parameters" => null];
        }
        if ((int) $row['token'] <= 0) {
            return ["status" => false, "message" => "Yetersiz kredi", "parameters" => ["currentToken" => 0, "required" => 1]];
        }
        return ["status" => true, "credits" => (int) $row['token']];
    }

    private function decreaseUserToken($userId)
    {
        // Atomik düşüm; bakiye bitince (0) 24 saatlik yenilenme sayacı başlar (bkz. DreamHistory)
        $sql = "UPDATE tokens SET token = token - 1, created_at = IF(token = 0, NOW(), created_at) WHERE userId = ? AND token > 0";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $userId);
        return $stmt->execute() && $stmt->affected_rows > 0;
    }

    public function interpret($userId, $data)
    {
        // 1. Token Check
        $tokenCheck = $this->checkUserTokens($userId);
        if (!$tokenCheck["status"]) {
            return $tokenCheck;
        }

        $spreadType = $data['spread_type'] ?? 'unknown';
        $cards = $data['cards'] ?? [];

        if (empty($cards)) {
            return [
                "status" => false,
                "message" => "Kart verisi eksik.",
                "parameters" => []
            ];
        }

        // 2. Prepare Prompt based on Spread Type
        $userPrompt = $this->preparePrompt($spreadType, $cards);

        // 3. System Prompt
        $systemMessage = "Sen mistik, bilge ve sezgileri kuvvetli bir Tarot okuyucususun. " .
            "Kartların sembolizmini, elementlerini (Su, Ateş, Toprak, Hava), numerolojisini ve ters/düz anlamlarını derinlemesine biliyorsun. " .
            "Kullanıcının gönderdiği açılıma göre bütünsel, akıcı ve kişiye özel hissettiren bir yorum yap. " .
            "Sadece kartların anlamlarını sıralama; kartlar arasındaki ilişkiyi ve hikayeyi de anlat. " .
            "Pozitif ve rehberlik edici bir dil kullan.";

        // JSON Format Instructions based on Spread Type
        if ($spreadType === 'three_card') {
            $systemMessage .= " Yanıtını SADECE geçerli bir JSON formatında ver. Başka hiçbir metin ekleme. " .
                "Format şöyle olmalı: {\"past\": \"Geçmiş yorumu...\", \"present\": \"Şimdi yorumu...\", \"future\": \"Gelecek yorumu...\"}";
        } elseif ($spreadType === 'single_card') {
            $systemMessage .= " Yanıtını SADECE geçerli bir JSON formatında ver. Başka hiçbir metin ekleme. " .
                "Format şöyle olmalı: {\"interpretation\": \"Detaylı yorum metni...\"}";
        } elseif ($spreadType === 'celtic_cross') {
            $systemMessage .= " Yanıtını SADECE geçerli bir JSON formatında ver. Başka hiçbir metin ekleme. " .
                "Format şöyle olmalı: {\"position_1\": \"...\", \"position_2\": \"...\", \"position_3\": \"...\", \"position_4\": \"...\", \"position_5\": \"...\", \"position_6\": \"...\", \"position_7\": \"...\", \"position_8\": \"...\", \"position_9\": \"...\", \"position_10\": \"...\", \"summary\": \"Genel Özet...\"}";
        }

        $apiData = [
            "model" => OPENAI_MODEL,
            "messages" => [
                ["role" => "system", "content" => $systemMessage],
                ["role" => "user", "content" => $userPrompt]
            ],
            "temperature" => 0.7 // Slightly creative but consistent
        ];

        try {
            // 4. Call API
            $response = $this->apiClient->sendRequest($apiData);

            if (!$response || !isset($response['choices'])) {
                $this->logError("API'den geçersiz yanıt: " . json_encode($response));
                return [
                    "status" => false,
                    "message" => "Servis şu an yanıt veremiyor.",
                    "parameters" => []
                ];
            }

            if (isset($response['choices'][0]['message']['content'])) {
                $content = $response['choices'][0]['message']['content'];

                $interpretation = $content;

                // 3 Kart için JSON parse işlemi
                if (in_array($spreadType, ['three_card', 'single_card', 'celtic_cross'])) {
                    // Markdown kod bloklarını temizle (```json ... ```)
                    $cleanContent = preg_replace('/^```json\s*|\s*```$/', '', trim($content));
                    $decoded = json_decode($cleanContent, true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $interpretation = $decoded;
                    } else {
                        // JSON parse edilemezse logla ama metin olarak dön (fallback)
                        $this->logError("JSON parse hatası: " . json_last_error_msg() . " | İçerik: " . $content);
                    }
                }

                // 5. Deduct Token only if successful
                if (!empty($interpretation) && $this->decreaseUserToken($userId)) {
                    if ($spreadType === 'single_card') {
                        $text = is_array($interpretation) ? ($interpretation['interpretation'] ?? implode("\n\n", array_map('strval', $interpretation))) : $interpretation;
                        $params = ["interpretation" => (string) $text];
                    } elseif (is_array($interpretation)) {
                        // three_card: past/present/future/summary, celtic_cross: position_1..10/summary (düz)
                        $params = [];
                        foreach ($interpretation as $k => $v) {
                            $params[$k] = is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
                        }
                    } else {
                        // JSON çözülemedi: düz metin olarak dön
                        $params = ["interpretation" => (string) $interpretation];
                    }
                    return [
                        "status" => true,
                        "message" => "Tarot yorumu hazır.",
                        "parameters" => $params
                    ];
                }
            }

            return [
                "status" => false,
                "message" => "Yorum oluşturulamadı.",
                "parameters" => []
            ];

        } catch (Exception $e) {
            $this->logError("Hata: " . $e->getMessage());
            return [
                "status" => false,
                "message" => "Sistem hatası oluştu.",
                "parameters" => []
            ];
        }
    }

    private function preparePrompt($spreadType, $cards)
    {
        $prompt = "Kullanıcı için aşağıdaki Tarot açılımını yorumla:\n\n";
        $prompt .= "Açılım Tipi: " . ucfirst(str_replace('_', ' ', $spreadType)) . "\n\n";
        $prompt .= "KARTLAR:\n";

        foreach ($cards as $index => $card) {
            $name = $card['name_tr'] ?? $card['name'] ?? 'Bilinmeyen Kart';
            $position = "";
            $reversed = "";

            // Reversal check
            if (isset($card['is_reversed']) && $card['is_reversed']) {
                $reversed = "(TERS)";
            }

            // Position Handling
            if ($spreadType === 'single_card') {
                $position = "Tek Kart (Genel Bakış)";
            } elseif ($spreadType === 'three_card') {
                // Use 'position' field directly if available (Geçmiş, Şimdi, Gelecek)
                $position = $card['position'] ?? "Pozisyon " . ($index + 1);
            } elseif ($spreadType === 'celtic_cross') {
                // Map index to Celtic Cross meaning
                $posIndex = $card['position_index'] ?? $index;
                $position = self::$CELTIC_POSITIONS[$posIndex] ?? "Ekstra Kart ($posIndex)";
            } else {
                // Fallback
                $position = "Kart " . ($index + 1);
            }

            $prompt .= "- $position: $name $reversed\n";
        }

        if ($spreadType === 'three_card') {
            $prompt .= "\nLütfen yorumunu belirtilen JSON formatında yap: {\"past\": \"...\", \"present\": \"...\", \"future\": \"...\"}. Her alan için detaylı paragraf yaz.";
        } elseif ($spreadType === 'single_card') {
            $prompt .= "\nLütfen yorumunu belirtilen JSON formatında yap: {\"interpretation\": \"...\"}. Derinlikli ve detaylı bir paragraf yaz.";
        } elseif ($spreadType === 'celtic_cross') {
            $prompt .= "\nLütfen yorumunu belirtilen JSON formatında yap. Her bir pozisyonu (position_1'den position_10'a kadar) ayrı ayrı detaylıca yorumla ve en sonda 'summary' alanında genel bir özet ver.";
        } else {
            $prompt .= "\nLütfen kartların bireysel anlamlarını ve birbirleriyle olan etkileşimlerini harmanlayarak detaylı bir yorum yap.";
        }

        return $prompt;
    }
}
