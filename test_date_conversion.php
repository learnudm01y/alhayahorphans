<?php

require __DIR__.'/vendor/autoload.php';

echo "========================================\n";
echo "🔍 اختبار تحويل التاريخ\n";
echo "========================================\n\n";

$testDate = '22-05-1994';

echo "التاريخ الأصلي: $testDate\n\n";

// اختبار التنسيق
if (preg_match('/^\d{2}-\d{2}-\d{4}/', $testDate)) {
    echo "✓ التنسيق مطابق لـ DD-MM-YYYY\n\n";

    try {
        $dateObj = DateTime::createFromFormat('d-m-Y', $testDate);

        if ($dateObj) {
            $converted = $dateObj->format('Y-m-d');
            echo "✅ التحويل نجح!\n";
            echo "التاريخ المحول: $converted\n\n";
        } else {
            echo "❌ فشل إنشاء كائن التاريخ\n";
            print_r(DateTime::getLastErrors());
        }
    } catch (Exception $e) {
        echo "❌ خطأ: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ التنسيق غير مطابق\n";
}

echo "\n========================================\n";
echo "🔍 اختبار مع ExcelImportService\n";
echo "========================================\n\n";

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\ExcelImportService;

// استخدام Reflection للوصول إلى الدالة الخاصة
$service = new ExcelImportService();
$reflection = new ReflectionClass($service);

// اختبار cleanValue
$cleanValueMethod = $reflection->getMethod('cleanValue');
$cleanValueMethod->setAccessible(true);

$result = $cleanValueMethod->invoke($service, $testDate);

echo "النتيجة من cleanValue: ";
var_dump($result);
echo "\n";

// اختبار isDate
$isDateMethod = $reflection->getMethod('isDate');
$isDateMethod->setAccessible(true);

$isDate = $isDateMethod->invoke($service, $testDate);

echo "هل هو تاريخ؟ " . ($isDate ? "نعم ✓" : "لا ✗") . "\n";

// اختبار parseDate
$parseDateMethod = $reflection->getMethod('parseDate');
$parseDateMethod->setAccessible(true);

$parsed = $parseDateMethod->invoke($service, $testDate);

echo "النتيجة من parseDate: ";
var_dump($parsed);
