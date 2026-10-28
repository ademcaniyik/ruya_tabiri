<?php
header('Content-Type: text/plain; charset=UTF-8');

// UTF-8 BOM bas → Chrome zorla UTF-8 okur
echo "\xEF\xBB\xBF";

$file = __DIR__ . '/basit_loglar.txt';

if (!file_exists($file)) {
    exit('Log dosyası yok');
}

$raw = file_get_contents($file);

// UTF-8'e zorla çevir
$clean = iconv('UTF-8', 'UTF-8//IGNORE', $raw);

echo $clean;
