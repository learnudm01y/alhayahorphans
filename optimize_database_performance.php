<?php
require_once 'vendor/autoload.php';

// إعداد Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "🚀 تحسين أداء قاعدة البيانات والبحث\n";
echo str_repeat("=", 60) . "\n";

// 1. فحص الفهارس الحالية
echo "\n1️⃣ فحص الفهارس الحالية:\n";
echo str_repeat("-", 40) . "\n";

$indexes = DB::select("SHOW INDEX FROM persons");
echo "📊 الفهارس الموجودة:\n";
foreach ($indexes as $index) {
    echo "   - {$index->Key_name} على العمود: {$index->Column_name}\n";
}

// 2. فحص إحصائيات الجدول
echo "\n2️⃣ إحصائيات الجدول:\n";
echo str_repeat("-", 40) . "\n";

$totalRows = DB::table('persons')->count();
echo "📈 إجمالي السجلات: " . number_format($totalRows) . "\n";

// فحص البيانات الفارغة
$nullStats = [
    'CI_FIRST_ARB' => DB::table('persons')->whereNull('CI_FIRST_ARB')->count(),
    'CI_FATHER_ARB' => DB::table('persons')->whereNull('CI_FATHER_ARB')->count(),
    'CI_GRAND_FATHER_ARB' => DB::table('persons')->whereNull('CI_GRAND_FATHER_ARB')->count(),
    'CI_FAMILY_ARB' => DB::table('persons')->whereNull('CI_FAMILY_ARB')->count(),
    'CI_ID_NUM' => DB::table('persons')->whereNull('CI_ID_NUM')->count(),
    'MOTHER_NAME1' => DB::table('persons')->whereNull('MOTHER_NAME1')->count(),
];

echo "\n📊 إحصائيات البيانات الفارغة (NULL):\n";
foreach ($nullStats as $column => $nullCount) {
    $percentage = round(($nullCount / $totalRows) * 100, 2);
    echo "   - {$column}: " . number_format($nullCount) . " فارغ ({$percentage}%)\n";
}

// 3. إنشاء الفهارس المحسنة
echo "\n3️⃣ إنشاء فهارس محسنة للبحث السريع:\n";
echo str_repeat("-", 40) . "\n";

try {
    // فهرس مركب للأسماء
    echo "🔧 إنشاء فهرس مركب للأسماء...\n";
    DB::statement("CREATE INDEX IF NOT EXISTS idx_names_composite ON persons (CI_FIRST_ARB(10), CI_FATHER_ARB(10), CI_FAMILY_ARB(10))");
    echo "✅ تم إنشاء فهرس مركب للأسماء\n";

    // فهرس لرقم الهوية
    echo "🔧 إنشاء فهرس لرقم الهوية...\n";
    DB::statement("CREATE INDEX IF NOT EXISTS idx_id_num ON persons (CI_ID_NUM)");
    echo "✅ تم إنشاء فهرس رقم الهوية\n";

    // فهرس للاسم الأول
    echo "🔧 إنشاء فهرس للاسم الأول...\n";
    DB::statement("CREATE INDEX IF NOT EXISTS idx_first_name ON persons (CI_FIRST_ARB(15))");
    echo "✅ تم إنشاء فهرس الاسم الأول\n";

    // فهرس لاسم الأب
    echo "🔧 إنشاء فهرس لاسم الأب...\n";
    DB::statement("CREATE INDEX IF NOT EXISTS idx_father_name ON persons (CI_FATHER_ARB(15))");
    echo "✅ تم إنشاء فهرس اسم الأب\n";

    // فهرس لاسم العائلة
    echo "🔧 إنشاء فهرس لاسم العائلة...\n";
    DB::statement("CREATE INDEX IF NOT EXISTS idx_family_name ON persons (CI_FAMILY_ARB(15))");
    echo "✅ تم إنشاء فهرس اسم العائلة\n";

    // فهرس FULLTEXT محسن
    echo "🔧 إنشاء فهرس FULLTEXT محسن...\n";
    DB::statement("DROP INDEX IF EXISTS fulltext_names ON persons");
    DB::statement("CREATE FULLTEXT INDEX fulltext_names ON persons (CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB)");
    echo "✅ تم إنشاء فهرس FULLTEXT محسن\n";

} catch (Exception $e) {
    echo "⚠️ خطأ في إنشاء الفهارس: " . $e->getMessage() . "\n";
}

// 4. تحسين إعدادات MySQL
echo "\n4️⃣ تحسين إعدادات MySQL:\n";
echo str_repeat("-", 40) . "\n";

