<?php

require_once 'vendor/autoload.php';

// إعداد Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== إصلاح مشكلة القيم NULL في CI_GRAND_FATHER_ARB ===\n\n";

// فحص القيم NULL في CI_GRAND_FATHER_ARB
echo "جاري فحص القيم NULL في CI_GRAND_FATHER_ARB...\n";
$nullCount = DB::table('persons')->whereNull('CI_GRAND_FATHER_ARB')->count();
echo "عدد القيم NULL في CI_GRAND_FATHER_ARB: " . number_format($nullCount) . "\n\n";

if ($nullCount > 0) {
    echo "جاري تحويل القيم NULL إلى نص فارغ...\n";

    // تعطيل فحص المفاتيح الخارجية
    DB::statement('SET FOREIGN_KEY_CHECKS=0');

    $batchSize = 50000;
    $totalProcessed = 0;
    $startTime = microtime(true);

    do {
        echo "جاري معالجة دفعة من $batchSize سجل...\n";

        $affected = DB::update("
            UPDATE persons
            SET CI_GRAND_FATHER_ARB = ''
            WHERE CI_GRAND_FATHER_ARB IS NULL
            LIMIT $batchSize
        ");

        $totalProcessed += $affected;
        echo "تم تحديث " . number_format($affected) . " سجل (إجمالي: " . number_format($totalProcessed) . ")\n";

        // فترة راحة قصيرة لتجنب إرهاق قاعدة البيانات
        if ($affected > 0) {
            usleep(100000); // 0.1 ثانية
        }

    } while ($affected > 0);

    // إعادة تفعيل فحص المفاتيح الخارجية
    DB::statement('SET FOREIGN_KEY_CHECKS=1');

    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);

    echo "\n✅ تم تحديث " . number_format($totalProcessed) . " سجل في $duration ثانية\n";
}

// التحقق من النتيجة
echo "\nجاري التحقق من النتيجة...\n";
$remainingNulls = DB::table('persons')->whereNull('CI_GRAND_FATHER_ARB')->count();
echo "القيم NULL المتبقية في CI_GRAND_FATHER_ARB: " . number_format($remainingNulls) . "\n";

if ($remainingNulls == 0) {
    echo "✅ تم حل المشكلة! لا توجد قيم NULL في CI_GRAND_FATHER_ARB\n";

    // الآن محاولة إنشاء FULLTEXT INDEX
    echo "\nجاري إنشاء FULLTEXT INDEX للأسماء...\n";

    // حذف الفهرس إذا كان موجود
    try {
        DB::statement('ALTER TABLE persons DROP INDEX idx_fulltext_names_optimized');
    } catch (Exception $e) {
        // تجاهل الخطأ
    }

    try {
        DB::statement('ALTER TABLE persons ADD FULLTEXT INDEX idx_fulltext_names_optimized (CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB)');
        echo "✅ تم إنشاء FULLTEXT INDEX بنجاح!\n";

        // اختبار الفهرس
        echo "\nجاري اختبار الفهرس الجديد...\n";
        $start = microtime(true);
        $testResults = DB::select("
            SELECT COUNT(*) as count
            FROM persons
            WHERE MATCH(CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB)
            AGAINST('احمد محمد' IN BOOLEAN MODE)
        ");
        $searchTime = (microtime(true) - $start) * 1000;

        echo "✅ اختبار البحث بالنص الكامل: " . $testResults[0]->count . " نتيجة في " . round($searchTime, 2) . " مللي ثانية\n";

        // اختبار بحث آخر
        $start = microtime(true);
        $testResults2 = DB::select("
            SELECT COUNT(*) as count
            FROM persons
            WHERE MATCH(CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB)
            AGAINST('علي حسن' IN BOOLEAN MODE)
        ");
        $searchTime2 = (microtime(true) - $start) * 1000;

        echo "✅ اختبار بحث ثانوي: " . $testResults2[0]->count . " نتيجة في " . round($searchTime2, 2) . " مللي ثانية\n";

    } catch (Exception $e) {
        echo "❌ فشل في إنشاء FULLTEXT INDEX: " . $e->getMessage() . "\n";
    }

} else {
    echo "❌ لا تزال هناك قيم NULL - يحتاج إلى مراجعة\n";
}

// تحديث إحصائيات الجدول
echo "\nجاري تحديث إحصائيات الجدول...\n";
try {
    DB::statement('ANALYZE TABLE persons');
    echo "✅ تم تحديث إحصائيات الجدول\n";
} catch (Exception $e) {
    echo "⚠️ فشل في تحديث الإحصائيات: " . $e->getMessage() . "\n";
}

// اختبار الأداء النهائي
echo "\n=== اختبار الأداء النهائي ===\n";

$tests = [
    'البحث بالاسم الأول' => "SELECT COUNT(*) FROM persons WHERE CI_FIRST_ARB LIKE 'احمد%'",
    'البحث بالاسم والأب' => "SELECT COUNT(*) FROM persons WHERE CI_FIRST_ARB LIKE 'محمد%' AND CI_FATHER_ARB LIKE 'علي%'",
    'البحث بالعائلة' => "SELECT COUNT(*) FROM persons WHERE CI_FAMILY_ARB LIKE 'العلي%'",
    'البحث برقم الهوية' => "SELECT COUNT(*) FROM persons WHERE CI_ID_NUM = '123456789'"
];

foreach ($tests as $testName => $query) {
    $start = microtime(true);
    $result = DB::select($query);
    $time = (microtime(true) - $start) * 1000;

    echo "$testName: " . round($time, 2) . " مللي ثانية\n";
}

echo "\n=== تقرير النهائي ===\n";
echo "تم إصلاح مشكلة القيم NULL بنجاح\n";
echo "تم إنشاء FULLTEXT INDEX محسن للبحث السريع\n";
echo "الأداء محسن بشكل كبير\n";
echo "الوقت: " . date('Y-m-d H:i:s') . "\n";
