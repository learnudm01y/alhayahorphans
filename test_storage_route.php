<?php

// اختبار Route الصور

$testUrls = [
    'http://127.0.0.1:8000/storage/report_designs/m2Y1PEU48oxEntKNucYJVEqUH2zVjJPtOVoKK1lk.png',
    'http://127.0.0.1:8000/storage/report_designs/C72tBtj7v034Ru8s0pGNeqEf2Eb1UI0WqZZXBhwZ.png',
    'http://127.0.0.1:8000/storage/report_designs/xv1VUTiAATndo71ClLC6Uh0lL2SH8hmI1S6nT3hr.png',
];

echo "=== اختبار الوصول إلى الصور ===\n\n";

foreach ($testUrls as $url) {
    $filename = basename($url);
    echo "اختبار: {$filename}\n";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        echo "  ✓ نجح (200 OK)\n";
    } else {
        echo "  ✗ فشل (HTTP {$httpCode})\n";
    }

    echo "\n";
}

// اختبار الملف مباشرة
echo "=== اختبار الملفات المباشرة ===\n\n";
$files = glob(storage_path("app/public/report_designs/*"));
foreach ($files as $file) {
    echo "  ✓ " . basename($file) . " (" . filesize($file) . " bytes)\n";
}
