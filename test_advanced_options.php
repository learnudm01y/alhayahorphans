<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Barryvdh\Snappy\Facades\SnappyPdf as PDF;

echo "=== اختبار مع خيارات wkhtmltopdf المتقدمة ===\n\n";

$html = '<!DOCTYPE html>
<html dir="rtl">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<style>
@page {
    margin: 2cm;
}
body {
    font-family: Arial, "Traditional Arabic", "Simplified Arabic", Tahoma, sans-serif;
    font-size: 14pt;
    direction: rtl;
    text-align: right;
    line-height: 1.6;
}
h1 {
    color: #003366;
    font-size: 24pt;
    text-align: center;
    border-bottom: 2px solid #003366;
    padding-bottom: 10px;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
}
td {
    border: 1px solid #333;
    padding: 10px;
    font-size: 14pt;
}
.label {
    background: #e0e0e0;
    font-weight: bold;
}
</style>
</head>
<body>
<h1>تقرير بيانات اليتيم</h1>

<table>
<tr>
    <td class="label">رقم الملف</td>
    <td>032454</td>
</tr>
<tr>
    <td class="label">اسم اليتيم</td>
    <td>يوسف وجدي يوسف جروان</td>
</tr>
<tr>
    <td class="label">رقم الجوال</td>
    <td>595757929</td>
</tr>
<tr>
    <td class="label">المدينة</td>
    <td>البريج</td>
</tr>
<tr>
    <td class="label">اسم المدرسة</td>
    <td>مدرسة الخلافاء</td>
</tr>
</table>

<p>هذا نص تجريبي للتأكد من ظهور اللغة العربية بشكل صحيح في ملف PDF</p>
</body>
</html>';

try {
    echo "إنشاء PDF مع خيارات متقدمة...\n";

    $pdfContent = PDF::loadHTML($html)
        ->setOption('encoding', 'UTF-8')
        ->setOption('page-size', 'A4')
        ->setOption('margin-top', '20mm')
        ->setOption('margin-right', '20mm')
        ->setOption('margin-bottom', '20mm')
        ->setOption('margin-left', '20mm')
        ->setOption('enable-local-file-access', true)
        ->setOption('no-stop-slow-scripts', true)
        ->setOption('javascript-delay', '1000')
        ->setOption('enable-javascript', false)
        ->setOption('print-media-type', true)
        ->output();

    file_put_contents('advanced_test.pdf', $pdfContent);
    $size = strlen($pdfContent);

    echo "✅ تم إنشاء PDF: advanced_test.pdf\n";
    echo "✅ الحجم: {$size} bytes\n";

    // فحص محتوى
    if (mb_strpos($pdfContent, 'يوسف', 0, 'UTF-8') !== false) {
        echo "✅ النص العربي موجود في PDF!\n";
    } else {
        echo "⚠️ النص العربي قد يكون مُرمّز كـ glyphs\n";
    }

    // فتح PDF
    echo "\nفتح ملف PDF...\n";
    exec('start advanced_test.pdf');

    echo "\n⚠️ يرجى التحقق من الملف يدوياً!\n";
    echo "إذا كان الملف فارغاً، المشكلة في wkhtmltopdf نفسه\n";
    echo "وسنحتاج لاستخدام مكتبة PDF بديلة مثل TCPDF أو DomPDF\n";

} catch (\Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
