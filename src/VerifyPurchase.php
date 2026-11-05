<?php
// Kredi paketi satın alma doğrulama (tüketilebilir ürün).
// Android: Google Play Developer API (purchases.products). iOS: StoreKit 2 JWS veya verifyReceipt.
// Aynı işlem (purchaseToken / transactionId) ikinci kez kredi vermez (iap_transactions UNIQUE).
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/AppleJws.php';

function iap_log($message, array $ctx = [])
{
    $dir = dirname(__DIR__) . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    // Token/receipt asla loglanmaz
    @file_put_contents(
        $dir . '/iap_requests.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $message . ($ctx ? ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE) : '') . "\n",
        FILE_APPEND | LOCK_EX
    );
}

function credits_for_product($productId)
{
    $map = json_decode(CREDIT_PACKS, true);
    if (is_array($map) && isset($map[$productId])) {
        return (int) $map[$productId];
    }
    // "credits_10" gibi son sayı kredi miktarıdır
    if (preg_match('/(\d+)\s*$/', $productId, $m) && (int) $m[1] > 0) {
        return (int) $m[1];
    }
    return 0;
}

function fail($message, array $ctx = [])
{
    iap_log('RED: ' . $message, $ctx);
    json_out(false, $message);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    fail('Geçersiz veri');
}

$userId = trim((string) ($input['userId'] ?? ''));
$productId = trim((string) ($input['productId'] ?? ''));
$receipt = (string) ($input['receipt'] ?? '');
$purchaseToken = (string) ($input['purchaseToken'] ?? '');
$platform = $receipt !== '' ? 'ios' : 'android';

if ($userId === '' || $productId === '' || ($receipt === '' && $purchaseToken === '')) {
    fail('Eksik parametreler', ['platform' => $platform]);
}

$credits = credits_for_product($productId);
if ($credits < 1) {
    fail('Bilinmeyen ürün', ['productId' => $productId]);
}

$st = $conn->prepare('SELECT 1 FROM users WHERE userId = ? LIMIT 1');
$st->bind_param('s', $userId);
$st->execute();
if ($st->get_result()->num_rows === 0) {
    fail('Kullanıcı bulunamadı');
}

// ---- Doğrulama: başarılıysa $txKey (replay anahtarı) ve $orderRef dolar ----
$txKey = null;
$orderRef = (string) ($input['orderId'] ?? '');

