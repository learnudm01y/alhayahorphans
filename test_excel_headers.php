<?php
require 'vendor/autoload.php';

$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('مصنف متعدد الأبعاد.xlsx');
$worksheet = $spreadsheet->getActiveSheet();
$rows = $worksheet->toArray();

// عرض الصف الأول (الرؤوس)
echo "=== الصف الأول (الرؤوس) ===" . PHP_EOL;
$headers = $rows[0];
foreach ($headers as $index => $header) {
    if (!empty($header)) {
        echo "[" . $index . "] => '" . $header . "'" . PHP_EOL;
    }
}

echo PHP_EOL . "=== أول 5 صفوف بيانات ===" . PHP_EOL;
for ($i = 1; $i <= 5 && $i < count($rows); $i++) {
    echo "--- الصف " . ($i + 1) . " ---" . PHP_EOL;
    foreach ($rows[$i] as $index => $value) {
        if (!empty($value) && isset($headers[$index]) && !empty($headers[$index])) {
            echo "[" . $index . "] " . $headers[$index] . " => '" . $value . "'" . PHP_EOL;
        }
    }
    echo PHP_EOL;
}
