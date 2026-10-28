<?php
header('Content-Type: application/json; charset=utf-8');

// ==== LOG DOSYASI (src/ dizininde) ===
$logFile = __DIR__ . '/iap_debug.log';

function writeLog($step, $message, $data = null)
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $logEntry = "[$timestamp] [IP: $ip] [ADIM: $step] $message";
    if ($data !== null) {
        $logEntry .= "\n   DATA: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    $logEntry .= "\n" . str_repeat('-', 80) . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

writeLog(1, "=== YENİ SATIN ALMA DOĞRULAMA İSTEĞİ BAŞLADI ===");

// Gerekli dosyalar
writeLog(2, "vendor/autoload.php yükleniyor...");
try {
    require_once __DIR__ . '/../vendor/autoload.php';
    writeLog(2, "vendor/autoload.php başarıyla yüklendi");
} catch (Exception $e) {
    writeLog(2, "HATA: autoload yüklenemedi", ['error' => $e->getMessage()]);
    echo json_encode(['status' => false, 'message' => 'Autoload yüklenemedi']);
    exit;
}

writeLog(3, "config/config.php yükleniyor...");
require_once __DIR__ . '/../config/config.php';
writeLog(3, "config.php başarıyla yüklendi");

// Google Client Kütüphanesi
use Google\Client;
use Google\Service\AndroidPublisher;

// POST Verisini Al
writeLog(4, "POST verisi okunuyor...");
$rawInput = file_get_contents("php://input");
writeLog(4, "Ham POST verisi", ['raw' => $rawInput]);

$input = json_decode($rawInput, true);
writeLog(4, "Parse edilmiş veri", $input);

if (!$input) {
    writeLog(4, "HATA: Geçersiz JSON verisi");
    echo json_encode(['status' => false, 'message' => 'Geçersiz veri']);
    exit;
}

// Gerekli Parametreler
$userId = $conn->real_escape_string($input['userId'] ?? '');
// iOS'ta receipt olarak gelebilir, Android'de purchaseToken
$purchaseToken = $input['purchaseToken'] ?? ($input['receipt'] ?? '');
$productId = $conn->real_escape_string($input['productId'] ?? '');
$orderId = $conn->real_escape_string($input['orderId'] ?? '');
$packageName = $input['packageName'] ?? 'com.acd1f4ftware.ruyatabiri';

writeLog(5, "Parametreler ayıklandı", [
    'userId' => $userId,
    'productId' => $productId,
    'orderId' => $orderId,
    'packageName' => $packageName,
    'purchaseToken' => substr($purchaseToken, 0, 50) . '...' // Token'ın sadece başını logla
]);

// Platform'a göre zorunlu parametreler değişir
$platform = strtolower($input['platform'] ?? '');
if (empty($platform)) {
    // Platform belirtilmemişse, receipt varsa iOS, yoksa Android varsay
    $platform = !empty($input['receipt']) || (!empty($input['purchaseToken']) && count(explode('.', $input['purchaseToken'])) === 3) ? 'ios' : 'android';
}

if ($platform === 'ios') {
    // iOS için orderId zorunlu değil, transactionId kullanılır
    if (empty($userId) || empty($purchaseToken) || empty($productId)) {
        writeLog(5, "HATA: iOS için eksik parametreler", [
            'userId_empty' => empty($userId),
            'purchaseToken_empty' => empty($purchaseToken),
            'productId_empty' => empty($productId)
        ]);
        echo json_encode(['status' => false, 'message' => 'iOS için eksik parametreler']);
        exit;
    }
} else {
    // Android için orderId zorunlu
    if (empty($userId) || empty($purchaseToken) || empty($productId) || empty($orderId)) {
        writeLog(5, "HATA: Android için eksik parametreler", [
            'userId_empty' => empty($userId),
            'purchaseToken_empty' => empty($purchaseToken),
            'productId_empty' => empty($productId),
            'orderId_empty' => empty($orderId)
        ]);
        echo json_encode(['status' => false, 'message' => 'Android için eksik parametreler']);
        exit;
    }
}
writeLog(6, "Platform algılandı: $platform");

$isValid = false;
$purchaseData = null;

if ($platform === 'ios') {
    // ==== IOS DOĞRULAMA ====
    writeLog(7, "iOS doğrulaması başlıyor...");

    // iOS Shared Secret (Abonelikler için gerekli)
    // Eğer config.php'de tanımlı değilse buraya string olarak girebilirsiniz.
    $sharedSecret = defined('IOS_SHARED_SECRET') ? IOS_SHARED_SECRET : 'YOUR_IOS_SHARED_SECRET_HERE';

    // Receipt Data: purchaseToken parametresinden veya receipt parametresinden gelebilir
    $receiptData = $purchaseToken;
    if (empty($receiptData) && isset($input['receipt'])) {
        $receiptData = $input['receipt'];
    }

    if (empty($receiptData)) {
        writeLog(7, "HATA: iOS receipt data eksik");
        echo json_encode(['status' => false, 'message' => 'Receipt data eksik']);
        exit;
    }

    $requestData = ['receipt-data' => $receiptData];
    if (!empty($sharedSecret) && $sharedSecret !== 'YOUR_IOS_SHARED_SECRET_HERE') {
        $requestData['password'] = $sharedSecret;
    }

    // base64url decode yardımcı fonksiyonu
    function b64urlDecode($data) {
        $pad = strlen($data) % 4;
        if ($pad) $data .= str_repeat('=', 4 - $pad);
        return base64_decode(strtr($data, '-_', '+/'));
    }

    // 0. JWS (StoreKit 2) Kontrolü
    $isJws = count(explode('.', $receiptData)) === 3;

    if ($isJws) {
        writeLog(7, "StoreKit 2 JWS formatı algılandı, Apple imza doğrulaması yapılıyor...");
        try {
            $parts = explode('.', $receiptData);
            $header = json_decode(b64urlDecode($parts[0]), true);

            if (!isset($header['x5c']) || !is_array($header['x5c']) || count($header['x5c']) < 2) {
                writeLog(7, "HATA: JWS header'ında x5c sertifika zinciri eksik");
                $isValid = false;
            } else {
                // Apple Root CA - G3 (Apple'ın halka açık sertifikası, değişmez)
                // 780 karakter, geçerli base64, x5c[2] değeriyle doğrulandı
                $appleRootCA = "-----BEGIN CERTIFICATE-----\n" .
                    chunk_split(
                        "MIICQzCCAcmgAwIBAgIILcX8iNLFS5UwCgYIKoZIzj0EAwMwZzEbMBkGA1UEAwwS" .
                        "QXBwbGUgUm9vdCBDQSAtIEczMSYwJAYDVQQLDB1BcHBsZSBDZXJ0aWZpY2F0aW9u" .
                        "IEF1dGhvcml0eTETMBEGA1UECgwKQXBwbGUgSW5jLjELMAkGA1UEBhMCVVMwHhcN" .
                        "MTQwNDMwMTgxOTA2WhcNMzkwNDMwMTgxOTA2WjBnMRswGQYDVQQDDBJBcHBsZSBS" .
                        "b290IENBIC0gRzMxJjAkBgNVBAsMHUFwcGxlIENlcnRpZmljYXRpb24gQXV0aG9y" .
                        "aXR5MRMwEQYDVQQKDApBcHBsZSBJbmMuMQswCQYDVQQGEwJVUzB2MBAGByqGSM49" .
                        "AgEGBSuBBAAiA2IABJjpLz1AcqTtkyJygRMc3RCV8cWjTnHcFBbZDuWmBSp3ZHtf" .
                        "TjjTuxxEtX/1H7YyYl3J6YRbTzBPEVoA/VhYDKX1DyxNB0cTddqXl5dvMVztK517" .
                        "IDvYuVTZXpmkOlEKMaNCMEAwHQYDVR0OBBYEFLuw3qFYM4iapIqZ3r6966/ayySr" .
                        "MA8GA1UdEwEB/wQFMAMBAf8wDgYDVR0PAQH/BAQDAgEGMAoGCCqGSM49BAMDA2gA" .
                        "MGUCMQCD6cHEFl4aXTQY2e3v9GwOAEZLuN+yRhHFD/3meoyhpmvOwgPUnPWTxnS4a" .
                        "t+qIxUCMG1mihDK1A3UT82NQz60imOlM27jbdoXt2QfyFMm+YhidDkLF1vLUagM6" .
                        "BgD56KyKA==",
                        64, "\n"
                    ) . "-----END CERTIFICATE-----\n";

                // x5c sertifikalarını PEM'e çevir (x5c[0]=leaf, x5c[1]=intermediate, x5c[2]=root)
                $pemCerts = [];
                foreach ($header['x5c'] as $certB64) {
                    $pemCerts[] = "-----BEGIN CERTIFICATE-----\n" .
                                  chunk_split($certB64, 64, "\n") .
                                  "-----END CERTIFICATE-----\n";
                }

                $leafCert         = openssl_x509_read($pemCerts[0]);
                $intermediateCert = openssl_x509_read($pemCerts[1]);

                // Sertifika zincirini doğrula: leaf→intermediate→Apple Root CA
                // openssl_pkey_get_public'a PEM string geç (cert resource değil - daha uyumlu)
                $intermPubKey = openssl_pkey_get_public($pemCerts[1]);
                $rootPubKey   = openssl_pkey_get_public($appleRootCA);

                writeLog(8, "Sertifika okuma durumu", [
                    'leaf_cert'     => $leafCert     ? 'OK' : 'FAIL',
                    'interm_cert'   => $intermediateCert ? 'OK' : 'FAIL',
                    'interm_pubkey' => $intermPubKey ? 'OK' : 'FAIL',
                    'root_pubkey'   => $rootPubKey   ? 'OK' : 'FAIL',
                    'openssl_error' => openssl_error_string() ?: 'yok',
                ]);

                $chainValid = false;
                if ($leafCert && $intermediateCert && $intermPubKey && $rootPubKey) {
                    $leafOk         = openssl_x509_verify($leafCert, $intermPubKey);
                    $intermediateOk = openssl_x509_verify($intermediateCert, $rootPubKey);
                    writeLog(8, "Zincir doğrulama sonuçları", [
                        'leaf_ok'         => $leafOk,
                        'intermediate_ok' => $intermediateOk,
                        'openssl_error'   => openssl_error_string() ?: 'yok',
                    ]);
                    $chainValid = ($leafOk === 1 && $intermediateOk === 1);
                }

                if (!$chainValid) {
                    writeLog(8, "HATA: Sertifika zinciri Apple Root CA ile doğrulanamadı");
                    $isValid = false;
                } else {
                    // ES256 imzasını doğrula
                    $publicKey    = openssl_pkey_get_public($leafCert);
                    $sigRaw       = b64urlDecode($parts[2]);

                    // IEEE P1363 (r||s, 64 byte) → ASN.1 DER dönüşümü
                    $r = ltrim(substr($sigRaw, 0, 32), "\x00") ?: "\x00";
                    $s = ltrim(substr($sigRaw, 32, 32), "\x00") ?: "\x00";
                    if (ord($r[0]) > 0x7f) $r = "\x00" . $r;
                    if (ord($s[0]) > 0x7f) $s = "\x00" . $s;
                    $derSig = "\x30" . chr(4 + strlen($r) + strlen($s)) .
                              "\x02" . chr(strlen($r)) . $r .
                              "\x02" . chr(strlen($s)) . $s;

                    $verified = openssl_verify($parts[0] . '.' . $parts[1], $derSig, $publicKey, OPENSSL_ALGO_SHA256);

                    if ($verified === 1) {
                        $payload = json_decode(b64urlDecode($parts[1]), true);
                        $isValid = true;
                        $purchaseData = (object) [
                            'startTimeMillis' => $payload['purchaseDate'] ?? (time() * 1000),
                            'expiryTimeMillis' => $payload['expiresDate'] ?? null,
                            'purchaseState' => 0,
                            'orderId' => $payload['transactionId'] ?? $orderId
                        ];
                        if (empty($orderId) && isset($payload['transactionId'])) {
                            $orderId = $conn->real_escape_string($payload['transactionId']);
                        }
                        writeLog(8, "BAŞARILI: JWS imzası doğrulandı", [
                            'transactionId' => $payload['transactionId'] ?? 'yok',
                            'environment'   => $payload['environment'] ?? 'bilinmiyor',
                            'type'          => $payload['type'] ?? 'bilinmiyor'
                        ]);
                    } else {
                        writeLog(8, "HATA: JWS imzası geçersiz", ['openssl_error' => openssl_error_string()]);
                        $isValid = false;
                    }
                }
            }
        } catch (Exception $e) {
            writeLog(8, "HATA: JWS Doğrulama Hatası: " . $e->getMessage());
            $isValid = false;
        }
    }

    // Eğer JWS değilse veya JWS başarısızsa Klasik verifyReceipt dene
    if (!$isValid && !$isJws) {
        // Fonksiyon: Apple API'ye istek at
        function validateReceiptCurl($url, $data)
        {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $response = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            if ($error) {
                return ['error' => $error];
            }
            return json_decode($response, true);
        }

        $requestData = ['receipt-data' => $receiptData];
        if (!empty($sharedSecret) && $sharedSecret !== 'YOUR_IOS_SHARED_SECRET_HERE') {
            $requestData['password'] = $sharedSecret;
        }

        // 1. Önce Production dene
        writeLog(8, "Apple Production API deneniyor...");
        $response = validateReceiptCurl('https://buy.itunes.apple.com/verifyReceipt', $requestData);

        // Sandbox ortamındaysa 21007 döner, Sandbox'a git
        if (isset($response['status']) && $response['status'] == 21007) {
            writeLog(8, "Status 21007 (Sandbox receipt), Sandbox API deneniyor...");
            $response = validateReceiptCurl('https://sandbox.itunes.apple.com/verifyReceipt', $requestData);
        }

        writeLog(9, "Apple Yanıtı", ['status' => $response['status'] ?? 'yok']);

        if (isset($response['status']) && $response['status'] == 0) {
            $isValid = true;
            // En son makbuzu al (latest_receipt_info varsa array, yoksa receipt içi)
            if (isset($response['latest_receipt_info']) && is_array($response['latest_receipt_info']) && count($response['latest_receipt_info']) > 0) {
                // Genellikle sonuncusu en günceldir ama garanti olsun diye sıralayabiliriz veya sonuncuyu alırız.
                // Apple sonuncuyu en güncel olarak döndürür defaultta (ancak exclude-old-transactions'a bağlı).
                // Basitlik için son elemanı alalım.
                $latest = end($response['latest_receipt_info']);
                // productId kontrolü
                if ($latest['product_id'] === $productId) {
                    $purchaseData = (object) [
                        'startTimeMillis' => $latest['purchase_date_ms'],
                        'expiryTimeMillis' => $latest['expires_date_ms'] ?? null,
                        'purchaseState' => 0, // Geçerli
                        'transactionId' => $latest['transaction_id'] ?? null
                    ];
                    // orderId'yi transactionId ile doldur
                    if (empty($orderId) && isset($latest['transaction_id'])) {
                        $orderId = $latest['transaction_id'];
                    }
                } else {
                    // Ürün eşleşmedi, döngüyle ara
                    foreach (array_reverse($response['latest_receipt_info']) as $item) {
                        if ($item['product_id'] === $productId) {
                            $purchaseData = (object) [
                                'startTimeMillis' => $item['purchase_date_ms'],
                                'expiryTimeMillis' => $item['expires_date_ms'] ?? null,
                                'purchaseState' => 0,
                                'transactionId' => $item['transaction_id'] ?? null
                            ];
                            // orderId'yi transactionId ile doldur
                            if (empty($orderId) && isset($item['transaction_id'])) {
                                $orderId = $item['transaction_id'];
                            }
                            break;
                        }
                    }
                }
            } elseif (isset($response['receipt'])) {
                // Tek seferlik alım veya basit yapı
                $r = $response['receipt'];
                $purchaseData = (object) [
                    'startTimeMillis' => $r['purchase_date_ms'] ?? (time() * 1000), // Eğer yoksa şu an
                    'expiryTimeMillis' => $r['expires_date_ms'] ?? null,
                    'purchaseState' => 0,
                    'transactionId' => $r['transaction_id'] ?? null
                ];
                // orderId'yi transactionId ile doldur
                if (empty($orderId) && isset($r['transaction_id'])) {
                    $orderId = $r['transaction_id'];
                }
            } else {
                $isValid = false;
                writeLog(9, "HATA: Geçerli makbuz verisi ayrıştırılamadı");
            }

            if ($purchaseData) {
                writeLog(9, "BAŞARILI: iOS doğrulandı", ['expiry' => $purchaseData->expiryTimeMillis ?? 'yok']);
            }

        } else {
            writeLog(9, "HATA: iOS doğrulama başarısız", ['status' => $response['status'] ?? 'bilinmiyor']);
            echo json_encode(['status' => false, 'message' => 'iOS doğrulama başarısız. Kod: ' . ($response['status'] ?? 'bilinmiyor')]);
            exit;
        }
    }

    if (!$isValid) {
        writeLog(9, "HATA: iOS doğrulama tüm yöntemlerle başarısız oldu.");
        echo json_encode(['status' => false, 'message' => 'iOS doğrulama başarısız']);
        exit;
    }

} else {
    // ==== ANDROID DOĞRULAMA (Mevcut Kod) ====
    // 1. Google API Client Kurulumu
    writeLog(6, "Google API Client oluşturuluyor...");
    $client = new Client();
    writeLog(6, "Google Client oluşturuldu");

    $authConfigPath = __DIR__ . '/../config/service_account.json';
    writeLog(6, "Service account yolu", ['path' => $authConfigPath, 'exists' => file_exists($authConfigPath)]);

    if (!file_exists($authConfigPath)) {
        writeLog(6, "HATA: Service account dosyası bulunamadı!");
        echo json_encode(['status' => false, 'message' => 'Service account dosyası bulunamadı!']);
        exit;
    }

    writeLog(7, "Google Client yapılandırılıyor...");
    try {
        $client->setAuthConfig($authConfigPath);
        writeLog(7, "Auth config yüklendi");
        $client->addScope(AndroidPublisher::ANDROIDPUBLISHER);
        writeLog(7, "Scope eklendi: ANDROIDPUBLISHER");
        $service = new AndroidPublisher($client);
        writeLog(7, "AndroidPublisher service oluşturuldu");
    } catch (Exception $e) {
        writeLog(7, "HATA: Google Client yapılandırma hatası", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        echo json_encode(['status' => false, 'message' => 'Google API bağlantı hatası: ' . $e->getMessage()]);
        exit;
    }

    // 2. Satın Almayı Doğrula
    writeLog(8, "Google Play API'ye satın alma doğrulama isteği gönderiliyor...");

    try {
        // Önce Subscription olarak dene
        writeLog(8, "Abonelik olarak sorgulanıyor...", [
            'packageName' => $packageName,
            'productId' => $productId,
            'purchaseToken' => substr($purchaseToken, 0, 30) . '...'
        ]);
        try {
            $purchase = $service->purchases_subscriptions->get($packageName, $productId, $purchaseToken);
            writeLog(8, "Abonelik sorgu yanıtı alındı", ['response' => json_encode($purchase)]);

            if (isset($purchase->paymentState) && ($purchase->paymentState === 1 || $purchase->paymentState === 2)) {
                $isValid = true;
                $purchaseData = $purchase;
                writeLog(8, "BAŞARILI: Abonelik doğrulandı", ['paymentState' => $purchase->paymentState]);
            } else {
                writeLog(8, "Abonelik durumu geçersiz", ['paymentState' => $purchase->paymentState ?? 'yok']);
            }

        } catch (Exception $eSub) {
            writeLog(9, "Abonelik sorgusu başarısız, ürün olarak deneniyor...", ['error' => $eSub->getMessage()]);
            try {
                $purchase = $service->purchases_products->get($packageName, $productId, $purchaseToken);
                writeLog(9, "Ürün sorgu yanıtı alındı", ['response' => json_encode($purchase)]);

                if ($purchase->purchaseState === 0) {
                    $isValid = true;
                    $purchaseData = $purchase;
                    writeLog(9, "BAŞARILI: Ürün doğrulandı", ['purchaseState' => $purchase->purchaseState]);
                } else {
                    writeLog(9, "Ürün durumu geçersiz", ['purchaseState' => $purchase->purchaseState]);
                }
            } catch (Exception $eProd) {
                writeLog(9, "HATA: Ürün sorgusu da başarısız", ['error' => $eProd->getMessage(), 'trace' => $eProd->getTraceAsString()]);
                throw new Exception("Google API üzerinden satın alma doğrulanamadı: " . $eProd->getMessage());
            }
        }

    } catch (Exception $e) {
        writeLog(10, "HATA: Doğrulama Exception", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        echo json_encode(['status' => false, 'message' => 'Doğrulama başarısız: ' . $e->getMessage()]);
        exit;
    }
}

// 3. Veritabanına İşle
writeLog(11, "Veritabanı işlemleri başlıyor...", ['isValid' => $isValid, 'platform' => $platform, 'orderId' => $orderId]);
if ($isValid) {
    // iOS için orderId boş olabilir, transactionId kullan
    $transactionIdForDb = $orderId;
    if ($platform === 'ios' && empty($orderId) && isset($purchaseData->transactionId)) {
        $transactionIdForDb = $purchaseData->transactionId;
        writeLog(11, "iOS için orderId boş, transactionId kullanılıyor", ['transactionId' => $transactionIdForDb]);
    }

    // Transaction kaydet (iOS için transactionId ile)
    $checkTx = $conn->query("SELECT id FROM iap_transactions WHERE order_id = '$transactionIdForDb'");
    writeLog(11, "Mevcut transaction kontrolü", ['exists' => $checkTx->num_rows > 0, 'orderId' => $transactionIdForDb]);

    if ($checkTx->num_rows == 0) {
        // FK Hatasını Önlemek İçin: Kullanıcı var mı kontrol et, yoksa oluştur
        $userIdDb = null;
        $checkUser = $conn->query("SELECT id FROM users WHERE userId = '$userId'");
        if ($checkUser->num_rows > 0) {
            $userRow = $checkUser->fetch_assoc();
            $userIdDb = $userRow['id'];
        } else {
            writeLog(11, "Kullanıcı veritabanında bulunamadı, oluşturuluyor...", ['userId' => $userId]);
            $createdAt = date('Y-m-d H:i:s');
            // E-posta ve isim bilinmiyor, varsayılan değerlerle oluşturuyoruz
            $insertUserSql = "INSERT INTO users (userId, name, email, created_at) VALUES ('$userId', 'Mobil Kullanıcı', NULL, '$createdAt')";
            if ($conn->query($insertUserSql)) {
                $userIdDb = $conn->insert_id;
                writeLog(11, "Yeni kullanıcı oluşturuldu", ['id' => $userIdDb]);
            } else {
                writeLog(11, "HATA: Kullanıcı oluşturulamadı", ['error' => $conn->error]);
            }
        }

        if ($userIdDb) {
            $purchaseTimeStr = $purchaseData->startTimeMillis ?? $purchaseData->purchaseTimeMillis;
            $purchaseTime = floor($purchaseTimeStr / 1000);
            $safeToken = $conn->real_escape_string($purchaseToken);
            $safePlatform = $conn->real_escape_string($platform);

            $insertTx = "INSERT INTO iap_transactions (user_id, product_id, order_id, purchase_token, purchase_time, status, platform)
                         VALUES ('$userIdDb', '$productId', '$transactionIdForDb', '$safeToken', '$purchaseTime', 'verified', '$safePlatform')";
            $txResult = $conn->query($insertTx);
            writeLog(11, "Transaction kaydedildi", ['success' => $txResult, 'error' => $conn->error ?: 'yok', 'orderId' => $transactionIdForDb]);
        } else {
            writeLog(11, "HATA: User ID DB alınamadığı için transaction kaydedilemedi.");
        }
    }

    if (isset($purchaseData->expiryTimeMillis)) {
        writeLog(12, "Abonelik kaydı yapılıyor...");
        $expiryTime = floor($purchaseData->expiryTimeMillis / 1000);
        $expiryDate = date('Y-m-d H:i:s', $expiryTime);
        $startTime = date('Y-m-d H:i:s');

        $upsertSub = "INSERT INTO user_subscriptions (user_id, product_id, start_date, end_date, status) 
                      VALUES ('$userId', '$productId', '$startTime', '$expiryDate', 'active')
                      ON DUPLICATE KEY UPDATE end_date = '$expiryDate', status = 'active', updated_at = NOW()";

        if ($conn->query($upsertSub)) {
            writeLog(12, "BAŞARILI: Abonelik kaydedildi", ['expiryDate' => $expiryDate]);
            echo json_encode(['status' => true, 'message' => 'Abonelik aktif', 'is_premium' => true, 'expiry' => $expiryDate]);
        } else {
            writeLog(12, "HATA: Abonelik DB hatası", ['error' => $conn->error]);
            echo json_encode(['status' => true, 'message' => 'Satın alma doğrulandı ancak kayıt güncellenemedi', 'is_premium' => true]);
        }
    } else {
        writeLog(12, "Tek seferlik ürün - Hak ekleniyor...");
        $insertCredit = "INSERT INTO user_credits (user_id, credits) 
                         VALUES ('$userId', 1)
                         ON DUPLICATE KEY UPDATE credits = credits + 1, updated_at = NOW()";

        if ($conn->query($insertCredit)) {
            $creditResult = $conn->query("SELECT credits FROM user_credits WHERE user_id = '$userId'");
            $currentCredits = 0;
            if ($creditResult && $row = $creditResult->fetch_assoc()) {
                $currentCredits = (int) $row['credits'];
            }
            writeLog(12, "BAŞARILI: Hak eklendi", ['userId' => $userId, 'credits' => $currentCredits]);
            echo json_encode(['status' => true, 'message' => 'Hak eklendi', 'credits' => $currentCredits]);
        } else {
            writeLog(12, "HATA: Hak ekleme hatası", ['error' => $conn->error]);
            echo json_encode(['status' => true, 'message' => 'Satın alma doğrulandı ancak hak eklenemedi']);
        }
    }

} else {
    writeLog(12, "Satın alma doğrulanamadı - isValid false");
    echo json_encode(['status' => false, 'message' => 'Satın alma doğrulanamadı (Durum geçersiz)']);
}

writeLog(13, "=== İSTEK TAMAMLANDI ===");
$conn->close();
?>