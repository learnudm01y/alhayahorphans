<?php
require_once 'vendor/autoload.php';

// إعداد Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔥 إصلاح وتحسين الفهارس - مرحلة متقدمة\n";
echo str_repeat("=", 60) . "\n";

// 1. حذف الفهارس المكررة والغير مستخدمة
echo "\n1️⃣ تنظيف الفهارس المكررة:\n";
echo str_repeat("-", 40) . "\n";

$indexesToDrop = [
    'idx_ci_first_ultra',
    'idx_ci_father_ultra',
    'idx_ci_family_ultra',
    'idx_id_desc_ultra',
    'idx_names_combo_ultra',
    'idx_ci_id_simple',
    'idx_ci_id_smart_search',
    'idx_persons_id_first_name_fast',
    'idx_persons_three_names_fast',
    'idx_persons_demographic_search',
    'idx_persons_sorting_optimized',
    'idx_fast_search_composite',
    'idx_id_search_composite',
    'idx_family_search_composite',
    'idx_persons_full_name',
    'idx_persons_name_city',
    'idx_persons_gender_city',
    'idx_ft_first_name',
    'idx_ft_father_name',
    'idx_ft_grand_father_name',
    'idx_ft_family_name'
];

foreach ($indexesToDrop as $indexName) {
    try {
        DB::statement("DROP INDEX IF EXISTS {$indexName} ON persons");
        echo "✅ حذف الفهرس: {$indexName}\n";
    } catch (Exception $e) {
        echo "⚠️ لم يتم العثور على: {$indexName}\n";
    }
}

// 2. إنشاء فهرس FULLTEXT صحيح
echo "\n2️⃣ إنشاء فهرس FULLTEXT محسن:\n";
echo str_repeat("-", 40) . "\n";

try {
    // حذف فهارس FULLTEXT القديمة
    DB::statement("DROP INDEX IF EXISTS fulltext_names ON persons");
    DB::statement("DROP INDEX IF EXISTS idx_fulltext_family ON persons");
    DB::statement("DROP INDEX IF EXISTS idx_fulltext_mother ON persons");

    // إنشاء فهرس FULLTEXT جديد ومحسن
    DB::statement("ALTER TABLE persons ADD FULLTEXT(CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB)");
    echo "✅ تم إنشاء فهرس FULLTEXT محسن للأسماء\n";

    DB::statement("ALTER TABLE persons ADD FULLTEXT(MOTHER_NAME1)");
    echo "✅ تم إنشاء فهرس FULLTEXT لاسم الأم\n";

} catch (Exception $e) {
    echo "⚠️ ملاحظة FULLTEXT: " . $e->getMessage() . "\n";
}

// 3. إنشاء فهارس مُحسنة فقط (الضرورية)
echo "\n3️⃣ إنشاء الفهارس الأساسية المحسنة:\n";
echo str_repeat("-", 40) . "\n";

$essentialIndexes = [
    "CREATE INDEX idx_search_first_prefix ON persons (CI_FIRST_ARB(8))",
    "CREATE INDEX idx_search_father_prefix ON persons (CI_FATHER_ARB(8))",
    "CREATE INDEX idx_search_family_prefix ON persons (CI_FAMILY_ARB(8))",
    "CREATE INDEX idx_search_composite_names ON persons (CI_FIRST_ARB(5), CI_FATHER_ARB(5), CI_FAMILY_ARB(5))"
];

