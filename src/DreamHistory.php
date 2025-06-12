<?php

// src/DreamHistory.php

class DreamHistory {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function saveDream($userId, $dream, $interpretation) {
        // SQL sorgusunu hazırla
        $query = "INSERT INTO dream_history (user_id, dream, interpretation, created_at) VALUES (?, ?, ?, ?)";
        
        // Statement'ı hazırla
        $stmt = $this->db->prepare($query);
        
        // Şu anki zamanı al
        $currentTime = date('Y-m-d H:i:s');
        
        // Parametreleri bağla
        $stmt->bind_param("ssss", $userId, $dream, $interpretation, $currentTime);
        
        // Sorguyu çalıştır
        return $stmt->execute();
    }

    public function getUserDreams($userId) {
        // SQL sorgusunu hazırla
        $query = "SELECT * FROM dream_history WHERE user_id = ? ORDER BY created_at DESC";
        
        // Statement'ı hazırla
        $stmt = $this->db->prepare($query);
        
        // userId parametresini bağla
        $stmt->bind_param("s", $userId);
        
        // Sorguyu çalıştır
        $stmt->execute();
        
        // Sonuçları al
        $result = $stmt->get_result();
        
        // Sonuçları diziye dönüştür
        $dreams = [];
        while ($row = $result->fetch_assoc()) {
            $dreams[] = $row;
        }
        
        return $dreams;
    }

    public function checkUserTokens($userId) {
        // Token kontrolü
        $sql = "SELECT token FROM tokens WHERE userId = ? ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
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

    public function decreaseUserToken($userId) {
        $sql = "UPDATE tokens SET token = token - 1, created_at = NOW() WHERE userId = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $userId);
        return $stmt->execute();
    }
}
