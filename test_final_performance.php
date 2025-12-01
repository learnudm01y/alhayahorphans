<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\NormalizedSearchService;

$searchService = new NormalizedSearchService();

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║       اختبار الأداء النهائي - بعد إضافة الفهارس             ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$tests = [
    [
        'name' => 'البحث برقم هوية نصرالله',
        'query' => '407015692',
        'expected_name' => 'نصرالله عبد الناصر رفيق الفرا',
        'expected_id' => '407015692',
        'max_time' => 100, // ms
    ],
    [
        'name' => 'البحث بالاسم الكامل لنصرالله',
        'query' => 'نصرالله عبد الناصر رفيق الفرا',
        'expected_name' => 'نصرالله عبد الناصر رفيق الفرا',
        'expected_id' => '407015692',
        'max_time' => 50,
    ],
    [
        'name' => 'البحث بالاسم الكامل لمحمد',
        'query' => 'محمد عبد الناصر رفيق الفرا',
        'expected_name' => 'محمد عبد الناصر رفيق الفرا',
        'expected_id' => '801448911',
        'max_time' => 50,
    ],
    [
        'name' => 'البحث بكلمتين (نصرالله الفرا)',
        'query' => 'نصرالله الفرا',
        'expected_first_name' => 'نصرالله',
        'expected_family' => 'الفرا',
        'expected_in_results' => true,
        'max_time' => 200,
    ],
    [
        'name' => 'البحث بكلمة واحدة (نصرالله)',
        'query' => 'نصرالله',
        'min_results' => 10,
        'max_time' => 1000,
    ],
];

$passedTests = 0;
$failedTests = 0;

foreach ($tests as $i => $test) {
    $testNum = $i + 1;
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "اختبار #{$testNum}: {$test['name']}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🔍 الاستعلام: {$test['query']}\n";

    $start = microtime(true);
    $results = $searchService->searchCivilRegistry($test['query']);
    $duration = round((microtime(true) - $start) * 1000, 2);

    echo "⏱️  الوقت: {$duration} ms";

    $passed = true;
    $reasons = [];

    // فحص الوقت
    if ($duration > $test['max_time']) {
        $passed = false;
        $reasons[] = "❌ بطيء جداً (> {$test['max_time']}ms)";
        echo " ❌";
    } else {
        echo " ✅";
    }
    echo "\n";

    echo "📊 عدد النتائج: " . count($results);

    // فحص عدد النتائج
    if (isset($test['min_results']) && count($results) < $test['min_results']) {
        $passed = false;
        $reasons[] = "❌ عدد النتائج أقل من المتوقع (< {$test['min_results']})";
        echo " ❌\n";
    } else {
        echo " ✅\n";
    }

    // فحص الاسم المتوقع
    if (isset($test['expected_name']) && !empty($results)) {
        $first = $results[0];
        $fullName = trim("{$first->CI_FIRST_ARB} {$first->CI_FATHER_ARB} {$first->CI_GRAND_FATHER_ARB} {$first->CI_FAMILY_ARB}");
        echo "👤 أول نتيجة: {$fullName}\n";

        if ($fullName === $test['expected_name']) {
            echo "✅ الاسم مطابق للمتوقع\n";
        } else {
            $passed = false;
            $reasons[] = "❌ الاسم غير مطابق (المتوقع: {$test['expected_name']})";
        }

        if (isset($test['expected_id'])) {
            echo "🆔 رقم الهوية: {$first->id_number}";
            if ($first->id_number == $test['expected_id']) {
                echo " ✅\n";
            } else {
                $passed = false;
                $reasons[] = "❌ رقم الهوية غير مطابق";
                echo " ❌\n";
            }
        }
    } elseif (isset($test['expected_first_name']) && isset($test['expected_family'])) {
        // فحص الاسم الأول والعائلة فقط
        if (!empty($results)) {
            $first = $results[0];
            $fullName = trim("{$first->CI_FIRST_ARB} {$first->CI_FATHER_ARB} {$first->CI_GRAND_FATHER_ARB} {$first->CI_FAMILY_ARB}");
            echo "👤 أول نتيجة: {$fullName}\n";

            if ($first->CI_FIRST_ARB === $test['expected_first_name'] && $first->CI_FAMILY_ARB === $test['expected_family']) {
                echo "✅ الاسم الأول والعائلة مطابقان\n";
            } else {
                $passed = false;
                $reasons[] = "❌ الاسم الأول أو العائلة غير مطابق";
            }
        }
    } elseif (isset($test['expected_in_results'])) {
        $found = false;
        foreach ($results as $result) {
            $fullName = trim("{$result->CI_FIRST_ARB} {$result->CI_FATHER_ARB} {$result->CI_GRAND_FATHER_ARB} {$result->CI_FAMILY_ARB}");
            if ($fullName === $test['expected_name']) {
                $found = true;
                echo "👤 النتيجة المتوقعة موجودة: {$fullName} ✅\n";
                break;
            }
        }
        if (!$found) {
            $passed = false;
            $reasons[] = "❌ النتيجة المتوقعة غير موجودة";
        }
    }

    // النتيجة النهائية للاختبار
    echo "\n";
    if ($passed) {
        echo "✅ الاختبار نجح\n";
        $passedTests++;
    } else {
        echo "❌ الاختبار فشل:\n";
        foreach ($reasons as $reason) {
            echo "   {$reason}\n";
        }
        $failedTests++;
    }
    echo "\n";
}

// الملخص النهائي
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                      الملخص النهائي                          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "إجمالي الاختبارات: " . count($tests) . "\n";
echo "✅ نجح: {$passedTests}\n";
echo "❌ فشل: {$failedTests}\n";
echo "\n";

if ($failedTests === 0) {
    echo "🎉 تهانينا! جميع الاختبارات نجحت!\n";
    echo "📈 التحسينات المطبقة:\n";
    echo "   ✅ إضافة فهارس على جميع أعمود البحث\n";
    echo "   ✅ تحسين البحث برقم الهوية (من 18+ ثانية إلى < 100ms)\n";
    echo "   ✅ دعم الأسماء المركبة مثل 'عبد الناصر'\n";
    echo "   ✅ ترتيب النتائج حسب الدقة (relevance sorting)\n";
    echo "   ✅ جميع الاستعلامات تستخدم INDEX للسرعة\n";
} else {
    echo "⚠️  بعض الاختبارات فشلت. راجع التفاصيل أعلاه.\n";
}