foreach ($essentialIndexes as $indexQuery) {
    try {
        DB::statement($indexQuery);
        echo "✅ " . substr($indexQuery, 0, 50) . "...\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "⚠️ الفهرس موجود بالفعل\n";
        } else {
            echo "❌ خطأ: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n4️⃣ اختبار الأداء المحسن:\n";
echo str_repeat("-", 40) . "\n";

$testQueries = [
    'سعاد',
    'محمد',
    'سعاد صلاح',
    'سعاد صلاح رضوان بلال'
];

foreach ($testQueries as $index => $query) {
    echo "\n🔍 اختبار " . ($index + 1) . ": '$query'\n";

    $startTime = microtime(true);

    // استعلام محسن بدون FULLTEXT (لتجنب الأخطاء)
    $words = array_filter(explode(' ', trim($query)), function($word) {
        return strlen($word) >= 2;
    });

    $results = DB::table('persons')
        ->where(function($q) use ($words, $query) {
            if (count($words) > 1) {
                // بحث بالنص الكامل أولاً
                $q->whereRaw("CONCAT(COALESCE(CI_FIRST_ARB, ''), ' ', COALESCE(CI_FATHER_ARB, ''), ' ', COALESCE(CI_FAMILY_ARB, '')) LIKE ?", ["%{$query}%"]);

                // ثم بحث بكل كلمة بادئة
                foreach ($words as $word) {
                    $q->orWhere('CI_FIRST_ARB', 'LIKE', "{$word}%")
                      ->orWhere('CI_FATHER_ARB', 'LIKE', "{$word}%")
                      ->orWhere('CI_FAMILY_ARB', 'LIKE', "{$word}%");
                }
            } else {
                // بحث كلمة واحدة كبادئة (أسرع)
                $q->where('CI_FIRST_ARB', 'LIKE', "{$query}%")
                  ->orWhere('CI_FATHER_ARB', 'LIKE', "{$query}%")
                  ->orWhere('CI_FAMILY_ARB', 'LIKE', "{$query}%")
                  ->orWhere('CI_ID_NUM', 'LIKE', "{$query}%");
            }
        })
        ->orderBy('ID', 'DESC')
        ->limit(20)
        ->get(['ID', 'CI_ID_NUM', 'CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_GRAND_FATHER_ARB', 'CI_FAMILY_ARB']);

    $endTime = microtime(true);
    $searchTime = round(($endTime - $startTime) * 1000, 2);

    echo "   ⏱️ الوقت: {$searchTime} ms\n";
    echo "   📊 النتائج: " . $results->count() . "\n";

    if ($searchTime < 500) {
        echo "   🚀 أداء ممتاز (أقل من 0.5 ثانية)\n";
    } elseif ($searchTime < 1000) {
        echo "   ✅ أداء جيد (أقل من ثانية)\n";
    } elseif ($searchTime < 3000) {
        echo "   ⚠️ أداء مقبول (أقل من 3 ثوان)\n";
    } else {
        echo "   ❌ أداء بطيء (أكثر من 3 ثوان)\n";
    }

    // عرض أول نتيجة للتأكد
    if ($results->count() > 0) {
        $first = $results->first();
        $fullName = trim($first->CI_FIRST_ARB . ' ' . $first->CI_FATHER_ARB . ' ' . $first->CI_FAMILY_ARB);
        echo "   🎯 أول نتيجة: {$fullName}\n";
    }
}

// 5. تحسين إعدادات الجلسة
echo "\n5️⃣ تحسين إعدادات MySQL للجلسة:\n";
echo str_repeat("-", 40) . "\n";

try {
    $optimizations = [
        "SET SESSION tmp_table_size = 256M",
        "SET SESSION max_heap_table_size = 256M",
        "SET SESSION sort_buffer_size = 8M",
        "SET SESSION join_buffer_size = 8M",
        "SET SESSION read_buffer_size = 2M"
    ];

    foreach ($optimizations as $optimization) {
        DB::statement($optimization);
        echo "✅ " . $optimization . "\n";
    }

} catch (Exception $e) {
    echo "⚠️ بعض التحسينات تحتاج صلاحيات أعلى\n";
}

echo "\n6️⃣ الإحصائيات النهائية:\n";
echo str_repeat("-", 40) . "\n";

$finalIndexes = DB::select("SHOW INDEX FROM persons");
echo "📊 عدد الفهارس النهائي: " . count($finalIndexes) . "\n";

$tableSize = DB::select("SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'DB Size in MB' FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'persons'");
if (!empty($tableSize)) {
    echo "📊 حجم الجدول: " . $tableSize[0]->{'DB Size in MB'} . " MB\n";
}

echo "\n🎉 تم تحسين الأداء بنجاح!\n";
echo "⚡ متوقع أداء أفضل بكثير الآن\n";
echo "🔄 يُفضل إعادة تشغيل خادم قاعدة البيانات إذا أمكن\n";
?>
