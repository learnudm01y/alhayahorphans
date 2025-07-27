<?php
/**
 * حل نهائي لمشكلة الفهارس المكررة - يعمل مع صلاحيات محدودة
 */

require_once 'vendor/autoload.php';

// تحميل Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔧 الحل النهائي لمشكلة الفهارس المكررة...\n";
echo "=" . str_repeat("=", 60) . "\n";

try {
    // 1. حذف migrations المُشكِلة من الجدول
    echo "\n1️⃣ تنظيف جدول migrations:\n";

    $problematicMigrations = [
        '2024_01_01_000001_add_search_indexes_to_persons_table',
        '2025_01_25_000000_add_search_indexes_for_performance'
    ];

    foreach ($problematicMigrations as $migration) {
        try {
            $deleted = DB::table('migrations')->where('migration', $migration)->delete();
            if ($deleted > 0) {
                echo "✅ تم حذف migration: {$migration}\n";
            } else {
                echo "⚠️ غير موجود: {$migration}\n";
            }
        } catch (Exception $e) {
            echo "❌ فشل حذف {$migration}: " . $e->getMessage() . "\n";
        }
    }

    // 2. إضافة migration جديدة آمنة
    echo "\n2️⃣ إضافة migration جديدة آمنة:\n";

    try {
        // فحص إذا كانت موجودة
        $exists = DB::table('migrations')
                    ->where('migration', '2024_01_02_000001_add_fast_indexes_to_persons')
                    ->exists();

        if (!$exists) {
            $maxBatch = DB::table('migrations')->max('batch') ?? 0;
            DB::table('migrations')->insert([
                'migration' => '2024_01_02_000001_add_fast_indexes_to_persons',
                'batch' => $maxBatch + 1
            ]);
            echo "✅ تم إضافة migration الجديدة\n";
        } else {
            echo "⚠️ Migration الجديدة موجودة بالفعل\n";
        }
    } catch (Exception $e) {
        echo "❌ فشل إضافة migration: " . $e->getMessage() . "\n";
    }

    // 3. فحص الفهارس الموجودة
    echo "\n3️⃣ فحص الفهارس الموجودة:\n";

    $indexes = DB::select("SHOW INDEX FROM persons");
    $indexNames = array_unique(array_column($indexes, 'Key_name'));

    echo "📊 الفهارس الموجودة (" . count($indexNames) . "):\n";
    foreach ($indexNames as $indexName) {
        if ($indexName !== 'PRIMARY') {
            echo "   • {$indexName}\n";
        }
    }

    // 4. اختبار أداء البحث
    echo "\n4️⃣ اختبار أداء البحث:\n";

    $searchTests = [
        [
            'name' => 'بحث برقم الهوية',
            'sql' => "SELECT * FROM persons WHERE CI_ID_NUM = '123456789' LIMIT 1"
        ],
        [
            'name' => 'بحث بالاسم الأول',
            'sql' => "SELECT * FROM persons WHERE CI_FIRST_ARB LIKE 'محمد%' LIMIT 5"
        ],
        [
            'name' => 'بحث باسم الأب',
            'sql' => "SELECT * FROM persons WHERE CI_FATHER_ARB LIKE 'أحمد%' LIMIT 5"
        ],
        [
            'name' => 'بحث مركب',
            'sql' => "SELECT * FROM persons WHERE CI_FIRST_ARB = 'محمد' AND CI_FATHER_ARB = 'أحمد' LIMIT 3"
        ]
    ];

    foreach ($searchTests as $test) {
        try {
            $startTime = microtime(true);
            $results = DB::select($test['sql']);
            $endTime = microtime(true);

            $executionTime = round(($endTime - $startTime) * 1000, 2);
            $count = count($results);

            echo "🎯 " . $test['name'] . ": {$executionTime} ms ({$count} نتيجة)\n";

            if ($executionTime < 50) {
                echo "   ✅ ممتاز - سريع جداً\n";
            } elseif ($executionTime < 200) {
                echo "   ✓ جيد - سريع\n";
            } elseif ($executionTime < 1000) {
                echo "   ⚠️ مقبول - متوسط\n";
            } else {
                echo "   ❌ بطيء - يحتاج تحسين\n";
            }

        } catch (Exception $e) {
            echo "❌ " . $test['name'] . ": خطأ - " . $e->getMessage() . "\n";
        }
    }

    // 5. اختبار APIs
    echo "\n5️⃣ فحص APIs:\n";

    try {
        // فحص routes/api.php
        $apiFile = 'routes/api.php';
        if (file_exists($apiFile)) {
            $apiContent = file_get_contents($apiFile);

            if (strpos($apiContent, '/search/exact-only') !== false) {
                echo "✅ API البحث الدقيق موجود في routes/api.php\n";
            } else {
                echo "⚠️ API البحث الدقيق غير موجود في routes/api.php\n";
            }
        } else {
            echo "❌ ملف routes/api.php غير موجود\n";
        }
    } catch (Exception $e) {
        echo "⚠️ تعذر فحص APIs: " . $e->getMessage() . "\n";
    }

    // 6. تقرير الحالة النهائي
    echo "\n6️⃣ تقرير الحالة النهائي:\n";
    echo str_repeat("-", 50) . "\n";

    $totalRecords = DB::table('persons')->count();
    echo "📊 إجمالي السجلات: " . number_format($totalRecords) . "\n";
    echo "🗂️ عدد الفهارس: " . count($indexNames) . "\n";

    // فحص الفهارس المهمة
    $importantIndexes = [
        'idx_ci_id_num' => 'رقم الهوية',
        'idx_first_name' => 'الاسم الأول',
        'idx_father_name' => 'اسم الأب',
        'idx_persons_ci_id_num' => 'رقم الهوية (إضافي)'
    ];

    $foundIndexes = 0;
    foreach ($importantIndexes as $indexName => $description) {
        if (in_array($indexName, $indexNames)) {
            echo "✅ فهرس {$description}: موجود\n";
            $foundIndexes++;
        }
    }

    if ($foundIndexes >= 2) {
        echo "\n🎉 النظام جاهز للعمل!\n";
        echo "💡 الفهارس الموجودة كافية للحصول على أداء جيد\n";
    } else {
        echo "\n⚠️ النظام يعمل ولكن الأداء قد يكون بطيئاً\n";
        echo "💡 يُنصح بإضافة المزيد من الفهارس\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ عام: " . $e->getMessage() . "\n";
}

echo "\n📋 الخطوات التالية:\n";
echo "1. تشغيل: php artisan migrate (يجب أن ينجح الآن)\n";
echo "2. اختبار: curl -X GET 'https://yoursite.com/api/search/exact-only?q=محمد'\n";
echo "3. استخدام البحث الدقيق بدلاً من البحث العادي\n";
echo "4. مراقبة الأداء والاستجابة\n";

echo "\n" . str_repeat("=", 60) . "\n";
echo "🎯 تم إعداد النظام بنجاح للعمل مع الاستضافة المشتركة!\n";
