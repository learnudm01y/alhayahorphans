<?php
/**
 * حل مشكلة الفهارس المكررة على الاستضافة
 */

require_once 'vendor/autoload.php';

// تحميل Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔧 حل مشكلة الفهارس المكررة على الاستضافة...\n";
echo "=" . str_repeat("=", 60) . "\n";

try {
    // 1. فحص الفهارس الموجودة
    echo "\n1️⃣ فحص الفهارس الموجودة:\n";
    $indexes = DB::select("SHOW INDEX FROM persons");

    $indexNames = [];
    foreach ($indexes as $index) {
        $indexNames[] = $index->Key_name;
    }

    $uniqueIndexes = array_unique($indexNames);
    echo "📊 الفهارس الموجودة: " . implode(', ', $uniqueIndexes) . "\n";

    // 2. حذف الفهارس المكررة/القديمة
    echo "\n2️⃣ حذف الفهارس القديمة المكررة:\n";

    $oldIndexesToDrop = [
        'idx_persons_ci_id_num',
        'idx_persons_ci_first_arb',
        'idx_persons_ci_father_arb',
        'idx_persons_ci_family_arb',
        'idx_persons_gender_city',
        'idx_persons_full_name_search'
    ];

    foreach ($oldIndexesToDrop as $indexName) {
        try {
            if (in_array($indexName, $uniqueIndexes)) {
                DB::statement("DROP INDEX {$indexName} ON persons");
                echo "✅ تم حذف: {$indexName}\n";
            } else {
                echo "⚠️ غير موجود: {$indexName}\n";
            }
        } catch (Exception $e) {
            echo "❌ فشل حذف {$indexName}: " . $e->getMessage() . "\n";
        }
    }

    // 3. إنشاء الفهارس الجديدة المحسنة
    echo "\n3️⃣ إنشاء الفهارس الجديدة المحسنة:\n";

    $newIndexes = [
        [
            'name' => 'idx_id_num_new',
            'sql' => 'CREATE INDEX idx_id_num_new ON persons (CI_ID_NUM)',
            'description' => 'فهرس رقم الهوية الجديد'
        ],
        [
            'name' => 'idx_first_name_new',
            'sql' => 'CREATE INDEX idx_first_name_new ON persons (CI_FIRST_ARB(8))',
            'description' => 'فهرس الاسم الأول الجديد'
        ],
        [
            'name' => 'idx_father_name_new',
            'sql' => 'CREATE INDEX idx_father_name_new ON persons (CI_FATHER_ARB(8))',
            'description' => 'فهرس اسم الأب الجديد'
        ],
        [
            'name' => 'idx_family_name_new',
            'sql' => 'CREATE INDEX idx_family_name_new ON persons (CI_FAMILY_ARB(8))',
            'description' => 'فهرس اسم العائلة الجديد'
        ]
    ];

    foreach ($newIndexes as $index) {
        try {
            // فحص وجود الفهرس أولاً
            $exists = DB::select("SHOW INDEX FROM persons WHERE Key_name = ?", [$index['name']]);

            if (count($exists) > 0) {
                echo "⚠️ موجود بالفعل: " . $index['name'] . "\n";
                continue;
            }

            $startTime = microtime(true);
            DB::statement($index['sql']);
            $endTime = microtime(true);

            $executionTime = round(($endTime - $startTime), 2);
            echo "✅ " . $index['description'] . " - " . $executionTime . " ثانية\n";

        } catch (Exception $e) {
            echo "❌ فشل " . $index['description'] . ": " . $e->getMessage() . "\n";
        }
    }

    // 4. تحديث جدول migrations لحل المشكلة
    echo "\n4️⃣ تحديث جدول migrations:\n";

    try {
        // حذف migration المُشكِلة
        DB::table('migrations')
          ->where('migration', '2024_01_01_000001_add_search_indexes_to_persons_table')
          ->delete();

        echo "✅ تم حذف migration المُشكِلة من الجدول\n";

        // إضافة migration الجديدة
        DB::table('migrations')->insert([
            'migration' => '2024_01_02_000001_add_fast_indexes_to_persons',
            'batch' => DB::table('migrations')->max('batch') + 1
        ]);

        echo "✅ تم إضافة migration الجديدة\n";

    } catch (Exception $e) {
        echo "⚠️ تحذير في تحديث migrations: " . $e->getMessage() . "\n";
    }

    // 5. اختبار الفهارس الجديدة
    echo "\n5️⃣ اختبار الفهارس الجديدة:\n";

    $testQueries = [
        ['name' => 'بحث برقم الهوية', 'sql' => "SELECT COUNT(*) as count FROM persons WHERE CI_ID_NUM = '123456789'"],
        ['name' => 'بحث بالاسم الأول', 'sql' => "SELECT COUNT(*) as count FROM persons WHERE CI_FIRST_ARB LIKE 'محمد%'"],
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
            echo "❌ " . $test['name'] . ": خطأ - " . $e->getMessage() . "\n";
        }
    }

    echo "\n🎉 تم حل مشكلة الفهارس المكررة بنجاح!\n";
    echo "💡 الآن يمكنك تشغيل: php artisan migrate بأمان\n";

} catch (Exception $e) {
    echo "❌ خطأ عام: " . $e->getMessage() . "\n";
}

echo "\n📋 الخطوات التالية:\n";
echo "1. تشغيل: php artisan migrate\n";
echo "2. اختبار APIs: /api/search/exact-only?q=محمد\n";
echo "3. مراقبة الأداء والسرعة\n";
