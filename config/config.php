<?php

// config.php
// Gizli değerler koda yazılmaz; sunucudaki env dosyasından okunur.
// Varsayılan yol: /etc/ruyatabiri.env (RUYA_ENV_FILE ile değiştirilebilir). Örnek: config/ruyatabiri.env.example

// Uyarılar JSON yanıtını bozmasın: ekrana basma, dosyaya yaz
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/logs/php_error.log');

if (!function_exists('ruya_env')) {
    function ruya_env($key, $default = null)
    {
        static $vars = null;
        if ($vars === null) {
            $vars = [];
            $file = getenv('RUYA_ENV_FILE') ?: '/etc/ruyatabiri.env';
            if (is_readable($file)) {
                foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    $line = trim($line);
                    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                        continue;
                    }
                    [$k, $v] = explode('=', $line, 2);
                    $vars[trim($k)] = trim($v, " \t\"'");
                }
            }
        }
        return $vars[$key] ?? $default;
    }
}

if (!function_exists('json_out')) {
    // Standart yanıt zarfı. status her zaman bool, message her zaman string.
    function json_out($status, $message = '', $parameters = null, $httpCode = 200)
    {
        if ($httpCode !== 200) {
            http_response_code($httpCode);
        }
        header('Content-Type: application/json; charset=utf-8');
        $out = ['status' => (bool) $status, 'message' => (string) $message];
        if ($parameters !== null) {
            $out['parameters'] = $parameters;
        }
        echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        exit;
    }
}

define('API_KEY', ruya_env('OPENAI_API_KEY', ''));
define('API_URL', 'https://api.openai.com/v1/chat/completions');
define('OPENAI_MODEL', ruya_env('OPENAI_MODEL', 'gpt-4o-mini'));

// iOS App Store Shared Secret
define('IOS_SHARED_SECRET', ruya_env('IOS_SHARED_SECRET', ''));
define('ANDROID_PACKAGE', ruya_env('ANDROID_PACKAGE', 'com.mws.ruya_tabiri_ai'));
define('IOS_BUNDLE_ID', ruya_env('IOS_BUNDLE_ID', 'com.mws.ruyaTabiriAi'));
define('GOOGLE_SERVICE_ACCOUNT', ruya_env('GOOGLE_SERVICE_ACCOUNT', __DIR__ . '/service_account.json'));

// Başlangıç kredisi ve günlük yenilenme için kullanılan değerler
define('INITIAL_CREDITS', (int) ruya_env('INITIAL_CREDITS', 1));

// Ürün kimliği → kredi. Tanımlı değilse "credits_10" gibi son sayıdan türetilir (VerifyPurchase.php).
// Örnek: CREDIT_PACKS={"credits_10":10,"credits_25":25}
define('CREDIT_PACKS', ruya_env('CREDIT_PACKS', '{}'));

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli(
    ruya_env('DB_HOST', 'localhost'),
    ruya_env('DB_USER', ''),
    ruya_env('DB_PASS', ''),
    ruya_env('DB_NAME', 'ruyatabiri')
);

if ($conn->connect_error) {
    error_log('DB baglanti hatasi: ' . $conn->connect_error);
    json_out(false, 'Veritabanı bağlantı hatası', null, 500);
}
$conn->set_charset('utf8mb4');
// Zaman karşılaştırmaları UTC üzerinden yapılır
$conn->query("SET time_zone = '+00:00'");
date_default_timezone_set('UTC');
