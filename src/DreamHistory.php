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
}