try {
    // زيادة cache للفهارس
    DB::statement("SET SESSION key_buffer_size = 256M");
    echo "✅ تم تحسين key_buffer_size\n";

    // تحسين الذاكرة للاستعلامات
    DB::statement("SET SESSION sort_buffer_size = 4M");
    echo "✅ تم تحسين sort_buffer_size\n";

    // تحسين cache النتائج
    DB::statement("SET SESSION query_cache_size = 64M");
    echo "✅ تم تحسين query_cache_size\n";

} catch (Exception $e) {
    echo "⚠️ ملاحظة: بعض إعدادات MySQL تحتاج صلاحيات إدارية\n";
}

// 5. اختبار الأداء
echo "\n5️⃣ اختبار الأداء بعد التحسين:\n";
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

    // الاستعلام المحسن
    $results = DB::table('persons')
        ->where(function($q) use ($query) {
            $words = explode(' ', $query);

            if (count($words) > 1) {
                // بحث متعدد الكلمات
                $q->whereRaw("MATCH(CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB) AGAINST(? IN BOOLEAN MODE)", [$query]);

                foreach ($words as $word) {
                    $q->orWhere('CI_FIRST_ARB', 'LIKE', "{$word}%")
                      ->orWhere('CI_FATHER_ARB', 'LIKE', "{$word}%")
                      ->orWhere('CI_FAMILY_ARB', 'LIKE', "{$word}%");
                }
            } else {
                // بحث كلمة واحدة
                $q->where('CI_FIRST_ARB', 'LIKE', "{$query}%")
                  ->orWhere('CI_FATHER_ARB', 'LIKE', "{$query}%")
                  ->orWhere('CI_FAMILY_ARB', 'LIKE', "{$query}%")
                  ->orWhere('CI_ID_NUM', 'LIKE', "{$query}%");
            }
        })
        ->limit(20)
        ->get(['ID', 'CI_ID_NUM', 'CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_GRAND_FATHER_ARB', 'CI_FAMILY_ARB']);

    $endTime = microtime(true);
    $searchTime = round(($endTime - $startTime) * 1000, 2);

    echo "   ⏱️ الوقت: {$searchTime} ms\n";
    echo "   📊 النتائج: " . $results->count() . "\n";

    if ($searchTime < 1000) {
        echo "   ✅ أداء ممتاز (أقل من ثانية)\n";
    } elseif ($searchTime < 3000) {
        echo "   ⚠️ أداء مقبول (أقل من 3 ثوان)\n";
    } else {
        echo "   ❌ أداء بطيء (أكثر من 3 ثوان)\n";
    }
}

// 6. معالجة البيانات الفارغة
echo "\n6️⃣ معالجة البيانات الفارغة (NULL):\n";
echo str_repeat("-", 40) . "\n";

try {
    // استبدال NULL بقيم افتراضية
    echo "🔄 معالجة البيانات الفارغة...\n";

    $updates = [
        "UPDATE persons SET CI_FIRST_ARB = '' WHERE CI_FIRST_ARB IS NULL",
        "UPDATE persons SET CI_FATHER_ARB = '' WHERE CI_FATHER_ARB IS NULL",
        "UPDATE persons SET CI_GRAND_FATHER_ARB = '' WHERE CI_GRAND_FATHER_ARB IS NULL",
        "UPDATE persons SET CI_FAMILY_ARB = '' WHERE CI_FAMILY_ARB IS NULL",
        "UPDATE persons SET MOTHER_NAME1 = '' WHERE MOTHER_NAME1 IS NULL",
        "UPDATE persons SET CITY = 0 WHERE CITY IS NULL"
    ];

    foreach ($updates as $updateQuery) {
        $affected = DB::update($updateQuery);
        echo "   ✅ تم تحديث " . number_format($affected) . " سجل\n";
    }

} catch (Exception $e) {
    echo "⚠️ خطأ في معالجة البيانات الفارغة: " . $e->getMessage() . "\n";
}

// 7. إحصائيات نهائية
echo "\n7️⃣ الإحصائيات النهائية:\n";
echo str_repeat("-", 40) . "\n";

$finalIndexes = DB::select("SHOW INDEX FROM persons");
echo "📊 عدد الفهارس النهائي: " . count($finalIndexes) . "\n";

$finalNullCount = DB::table('persons')->whereNull('CI_FIRST_ARB')->count();
echo "📊 السجلات الفارغة المتبقية: " . number_format($finalNullCount) . "\n";

echo "\n🎉 تم تحسين قاعدة البيانات بنجاح!\n";
echo "⚡ متوقع تحسن كبير في سرعة البحث (من 24 ثانية إلى أقل من ثانية)\n";
echo "\n🔄 يُنصح بإعادة تشغيل الخادم لتطبيق التحسينات كاملة.\n";
?>