if ($platform === 'android') {
    // İstekteki packageName'e güvenilmez (mobil iOS bundle id'sini gönderiyor: com.mws.ruyaTabiriAi);
    // Android paketi sunucuda sabit: Play'deki gerçek paket com.mws.ruya_tabiri_ai
    $packageName = ANDROID_PACKAGE;
    if (!class_exists('Google\Client')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    }
    if (!is_readable(GOOGLE_SERVICE_ACCOUNT)) {
        error_log('VerifyPurchase: service account dosyasi yok');
        fail('Doğrulanamadı');
    }
    try {
        $client = new Google\Client();
        $client->setAuthConfig(GOOGLE_SERVICE_ACCOUNT);
        $client->addScope(Google\Service\AndroidPublisher::ANDROIDPUBLISHER);
        $service = new Google\Service\AndroidPublisher($client);
        $purchase = $service->purchases_products->get($packageName, $productId, $purchaseToken);
        // 0 = satın alındı (1 iptal, 2 beklemede)
        if ((int) $purchase->getPurchaseState() !== 0) {
            fail('Doğrulanamadı', ['state' => $purchase->getPurchaseState()]);
        }
        $txKey = 'android:' . hash('sha256', $purchaseToken);
        $orderRef = $purchase->getOrderId() ?: $orderRef;
        // Mağaza 3 gün içinde onaylanmayan satın almayı iade eder; best effort onayla
        if ((int) $purchase->getAcknowledgementState() === 0) {
            try {
                $service->purchases_products->acknowledge(
                    $packageName,
                    $productId,
                    $purchaseToken,
                    new Google\Service\AndroidPublisher\ProductPurchasesAcknowledgeRequest()
                );
            } catch (Throwable $e) {
                iap_log('acknowledge basarisiz', ['err' => $e->getMessage()]);
            }
        }
    } catch (Throwable $e) {
        fail('Doğrulanamadı', ['err' => $e->getMessage()]);
    }
} else {
    $transactionId = null;

    if (count(explode('.', $receipt)) === 3) {
        // StoreKit 2 JWS
        $payload = apple_verify_jws($receipt);
        if (!$payload || ($payload['bundleId'] ?? '') !== IOS_BUNDLE_ID || ($payload['productId'] ?? '') !== $productId) {
            fail('Doğrulanamadı', ['jws' => $payload ? 'uyumsuz' : 'imza']);
        }
        if (!empty($payload['revocationDate'])) {
            fail('Doğrulanamadı', ['jws' => 'iade']);
        }
        $transactionId = (string) ($payload['transactionId'] ?? '');
    } else {
        // Klasik App Store makbuzu
        $verify = function ($url) use ($receipt) {
            $body = ['receipt-data' => $receipt];
            if (IOS_SHARED_SECRET !== '') {
                $body['password'] = IOS_SHARED_SECRET;
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($body),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 20,
            ]);
            $res = curl_exec($ch);
            curl_close($ch);
            return $res === false ? null : json_decode($res, true);
        };
        $res = $verify('https://buy.itunes.apple.com/verifyReceipt');
        if (is_array($res) && ($res['status'] ?? null) === 21007) {
            $res = $verify('https://sandbox.itunes.apple.com/verifyReceipt');
        }
        if (!is_array($res) || ($res['status'] ?? -1) !== 0) {
            fail('Doğrulanamadı', ['apple_status' => is_array($res) ? ($res['status'] ?? null) : 'yanit yok']);
        }
        if (($res['receipt']['bundle_id'] ?? '') !== IOS_BUNDLE_ID) {
            fail('Doğrulanamadı', ['bundle' => 'uyumsuz']);
        }
        // Makbuz tüm geçmiş işlemleri içerir: bu ürüne ait, henüz işlenmemiş ilkini seç
        $items = array_merge($res['receipt']['in_app'] ?? [], $res['latest_receipt_info'] ?? []);
        foreach ($items as $item) {
            if (($item['product_id'] ?? '') !== $productId || empty($item['transaction_id'])) {
                continue;
            }
            $chk = $conn->prepare('SELECT 1 FROM iap_transactions WHERE tx_key = ?');
            $key = 'ios:' . $item['transaction_id'];
            $chk->bind_param('s', $key);
            $chk->execute();
            if ($chk->get_result()->num_rows === 0) {
                $transactionId = (string) $item['transaction_id'];
                break;
            }
        }
    }

    if (!$transactionId) {
        fail('Doğrulanamadı', ['ios' => 'yeni islem yok']);
    }
    $txKey = 'ios:' . $transactionId;
    $orderRef = $transactionId;
}

// ---- Kredi ekle (tek transaction; aynı işlem ikinci kez kredi vermez) ----
$conn->begin_transaction();
$ins = $conn->prepare('INSERT INTO iap_transactions (tx_key, user_id, platform, product_id, order_id, credits) VALUES (?, ?, ?, ?, ?, ?)');
$ins->bind_param('sssssi', $txKey, $userId, $platform, $productId, $orderRef, $credits);
if (!$ins->execute()) {
    $conn->rollback();
    if ($conn->errno === 1062 || $ins->errno === 1062) {
        fail('Bu satın alma daha önce işlendi', ['platform' => $platform]);
    }
    error_log('iap insert hatasi: ' . $ins->error);
    fail('Doğrulanamadı');
}

$up = $conn->prepare('UPDATE tokens SET token = token + ? WHERE userId = ?');
$up->bind_param('is', $credits, $userId);
$ok = $up->execute() && $up->affected_rows > 0;
if (!$ok) {
    // Kullanıcının kredi satırı yoksa oluştur
    $new = $conn->prepare('INSERT INTO tokens (userId, token, created_at) VALUES (?, ?, NOW())');
    $new->bind_param('si', $userId, $credits);
    $ok = $new->execute();
}
if (!$ok) {
    $conn->rollback();
    error_log('iap kredi ekleme hatasi: ' . $conn->error);
    fail('Doğrulanamadı');
}
$conn->commit();

iap_log('OK', ['user' => $userId, 'platform' => $platform, 'product' => $productId, 'credits' => $credits]);
json_out(true, 'Ödeme doğrulandı', [
    'success' => true,
    'message' => 'Doğrulama başarılı',
    'addedTokens' => $credits,
]);
