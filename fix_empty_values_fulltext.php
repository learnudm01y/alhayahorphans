<?php

require_once 'vendor/autoload.php';

// إعداد Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== حل مشكلة القيم المكررة الفارغة في FULLTEXT INDEX ===\n\n";

// فحص القيم الفارغة والمكررة
echo "جاري فحص القيم الفارغة في الأعمدة النصية...\n";

$emptyStats = DB::select("
    SELECT
        COUNT(*) as total,
        COUNT(CASE WHEN CI_FIRST_ARB = '' THEN 1 END) as empty_first,
        COUNT(CASE WHEN CI_FATHER_ARB = '' THEN 1 END) as empty_father,
        COUNT(CASE WHEN CI_GRAND_FATHER_ARB = '' THEN 1 END) as empty_grand,
        COUNT(CASE WHEN CI_FIRST_ARB = '' AND CI_FATHER_ARB = '' AND CI_GRAND_FATHER_ARB = '' THEN 1 END) as all_empty,
        COUNT(CASE WHEN CI_FIRST_ARB = '' OR CI_FATHER_ARB = '' OR CI_GRAND_FATHER_ARB = '' THEN 1 END) as any_empty
    FROM persons
");

if (!empty($emptyStats)) {
    $stats = $emptyStats[0];
    echo "إحصائيات القيم الفارغة:\n";
    echo "- إجمالي السجلات: " . number_format($stats->total) . "\n";
    echo "- أسماء أولى فارغة: " . number_format($stats->empty_first) . "\n";
    echo "- أسماء آباء فارغة: " . number_format($stats->empty_father) . "\n";
    echo "- أسماء أجداد فارغة: " . number_format($stats->empty_grand) . "\n";
    echo "- جميع الأعمدة فارغة: " . number_format($stats->all_empty) . "\n";
    echo "- أي عمود فارغ: " . number_format($stats->any_empty) . "\n\n";
}

// الحل: تحديث القيم الفارغة إلى قيم افتراضية فريدة
echo "جاري تحديث القيم الفارغة لتجنب التكرار...\n";

// تعطيل فحص المفاتيح الخارجية
DB::statement('SET FOREIGN_KEY_CHECKS=0');

// تحديث الأسماء الأولى الفارغة
echo "1. تحديث الأسماء الأولى الفارغة...\n";
$updated1 = DB::update("
    UPDATE persons
    SET CI_FIRST_ARB = CONCAT('غير_محدد_', ID)
    WHERE CI_FIRST_ARB = '' OR CI_FIRST_ARB IS NULL
");
echo "تم تحديث " . number_format($updated1) . " سجل للأسماء الأولى\n";

// تحديث أسماء الآباء الفارغة
echo "2. تحديث أسماء الآباء الفارغة...\n";
$updated2 = DB::update("
    UPDATE persons
    SET CI_FATHER_ARB = CONCAT('غير_محدد_', ID)
    WHERE CI_FATHER_ARB = '' OR CI_FATHER_ARB IS NULL
");
echo "تم تحديث " . number_format($updated2) . " سجل لأسماء الآباء\n";

// تحديث أسماء الأجداد الفارغة
echo "3. تحديث أسماء الأجداد الفارغة...\n";
$updated3 = DB::update("
    UPDATE persons
    SET CI_GRAND_FATHER_ARB = CONCAT('غير_محدد_', ID)
    WHERE CI_GRAND_FATHER_ARB = '' OR CI_GRAND_FATHER_ARB IS NULL
");
echo "تم تحديث " . number_format($updated3) . " سجل لأسماء الأجداد\n";

// إعادة تفعيل فحص المفاتيح الخارجية
DB::statement('SET FOREIGN_KEY_CHECKS=1');

echo "\nإجمالي السجلات المحدثة: " . number_format($updated1 + $updated2 + $updated3) . "\n";

// التحقق من النتيجة
echo "\nجاري التحقق من النتيجة...\n";
$newEmptyStats = DB::select("
    SELECT
        COUNT(CASE WHEN CI_FIRST_ARB = '' THEN 1 END) as empty_first,
        COUNT(CASE WHEN CI_FATHER_ARB = '' THEN 1 END) as empty_father,
        COUNT(CASE WHEN CI_GRAND_FATHER_ARB = '' THEN 1 END) as empty_grand
    FROM persons
");

if (!empty($newEmptyStats)) {
    $newStats = $newEmptyStats[0];
    echo "القيم الفارغة المتبقية:\n";
    echo "- أسماء أولى فارغة: " . number_format($newStats->empty_first) . "\n";
    echo "- أسماء آباء فارغة: " . number_format($newStats->empty_father) . "\n";
    echo "- أسماء أجداد فارغة: " . number_format($newStats->empty_grand) . "\n";
}

// الآن محاولة إنشاء FULLTEXT INDEX
echo "\nجاري إنشاء FULLTEXT INDEX بعد حل مشكلة التكرار...\n";

// حذف الفهارس القديمة المتضاربة
$indexesToDrop = ['idx_fulltext_names_optimized', 'idx_fulltext_names', 'idx_fulltext_search'];
foreach ($indexesToDrop as $indexName) {
    try {
        DB::statement("ALTER TABLE persons DROP INDEX $indexName");
        echo "تم حذف الفهرس القديم: $indexName\n";
    } catch (Exception $e) {
        // تجاهل الخطأ
    }
}

// إنشاء فهرس جديد
try {
    echo "\nجاري إنشاء idx_fulltext_names_complete...\n";
    DB::statement('ALTER TABLE persons ADD FULLTEXT INDEX idx_fulltext_names_complete (CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB)');
    echo "✅ تم إنشاء FULLTEXT INDEX بنجاح!\n";

    // اختبار الفهرس
    echo "\nجاري اختبار الفهرس الجديد...\n";

    $tests = [
        'احمد محمد' => "SELECT COUNT(*) as count FROM persons WHERE MATCH(CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB) AGAINST('احمد محمد' IN BOOLEAN MODE)",
        'علي حسن' => "SELECT COUNT(*) as count FROM persons WHERE MATCH(CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB) AGAINST('علي حسن' IN BOOLEAN MODE)",
        'محمد' => "SELECT COUNT(*) as count FROM persons WHERE MATCH(CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB) AGAINST('محمد' IN BOOLEAN MODE)"
    ];

    foreach ($tests as $searchTerm => $query) {
        $start = microtime(true);
        $result = DB::select($query);
        $time = (microtime(true) - $start) * 1000;

        echo "البحث عن '$searchTerm': " . $result[0]->count . " نتيجة في " . round($time, 2) . " مللي ثانية\n";
    }

} catch (Exception $e) {
    echo "❌ فشل في إنشاء FULLTEXT INDEX: " . $e->getMessage() . "\n";

    // محاولة تشخيص المشكلة
    echo "\nجاري تشخيص المشكلة...\n";
    $duplicateCheck = DB::select("
        SELECT CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, COUNT(*) as count
        FROM persons
        GROUP BY CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB
        HAVING COUNT(*) > 1
        LIMIT 5
    ");

    echo "أمثلة على التكرارات:\n";
    foreach ($duplicateCheck as $dup) {
        echo "- '{$dup->CI_FIRST_ARB}' + '{$dup->CI_FATHER_ARB}' + '{$dup->CI_GRAND_FATHER_ARB}': " . $dup->count . " مرة\n";
    }
}

// تحديث إحصائيات الجدول
echo "\nجاري تحديث إحصائيات الجدول...\n";
try {
    DB::statement('ANALYZE TABLE persons');
    echo "✅ تم تحديث إحصائيات الجدول\n";
} catch (Exception $e) {
    echo "⚠️ فشل في تحديث الإحصائيات: " . $e->getMessage() . "\n";
}

// اختبار الأداء النهائي مع الفهارس المحدثة
echo "\n=== اختبار الأداء النهائي ===\n";

$performanceTests = [
    'البحث بالاسم الأول (بدون فهرس)' => "SELECT COUNT(*) FROM persons WHERE CI_FIRST_ARB LIKE 'احمد%'",
    'البحث بالعائلة (مع فهرس)' => "SELECT COUNT(*) FROM persons WHERE CI_FAMILY_ARB LIKE 'العلي%'",
    'البحث برقم الهوية (مع فهرس)' => "SELECT COUNT(*) FROM persons WHERE CI_ID_NUM = '123456789'"
];

foreach ($performanceTests as $testName => $query) {
    $start = microtime(true);
    $result = DB::select($query);
    $time = (microtime(true) - $start) * 1000;

    echo "$testName: " . round($time, 2) . " مللي ثانية\n";
}

echo "\n=== الفهارس النهائية ===\n";
$allIndexes = DB::select("SHOW INDEX FROM persons WHERE Index_type = 'FULLTEXT'");
foreach ($allIndexes as $index) {
    echo "✅ {$index->Key_name} على العمود: {$index->Column_name}\n";
}

echo "\n=== انتهت العملية ===\n";
echo "الوقت: " . date('Y-m-d H:i:s') . "\n";
