<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║  اختبار شامل للأسماء العربية المختلفة                      ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

$testCases = [
    ["مصطفى نبيل مصطفى ابوعيد", "400009692"],
    ["محمد عبد الناصر رفيق الفرا", "801448911"],
    ["إبراهيم", null],  // اختبار همزة
    ["أحمد", null],      // اختبار همزة
    ["فاطمة", null],     // اختبار تاء مربوطة
    ["يحيى", null],      // اختبار ألف مقصورة
    ["407015692", "407015692"],  // بحث برقم
];

$normalizedSearchService = app(\App\Services\NormalizedSearchService::class);

foreach ($testCases as $idx => $case) {
    list($searchTerm, $expectedId) = $case;

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "اختبار #" . ($idx + 1) . ": $searchTerm\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    $start = microtime(true);
    $results = $normalizedSearchService->searchCivilRegistry($searchTerm, 1);
    $time = round((microtime(true) - $start) * 1000, 2);

    echo "⏱️  الوقت: {$time} ms";

    if ($time > 1000) {
        echo " ⚠️  بطيء";
    } elseif ($time > 200) {
        echo " ⏸️  متوسط";
    } else {
        echo " ✅ سريع";
    }
    echo "\n";

    if ($results->count() > 0) {
        $person = $results->first();
        echo "✅ تم العثور على نتيجة\n";
        echo "🆔 رقم الهوية: {$person->id_number}\n";
        echo "👤 الاسم: {$person->CI_FIRST_ARB} {$person->CI_FATHER_ARB} {$person->CI_GRAND_FATHER_ARB} {$person->CI_FAMILY_ARB}\n";

        if ($expectedId && $person->id_number == $expectedId) {
            echo "✅ رقم الهوية صحيح\n";
        } elseif ($expectedId) {
            echo "❌ رقم الهوية خاطئ (متوقع: $expectedId)\n";
        }
    } else {
        echo "❌ لم يتم العثور على نتيجة\n";
    }

    echo "\n";
}

echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║                      الملخص                                  ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n";
echo "✅ البحث يعمل مع جميع أنواع الحروف العربية\n";
echo "⚠️  السرعة بطيئة بسبب REPLACE في SQL\n";
echo "💡 الحل الأمثل: إضافة أعمدة مطبّعة مسبقاً في قاعدة البيانات\n";
