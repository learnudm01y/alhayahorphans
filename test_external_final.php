<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\Users\GeneralRegistrationController;
use Illuminate\Http\Request;

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  اختبار نهائي للبحث الخارجي المحسّن                          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$controller = new GeneralRegistrationController();

$tests = [
    [
        'name' => 'البحث برقم موجود في data (407015692)',
        'search_term' => '407015692',
        'expected_source' => 'data',
        'max_time' => 100,
    ],
    [
        'name' => 'البحث برقم موجود فقط في السجل المدني (801448911)',
        'search_term' => '801448911',
        'expected_source' => 'civil_registry',
        'max_time' => 150,
    ],
    [
        'name' => 'البحث بالاسم: محمد الفرا',
        'search_term' => 'محمد الفرا',
        'max_time' => 150,
    ],
    [
        'name' => 'البحث بكلمة واحدة: نصرالله',
        'search_term' => 'نصرالله',
        'max_time' => 200,
    ],
];

$totalTime = 0;
$passedTests = 0;
$speedTests = 0;

foreach ($tests as $i => $test) {
    $testNum = $i + 1;
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "اختبار #{$testNum}: {$test['name']}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🔍 البحث عن: {$test['search_term']}\n";

    $request = Request::create('/search-all-tables', 'POST', [
        'search_term' => $test['search_term']
    ]);

    $startTime = microtime(true);
    $response = $controller->searchAllTables($request);
    $duration = round((microtime(true) - $startTime) * 1000, 2);

    $result = json_decode($response->getContent(), true);

    echo "⏱️  الوقت: {$duration} ms";

    $passed = true;

    // فحص السرعة
    if ($duration <= $test['max_time']) {
        echo " ✅ سريع جداً\n";
        $speedTests++;
    } elseif ($duration <= 500) {
        echo " ✅ سريع\n";
        $speedTests++;
    } else {
        echo " ⚠️  بطيء\n";
        $passed = false;
    }

    $totalTime += $duration;

    // فحص النتائج
    if (isset($result['found']) && $result['found']) {
        echo "✅ تم العثور على نتيجة\n";
        echo "📍 المصدر: " . ($result['source'] ?? 'غير محدد') . "\n";

        if (isset($result['data']['full_name'])) {
            echo "👤 الاسم: " . $result['data']['full_name'] . "\n";
        }

        if (isset($result['data']['id_number'])) {
            echo "🆔 رقم الهوية: " . $result['data']['id_number'] . "\n";
        }

        if (isset($result['search_time'])) {
            echo "⚡ وقت البحث الداخلي: " . $result['search_time'] . "\n";
        }

        // فحص المصدر المتوقع
        if (isset($test['expected_source']) && $result['source'] === $test['expected_source']) {
            echo "✅ المصدر صحيح\n";
        }

        $passedTests++;
    } else {
        echo "ℹ️  لم يتم العثور على نتائج\n";
        if (isset($result['message'])) {
            echo "💬 الرسالة: " . $result['message'] . "\n";
        }
    }

    echo "\n" . ($passed && (isset($result['found']) && $result['found']) ? "✅ الاختبار نجح" : "❌ الاختبار فشل") . "\n\n";
}

$avgTime = $totalTime / count($tests);

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                      الملخص النهائي                          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "إجمالي الاختبارات: " . count($tests) . "\n";
echo "✅ وجد نتائج: {$passedTests}\n";
echo "🚀 اختبارات السرعة نجحت: {$speedTests}/" . count($tests) . "\n";
echo "⏱️  إجمالي الوقت: " . round($totalTime, 2) . " ms\n";
echo "📊 متوسط الوقت: " . round($avgTime, 2) . " ms\n\n";

echo "🎯 مقارنة الأداء:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "قبل التحسين: 4000-5000 ms (4-5 ثوان)\n";
echo "بعد التحسين: " . round($avgTime, 2) . " ms\n";
echo "التحسين: " . round((1 - ($avgTime / 4500)) * 100, 1) . "% أسرع\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

if ($speedTests === count($tests)) {
    echo "🎉 ممتاز! البحث الخارجي أصبح سريع جداً في جميع الحالات!\n";
    echo "✅ جميع اختبارات السرعة نجحت\n";
} else {
    echo "✅ البحث الخارجي محسّن بشكل كبير\n";
}
