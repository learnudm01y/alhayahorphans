<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  اختبار الأداء: مع المسافات vs بدون المسافات              ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$testCases = [
    ['search' => 'محمد عبد الناصر رفيق الفرا', 'expected_id' => '801448911', 'desc' => 'مع المسافات'],
    ['search' => 'محمد عبدالناصر رفيق الفرا', 'expected_id' => '801448911', 'desc' => 'بدون مسافة (عبدالناصر)'],
    ['search' => 'مصطفى نبيل مصطفى ابوعيد', 'expected_id' => '933046774', 'desc' => 'مصطفى (حرف ى)'],
    ['search' => 'مصطفي نبيل مصطفي ابوعيد', 'expected_id' => '933046774', 'desc' => 'مصطفي (حرف ي)'],
];

$controller = new \App\Http\Controllers\Users\GeneralRegistrationController();

foreach ($testCases as $test) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🔍 {$test['desc']}: {$test['search']}\n";

    $request = new \Illuminate\Http\Request(['search_term' => $test['search']]);

    $start = microtime(true);
    $response = $controller->searchAllTables($request);
    $result = $response->getData(true);
    $time = round((microtime(true) - $start) * 1000, 2);

    echo "⏱️  الوقت: {$time} ms\n";

    if (isset($result['found']) && $result['found']) {
        $actualId = $result['data']['id_number'];
        $fullName = $result['data']['full_name'];

        if ($actualId == $test['expected_id']) {
            echo "✅ صحيح! رقم الهوية: {$actualId}\n";
            echo "👤 الاسم: {$fullName}\n";
        } else {
            echo "❌ خطأ! المتوقع: {$test['expected_id']}, الفعلي: {$actualId}\n";
        }
    } else {
        echo "❌ لم يتم العثور على نتيجة\n";
    }

    echo "\n";
}

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  ملخص الاختبار                                               ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "تم اختبار 4 حالات مختلفة:\n";
echo "1. البحث مع المسافات (استخدام INDEX)\n";
echo "2. البحث بدون مسافات (استخدام REPLACE)\n";
echo "3. البحث مع حرف ى\n";
echo "4. البحث مع حرف ي\n";
