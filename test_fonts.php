<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Barryvdh\Snappy\Facades\SnappyPdf as PDF;

echo "=== اختبار بسيط للخطوط العربية ===\n\n";

// HTML بسيط جداً مع نص عربي
$simpleHtml = '<!DOCTYPE html>
<html dir="rtl">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<style>
body {
    font-family: "DejaVu Sans", Arial, Tahoma, sans-serif;
    direction: rtl;
    text-align: right;
}
</style>
</head>
<body>
<h1>تقرير اليتيم</h1>
<p>رقم الملف: 032454</p>
<p>اسم اليتيم: يوسف وجدي يوسف جروان</p>
<p>رقم الجوال: 595757929</p>
<p>المدينة: البريج</p>
</body>
</html>';

file_put_contents('simple_test.html', $simpleHtml);
echo "✅ تم حفظ HTML في simple_test.html\n";

// إنشاء PDF مع خيارات مختلفة
echo "\nاختبار 1: Snappy مع DejaVu Sans\n";
try {
    $pdf1 = PDF::loadHTML($simpleHtml)
        ->setOption('encoding', 'UTF-8')
        ->setOption('enable-local-file-access', true)
        ->output();

    file_put_contents('test1_dejavu.pdf', $pdf1);
    echo "✅ حجم: " . strlen($pdf1) . " bytes - test1_dejavu.pdf\n";
} catch (\Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}

// اختبار 2: باستخدام Traditional Arabic
$html2 = str_replace('DejaVu Sans', 'Traditional Arabic', $simpleHtml);
echo "\nاختبار 2: Snappy مع Traditional Arabic\n";
try {
    $pdf2 = PDF::loadHTML($html2)
        ->setOption('encoding', 'UTF-8')
        ->setOption('enable-local-file-access', true)
        ->output();

    file_put_contents('test2_traditional.pdf', $pdf2);
    echo "✅ حجم: " . strlen($pdf2) . " bytes - test2_traditional.pdf\n";
} catch (\Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}

// اختبار 3: wkhtmltopdf مباشر
echo "\nاختبار 3: wkhtmltopdf مباشر\n";
$wkhtmlPath = 'C:\\PROGRA~1\\WKHTML~1\\bin\\wkhtmltopdf.exe';

file_put_contents('direct_test.html', $simpleHtml);
$cmd = "\"{$wkhtmlPath}\" --encoding UTF-8 --enable-local-file-access direct_test.html test3_direct.pdf 2>&1";
exec($cmd, $output, $returnCode);

if (file_exists('test3_direct.pdf')) {
    echo "✅ حجم: " . filesize('test3_direct.pdf') . " bytes - test3_direct.pdf\n";
} else {
    echo "❌ فشل\n";
    echo implode("\n", $output) . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "افتح الملفات التالية وافحص إذا كانت فارغة أم لا:\n";
echo "  - test1_dejavu.pdf\n";
echo "  - test2_traditional.pdf\n";
echo "  - test3_direct.pdf\n";
