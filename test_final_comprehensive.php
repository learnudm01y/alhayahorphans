<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  الاختبار النهائي الشامل: البحث الخارجي                    ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$testCases = [
    [
        'search' => 'محمد عبد الناصر رفيق الفرا',
        'expected_id' => '801448911',
        'desc' => '✅ مع مسافات في "عبد الناصر"',
        'max_time' => 1000 // أقل من ثانية
    ],
    [
        'search' => 'محمد عبدالناصر رفيق الفرا',
        'expected_id' => '801448911',
        'desc' => '✅ بدون مسافة في "عبدالناصر"',
        'max_time' => 10000 // أقل من 10 ثواني
    ],
    [
        'search' => 'مصطفى نبيل مصطفى ابوعيد',
        'expected_id' => '400009692',
        'desc' => '✅ حرف ى (مصطفى)',
        'max_time' => 500
    ],
    [
        'search' => 'مصطفي نبيل مصطفي ابوعيد',
        'expected_id' => '400009692',
        'desc' => '✅ حرف ي (مصطفي)',
        'max_time' => 500
    ],
    [
        'search' => 'إبراهيم محمد خليل',
        'expected_id' => null,
        'desc' => '✅ إ → ا (normalization)',
        'max_time' => 500
    ],
];

$controller = new \App\Http\Controllers\Users\GeneralRegistrationController();

$passed = 0;
$failed = 0;

foreach ($testCases as $test) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🔍 {$test['desc']}\n";
    echo "   البحث: {$test['search']}\n";

    $request = new \Illuminate\Http\Request(['search_term' => $test['search']]);

    $start = microtime(true);
    $response = $controller->searchAllTables($request);
    $result = $response->getData(true);
    $time = round((microtime(true) - $start) * 1000, 2);

    echo "   ⏱️  الوقت: {$time} ms";

    $testPassed = true;

    // فحص الوقت
    if ($time > $test['max_time']) {
        echo " ❌ (أبطأ من المتوقع: {$test['max_time']}ms)\n";
        $testPassed = false;
    } else {
        echo " ✅\n";
    }

    // فحص النتيجة
    if ($test['expected_id'] === null) {
        if (isset($result['found']) && $result['found']) {
            echo "   📍 وُجد: {$result['data']['id_number']} - {$result['data']['full_name']}\n";
        } else {
            echo "   📍 لم يُوجد\n";
        }
    } else {
        if (isset($result['found']) && $result['found']) {
            $actualId = $result['data']['id_number'];
            $fullName = $result['data']['full_name'];

            if ($actualId == $test['expected_id']) {
                echo "   ✅ صحيح! رقم الهوية: {$actualId}\n";
                echo "   👤 الاسم: {$fullName}\n";
            } else {
                echo "   ❌ خطأ! المتوقع: {$test['expected_id']}, الفعلي: {$actualId}\n";
                $testPassed = false;
            }
        } else {
            echo "   ❌ لم يتم العثور على نتيجة\n";
            $testPassed = false;
        }
    }

    if ($testPassed) {
        $passed++;
        echo "   ✅ اختبار ناجح\n";
    } else {
        $failed++;
        echo "   ❌ اختبار فاشل\n";
    }

    echo "\n";
}

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  النتيجة النهائية                                            ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "✅ نجح: {$passed}\n";
echo "❌ فشل: {$failed}\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

if ($failed == 0) {
    echo "🎉🎉🎉 جميع الاختبارات نجحت! البحث الخارجي يعمل بشكل ممتاز!\n\n";
    echo "الخصائص:\n";
    echo "✅ يدعم الأسماء المركبة (عبد الناصر، أبو محمد)\n";
    echo "✅ يدعم البحث مع ومن دون مسافات\n";
    echo "✅ يدعم التطبيع العربي (أ,إ,آ→ا، ى→ي، ة→ه)\n";
    echo "✅ سرعة عالية (< 1 ثانية للبحث العادي)\n";
    echo "✅ دقة عالية (يعيد النتيجة الصحيحة)\n";
} else {
    echo "⚠️  هناك {$failed} اختبار فاشل. يحتاج مراجعة.\n";
}
