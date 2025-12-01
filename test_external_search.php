<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\Users\GeneralRegistrationController;
use Illuminate\Http\Request;

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  اختبار البحث الخارجي المحسّن (searchAllTables)              ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$controller = new GeneralRegistrationController();

$tests = [
    [
        'name' => 'البحث برقم هوية نصرالله',
        'search_term' => '407015692',
        'expected_source' => 'civil_registry',
    ],
    [
        'name' => 'البحث بالاسم: نصرالله عبد الناصر رفيق الفرا',
        'search_term' => 'نصرالله عبد الناصر رفيق الفرا',
        'expected_source' => 'civil_registry',
    ],
    [
        'name' => 'البحث بالاسم: محمد عبد الناصر رفيق الفرا',
        'search_term' => 'محمد عبد الناصر رفيق الفرا',
        'expected_source' => 'civil_registry',
    ],
    [
        'name' => 'البحث بكلمتين: نصرالله الفرا',
        'search_term' => 'نصرالله الفرا',
        'expected_source' => 'civil_registry',
    ],
];

$totalTime = 0;
$passedTests = 0;

foreach ($tests as $i => $test) {
    $testNum = $i + 1;
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "اختبار #{$testNum}: {$test['name']}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🔍 البحث عن: {$test['search_term']}\n";

    // إنشاء Request
    $request = Request::create('/search-all-tables', 'POST', [
        'search_term' => $test['search_term']
    ]);

    $startTime = microtime(true);
    $response = $controller->searchAllTables($request);
    $duration = round((microtime(true) - $startTime) * 1000, 2);

    $result = json_decode($response->getContent(), true);

    echo "⏱️  الوقت: {$duration} ms";

    $passed = true;

    // فحص الوقت (يجب أن يكون أقل من 200ms)
    if ($duration > 200) {
        echo " ⚠️  بطيء\n";
        $passed = false;
    } else {
        echo " ✅ سريع\n";
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
            echo "⚡ وقت البحث المُعاد: " . $result['search_time'] . "\n";
        }

        $passedTests++;
    } else {
        echo "❌ لم يتم العثور على نتائج\n";
        $passed = false;
    }

    echo "\n" . ($passed ? "✅ الاختبار نجح" : "❌ الاختبار فشل") . "\n\n";
}

$avgTime = $totalTime / count($tests);

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                      الملخص النهائي                          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "إجمالي الاختبارات: " . count($tests) . "\n";
echo "✅ نجح: {$passedTests}\n";
echo "❌ فشل: " . (count($tests) - $passedTests) . "\n";
echo "⏱️  إجمالي الوقت: " . round($totalTime, 2) . " ms\n";
echo "📊 متوسط الوقت: " . round($avgTime, 2) . " ms\n\n";

if ($passedTests === count($tests) && $avgTime < 200) {
    echo "🎉 ممتاز! البحث الخارجي أصبح سريع جداً!\n";
    echo "📈 التحسين: من 4-5 ثوان (4000-5000ms) → " . round($avgTime, 2) . "ms\n";
    echo "🚀 تحسين بنسبة " . round((1 - ($avgTime / 4000)) * 100, 1) . "%\n";
} else {
    echo "⚠️  بعض الاختبارات بحاجة لمراجعة\n";
}
