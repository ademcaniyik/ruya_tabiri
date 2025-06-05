<?php
$output = [];
$returnVar = null;

exec('HOME=/tmp php composer.phar dump-autoload 2>&1', $output, $returnVar);

echo "Return code: $returnVar\n";
echo "Output:\n" . implode("\n", $output);

if ($returnVar === 0) {
    echo "Autoload dosyası başarıyla güncellendi.\n";
} else {
    echo "Autoload dosyası güncellenirken bir hata oluştu.\n";
}
?>
