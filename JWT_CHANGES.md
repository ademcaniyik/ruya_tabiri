# JWT Entegrasyonu Değişiklik Dökümanı

## 1. Eklenen Yeni Dosyalar

### `src/JWTAuth.php`
- JWT token oluşturma ve doğrulama işlemlerini yöneten sınıf
- Token geçerlilik süresi: 24 saat
- Kullanılan algoritma: HS256
- Secret key: "ruya_tabiri_secret_key_2025" (Production'da env dosyasından alınmalı)

### `src/AuthMiddleware.php`
- API güvenliği için middleware sınıfı
- Token kontrolü ve doğrulaması
- Geçersiz token durumunda 401 hatası döndürme

## 2. Değiştirilen Dosyalar

### `src/UserLogin.php`
- JWT entegrasyonu eklendi
- Login/kayıt başarılı olduğunda JWT token üretiliyor
- Response yapısına token bilgisi eklendi

### `src/DreamInterpreter.php`
- AuthMiddleware entegrasyonu eklendi
- Her istek öncesi token kontrolü yapılıyor

## 3. Token Kullanımı

### Client Tarafı İstek Örneği
```javascript
fetch('api/interpret-dream', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer ' + jwtToken,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        dream: 'Rüya içeriği...'
    })
})
```

## 4. Değişiklikleri Geri Alma

Eğer JWT entegrasyonunu geri almak isterseniz:

1. Şu dosyaları silin:
   - `src/JWTAuth.php`
   - `src/AuthMiddleware.php`

2. UserLogin.php dosyasındaki JWT kodlarını kaldırın:
   - JWT token oluşturma kodu bloğunu kaldırın
   - Response yapısını eski haline getirin

3. DreamInterpreter.php dosyasındaki değişiklikleri kaldırın:
   - AuthMiddleware require satırını kaldırın
   - Token kontrolü kodlarını kaldırın

## 5. Güvenlik Notları

- Secret key production ortamında env dosyasından alınmalı
- HTTPS kullanılması önerilir
- Token süreleri ihtiyaca göre ayarlanabilir
- Token blacklist sistemi eklenebilir
- Refresh token mekanizması ihtiyaca göre eklenebilir

## 6. Gerekli Composer Paketi
```json
{
    "require": {
        "firebase/php-jwt": "^6.0"
    }
}
```

# Postman Test Talimatları

## 1. Kullanıcı Kaydı/Girişi (Login/Register)

### Endpoint: `/src/UserLogin.php`
- Method: POST
- Headers:
  ```
  Content-Type: application/json
  ```
- Body:
  ```json
  {
    "userId": "test_user",
    "name": "Test User",
    "email": "test@example.com",
    "device_token": "test_device_token"
  }
  ```
- Response (Başarılı):
  ```json
  {
    "status": true,
    "message": "Kullanıcı başarıyla kaydedildi.",
    "parameters": {
      "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
    }
  }
  ```

## 2. Rüya Yorumlama İsteği

### Endpoint: `/src/DreamInterpreter.php`
- Method: POST
- Headers:
  ```
  Content-Type: application/json
  Authorization: Bearer <login'den_alınan_token>
  ```
- Body:
  ```json
  {
    "userId": "test_user",
    "dreamDescription": "Rüya açıklaması buraya yazılacak",
    "language": "tr"
  }
  ```
- Response (Başarılı):
  ```json
  {
    "status": true,
    "message": "Rüya başarı ile yorumlandı.",
    "parameters": {
      "interpretation": "Rüya yorumu buraya gelecek..."
    }
  }
  ```

## Hata Durumları

### 1. Token Eksik
```json
{
  "status": false,
  "message": "Token bulunamadı",
  "parameters": null
}
```

### 2. Geçersiz Token
```json
{
  "status": false,
  "message": "Geçersiz token",
  "parameters": null
}
```

## Test Adımları

1. İlk önce UserLogin endpoint'ine istek atın ve dönen token'ı kaydedin
2. Bu token'ı DreamInterpreter isteklerinde Authorization header'ında kullanın
3. Token'ın geçerlilik süresi 24 saattir
4. Token olmadan veya geçersiz token ile yapılan istekler 401 hatası döndürür

## Postman Collection

Aşağıdaki collection'ı Postman'e import edebilirsiniz:

```json
{
  "info": {
    "name": "Rüya Tabiri API",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "User Login",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          }
        ],
        "url": "{{base_url}}/src/UserLogin.php",
        "body": {
          "mode": "raw",
          "raw": "{\n    \"userId\": \"test_user\",\n    \"name\": \"Test User\",\n    \"email\": \"test@example.com\",\n    \"device_token\": \"test_device_token\"\n}"
        }
      }
    },
    {
      "name": "Interpret Dream",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          },
          {
            "key": "Authorization",
            "value": "Bearer {{jwt_token}}"
          }
        ],
        "url": "{{base_url}}/src/DreamInterpreter.php",
        "body": {
          "mode": "raw",
          "raw": "{\n    \"userId\": \"test_user\",\n    \"dreamDescription\": \"Rüya açıklaması\",\n    \"language\": \"tr\"\n}"
        }
      }
    }
  ],
  "variable": [
    {
      "key": "base_url",
      "value": "http://localhost"
    },
    {
      "key": "jwt_token",
      "value": "buraya_login_sonrası_alınan_token_gelecek"
    }
  ]
}
```

## Environment Variables

Postman'de aşağıdaki environment variables'ları oluşturun:

- `base_url`: API'nizin base URL'i (örn: http://localhost)
- `jwt_token`: Login sonrası alınan token
