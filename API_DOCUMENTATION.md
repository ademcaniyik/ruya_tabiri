# Rüya Tabiri API Dokümantasyonu

## Genel Bilgiler

### Genel Response Formatı
Tüm endpointler aşağıdaki formatta yanıt döner:
```json
{
    "status": true|false,
    "message": "İşlem açıklaması",
    "parameters": {
        // endpoint'e özel veriler
    }
}
```

## Endpointler

### 1. Kullanıcı Girişi/Kaydı
**Endpoint:** `/src/UserLogin.php`  
**Metod:** POST

**Request Body:**
```json
{
    "userId": "unique_user_id",
    "name": "Kullanıcı Adı",      // Yeni kayıt için zorunlu
    "email": "email@example.com",  // Yeni kayıt için zorunlu
    "device_token": "fcm_device_token" // Opsiyonel
}
```

**Başarılı Response:**
```json
{
    "status": true,
    "message": "Giriş başarılı.",
    "parameters": null
}
```

### 2. Rüya Yorumlama Hakkı Sorgulama
**Endpoint:** `/src/Token.php`  
**Metod:** POST

**Request Body:**
```json
{
    "userId": "user_id"
}
```

**Başarılı Response:**
```json
{
    "status": true,
    "message": "Token sorgulandı.",
    "parameters": {
        "token": 5,  // Kalan rüya yorumlama hakkı
        "created_at": "2025-06-12 10:00:00"
    }
}
```

### 3. Rüya Yorumlama Hakkı Güncelleme
**Endpoint:** `/src/TokenUpdate.php`  
**Metod:** POST

**Request Body:**
```json
{
    "userId": "user_id",
    "tokenChange": 1  // Eklenecek veya çıkarılacak hak sayısı (pozitif veya negatif)
}
```

**Başarılı Response:**
```json
{
    "status": true,
    "message": "Token başarıyla güncellendi.",
    "parameters": {
        "currentToken": 5,  // Eski hak sayısı
        "newToken": 6,     // Yeni hak sayısı
        "newCreatedAt": "2025-06-12 10:00:00"
    }
}
```

### 4. Rüya Yorumlama
**Endpoint:** `/src/DreamInterpreter.php`  
**Metod:** POST

**Request Body:**
```json
{
    "userId": "user_id",
    "dreamDescription": "Rüya açıklaması",
    "language": "tr"  // Opsiyonel, varsayılan: "tr"
}
```

**Başarılı Response:**
```json
{
    "status": true,
    "message": "Rüya başarı ile yorumlandı.",
    "parameters": {
        "interpretation": "Rüya yorumu..."
    }
}
```

### 5. Rüya Geçmişi
**Endpoint:** `/src/dream_history.php`  
**Metod:** POST

**Request Body:**
```json
{
    "user_id": "user_id"
}
```

**Başarılı Response:**
```json
{
    "status": true,
    "message": "Kullanıcının rüya geçmişi başarıyla alındı.",
    "parameters": [
        {
            "id": "1",
            "user_id": "user_id",
            "dream": "Rüya açıklaması",
            "interpretation": "Rüya yorumu",
            "created_at": "2025-06-12 10:00:00"
        }
    ]
}
```

### 6. Kullanıcı Bilgileri
**Endpoint:** `/src/UserInfo.php`  
**Metod:** POST

**Request Body:**
```json
{
    "userId": "user_id"
}
```

**Başarılı Response:**
```json
{
    "status": true,
    "message": "Kullanıcı bilgileri başarıyla alındı.",
    "parameters": {
        "userId": "user_id",
        "name": "Kullanıcı Adı",
        "email": "email@example.com",
        "created_at": "2025-06-12 10:00:00"
    }
}
```

## Hata Durumları

### 1. Yetersiz Rüya Yorumlama Hakkı
```json
{
    "status": false,
    "message": "Yetersiz rüya yorumlama hakkı",
    "parameters": {
        "currentToken": 0,
        "required": 1
    }
}
```

### 2. Eksik veya Geçersiz Parametreler
```json
{
    "status": false,
    "message": "Gerekli parametre eksik veya geçersiz",
    "parameters": null
}
```
