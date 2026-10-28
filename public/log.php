<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=UTF-8');
mb_internal_encoding("UTF-8");

$logFile = __DIR__ . '/basit_loglar.txt';

$raw = file_get_contents("php://input");

// UTF-8 olmayan karakterleri TEMİZLE
$clean = mb_convert_encoding($raw, 'UTF-8', 'UTF-8');
$clean = preg_replace('/[^\P{C}\n\r\t]/u', '', $clean); // control chars temizle

$decoded = json_decode($clean, true);
if (json_last_error() === JSON_ERROR_NONE) {
    $log = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    $log = $clean;
}

$time = date("Y-m-d H:i:s");
$sep = str_repeat("=", 70);
$record = "\n$sep\n[$time]\n$sep\n$log\n";

file_put_contents($logFile, $record, FILE_APPEND | LOCK_EX);

echo json_encode(["status" => "ok"], JSON_UNESCAPED_UNICODE);
