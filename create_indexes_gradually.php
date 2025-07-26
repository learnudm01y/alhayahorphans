<?php
/**
 * إنشاء فهارس تدريجي للاستضافة المشتركة
 * يتم تنفيذه مرة واحدة بعد رفع النظام
 */

require_once 'vendor/autoload.php';

// تحميل Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🚀 بدء إنشاء الفهارس التدريجي للاستضافة المشتركة...\n";
echo "=" . str_repeat("=", 60) . "\n";

// 1. إعدادات أمان
try {
    DB::statement('SET SESSION innodb_lock_wait_timeout = 300');
    DB::statement('SET SESSION lock_wait_timeout = 300');
    echo "✅ تم ضبط إعدادات الانتظار\n";
} catch (Exception $e) {
    echo "⚠️ تحذير: " . $e->getMessage() . "\n";
}

// 2. قائمة الفهارس البسيطة والسريعة
$quickIndexes = [
    [
        'name' => 'idx_id_num_quick',
        'sql' => 'CREATE INDEX idx_id_num_quick ON persons (CI_ID_NUM)',
        'description' => 'فهرس رقم الهوية'
    ],
    [
        'name' => 'idx_first_name_quick',
        'sql' => 'CREATE INDEX idx_first_name_quick ON persons (CI_FIRST_ARB(8))',
        'description' => 'فهرس الاسم الأول (8 أحرف)'
    ],
    [
        'name' => 'idx_father_name_quick',
        'sql' => 'CREATE INDEX idx_father_name_quick ON persons (CI_FATHER_ARB(8))',
        'description' => 'فهرس اسم الأب (8 أحرف)'
    ],
    [
        'name' => 'idx_family_name_quick',
        'sql' => 'CREATE INDEX idx_family_name_quick ON persons (CI_FAMILY_ARB(8))',
        'description' => 'فهرس اسم العائلة (8 أحرف)'
    ]
];

// 3. تنفيذ الفهارس واحد تلو الآخر
foreach ($quickIndexes as $index) {
    echo "\n🔧 إنشاء: " . $index['description'] . "\n";

    try {
        // فحص وجود الفهرس أولاً
        $exists = DB::select("SHOW INDEX FROM persons WHERE Key_name = ?", [$index['name']]);

        if (count($exists) > 0) {
            echo "⚠️ موجود بالفعل: " . $index['name'] . "\n";
            continue;
        }

        $startTime = microtime(true);

        // تنفيذ الاستعلام
        DB::statement($index['sql']);

        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime), 2);

        echo "✅ تم بنجاح في " . $executionTime . " ثانية\n";

        // انتظار قصير بين الفهارس
        sleep(2);

    } catch (Exception $e) {
        echo "❌ فشل: " . $e->getMessage() . "\n";
        echo "💡 نصيحة: قد تحتاج للتواصل مع مقدم الاستضافة لزيادة الوقت المسموح\n";
    }
}

// 4. اختبار الفهارس
echo "\n📊 اختبار الفهارس الجديدة:\n";
echo str_repeat("-", 40) . "\n";

try {
    $indexes = DB::select("SHOW INDEX FROM persons WHERE Key_name LIKE 'idx_%_quick'");
    echo "📈 عدد الفهارس المنشأة: " . count($indexes) . "\n";

    foreach ($indexes as $index) {
        echo "   ✓ " . $index->Key_name . " على العمود: " . $index->Column_name . "\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ في فحص الفهارس: " . $e->getMessage() . "\n";
}

// 5. اختبار سرعة البحث
echo "\n⚡ اختبار سرعة البحث:\n";
echo str_repeat("-", 40) . "\n";

$testQueries = [
    ['name' => 'بحث بالاسم الأول', 'sql' => "SELECT COUNT(*) as count FROM persons WHERE CI_FIRST_ARB LIKE 'محمد%'"],
    ['name' => 'بحث برقم الهوية', 'sql' => "SELECT COUNT(*) as count FROM persons WHERE CI_ID_NUM = '123456789'"],
    ['name' => 'بحث باسم الأب', 'sql' => "SELECT COUNT(*) as count FROM persons WHERE CI_FATHER_ARB LIKE 'أحمد%'"]
];

foreach ($testQueries as $test) {
    try {
        $startTime = microtime(true);
        $result = DB::select($test['sql']);
        $endTime = microtime(true);

        $executionTime = round(($endTime - $startTime) * 1000, 2);
        echo "🎯 " . $test['name'] . ": " . $executionTime . " ms\n";

    } catch (Exception $e) {
        echo "❌ " . $test['name'] . ": خطأ\n";
    }
}

echo "\n🎉 انتهى إنشاء الفهارس!\n";
echo "💡 نصائح لتحسين الأداء أكثر:\n";
echo "   1. استخدم البحث الدقيق (/api/search/exact-only)\n";
echo "   2. قلل عدد النتائج (limit=10 بدلاً من 100)\n";
echo "   3. استخدم الكاش للاستعلامات المتكررة\n";
echo "   4. راقب استخدام الذاكرة والمعالج\n";
