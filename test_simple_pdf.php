<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Barryvdh\Snappy\Facades\SnappyPdf as PDF;

echo "=== اختبار PDF مبسط ===\n";

// اختبار HTML بسيط مع النص الثابت
$simpleHtml = '<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<style>
body {
    font-family: Arial, sans-serif;
    font-size: 16px;
    direction: rtl;
    text-align: right;
    padding: 20px;
}
h1 {
    color: #003366;
    text-align: center;
}
</style>
</head>
<body>
<h1>تقرير اليتيم</h1>
<p><strong>رقم الملف:</strong> 032454</p>
<p><strong>اسم اليتيم:</strong> يوسف وجدي يوسف جروان</p>
<p><strong>رقم الجوال:</strong> 595757929</p>
<p><strong>المدينة:</strong> البريج</p>
<p>هذا اختبار للتأكد من أن النص العربي يظهر في PDF</p>
</body>
</html>';

try {
    echo "إنشاء PDF بسيط...\n";
    $pdf = PDF::loadHTML($simpleHtml)
        ->setOption('encoding', 'UTF-8')
        ->setOption('enable-local-file-access', true)
        ->setOption('page-size', 'A4');

    $pdfContent = $pdf->output();
    $pdfSize = strlen($pdfContent);

    file_put_contents('test_simple.pdf', $pdfContent);
    echo "✅ تم إنشاء test_simple.pdf - الحجم: {$pdfSize} bytes\n";

    // اختبار 2: HTML مع CSS متقدم
    echo "\nاختبار PDF مع CSS متقدم...\n";

    $advancedHtml = view('user.dashboard.pdf.orphan-report', [
        'file_number' => '032454',
        'orphan_name' => 'يوسف وجدي يوسف جروان',
        'phone_number' => '595757929',
        'city' => 'البريج',
        'school_name' => 'مدرسة الخلافاء',
        'guardian_name' => 'حنان محمد أحمد جروان',
        'guardian_relationship' => 'ام',
        'guardian_health' => 'سليم',
        'guardian_job' => 'لا يعمل'
    ])->render();

    $advancedPdf = PDF::loadHTML($advancedHtml)
        ->setOption('encoding', 'UTF-8')
        ->setOption('enable-local-file-access', true)
        ->setOption('page-size', 'A4')
        ->setOption('disable-smart-shrinking', true)
        ->setOption('load-error-handling', 'ignore')
        ->setOption('load-media-error-handling', 'ignore');

    $advancedContent = $advancedPdf->output();
    $advancedSize = strlen($advancedContent);

    file_put_contents('test_advanced.pdf', $advancedContent);
    echo "✅ تم إنشاء test_advanced.pdf - الحجم: {$advancedSize} bytes\n";

    if ($pdfSize > 1000 && $advancedSize > 1000) {
        echo "\n✅ PDF يعمل بشكل طبيعي!\n";
        echo "المشكلة قد تكون في:\n";
        echo "1. فتح PDF في المتصفح\n";
        echo "2. إعدادات wkhtmltopdf\n";
        echo "3. ملف CSS\n";
    }

} catch (\Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
