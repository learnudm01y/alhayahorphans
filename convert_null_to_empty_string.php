<?php

require_once 'vendor/autoload.php';

// إعداد Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== تحويل القيم NULL إلى نص فارغ في جدول persons ===\n\n";

// الحصول على معلومات الجدول
echo "جاري فحص بنية الجدول...\n";
$columns = DB::select("DESCRIBE persons");

$stringColumns = [];
foreach ($columns as $column) {
    // التحقق من الأعمدة النصية التي يمكن أن تحتوي على NULL
    if (in_array(strtolower($column->Type), ['varchar(255)', 'text', 'longtext', 'mediumtext']) ||
        strpos(strtolower($column->Type), 'varchar') !== false ||
        strpos(strtolower($column->Type), 'char') !== false) {

        if ($column->Null === 'YES') {
            $stringColumns[] = $column->Field;
        }
    }
}

echo "تم العثور على " . count($stringColumns) . " عمود نصي يمكن أن يحتوي على NULL:\n";
foreach ($stringColumns as $col) {
    echo "- $col\n";
}

// عد القيم NULL في كل عمود
echo "\nجاري فحص القيم NULL في كل عمود...\n";
$nullCounts = [];
foreach ($stringColumns as $column) {
    $count = DB::table('persons')->whereNull($column)->count();
    $nullCounts[$column] = $count;
    echo "- $column: " . number_format($count) . " قيمة NULL\n";
}

$totalNulls = array_sum($nullCounts);
echo "\nإجمالي القيم NULL: " . number_format($totalNulls) . "\n\n";

if ($totalNulls == 0) {
    echo "✅ لا توجد قيم NULL لتحويلها!\n";
    exit;
}

echo "جاري تحويل القيم NULL إلى نص فارغ...\n\n";

// تعطيل فحص المفاتيح الخارجية مؤقتاً لتسريع العملية
DB::statement('SET FOREIGN_KEY_CHECKS=0');

$processedColumns = 0;
$totalProcessed = 0;

foreach ($stringColumns as $column) {
    if ($nullCounts[$column] > 0) {
        echo "جاري معالجة العمود: $column (" . number_format($nullCounts[$column]) . " قيمة NULL)...\n";

        $startTime = microtime(true);

        // تحديث القيم على دفعات لتجنب استنزاف الذاكرة
        $batchSize = 10000;
        $processed = 0;

        do {
            $affected = DB::update("
                UPDATE persons
                SET $column = ''
                WHERE $column IS NULL
                LIMIT $batchSize
            ");

            $processed += $affected;
            $totalProcessed += $affected;

            if ($affected > 0) {
                echo "  تم تحديث " . number_format($affected) . " سجل...\n";
            }

        } while ($affected > 0);

        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);

        echo "  ✅ تم: " . number_format($processed) . " سجل في $duration مللي ثانية\n\n";
        $processedColumns++;
    }
}

// إعادة تفعيل فحص المفاتيح الخارجية
DB::statement('SET FOREIGN_KEY_CHECKS=1');

echo "=== تقرير النتائج ===\n";
echo "الأعمدة المعالجة: $processedColumns\n";
echo "إجمالي السجلات المحدثة: " . number_format($totalProcessed) . "\n";

// التحقق من النتائج
echo "\nجاري التحقق من النتائج...\n";
$remainingNulls = 0;
foreach ($stringColumns as $column) {
    $count = DB::table('persons')->whereNull($column)->count();
    if ($count > 0) {
        echo "⚠️  $column: لا يزال يحتوي على $count قيمة NULL\n";
        $remainingNulls += $count;
    }
}

if ($remainingNulls == 0) {
    echo "✅ تم تحويل جميع القيم NULL إلى نص فارغ بنجاح!\n";
} else {
    echo "⚠️  تبقى " . number_format($remainingNulls) . " قيمة NULL\n";
}

// تحديث إحصائيات الجدول
echo "\nجاري تحديث إحصائيات الجدول...\n";
try {
    DB::statement('ANALYZE TABLE persons');
    echo "✅ تم تحديث إحصائيات الجدول\n";
} catch (Exception $e) {
    echo "⚠️  فشل في تحديث الإحصائيات: " . $e->getMessage() . "\n";
}

// اختبار إنشاء FULLTEXT INDEX الآن
echo "\nجاري اختبار إنشاء FULLTEXT INDEX...\n";
try {
    // حذف الفهرس إذا كان موجود
    try {
        DB::statement('ALTER TABLE persons DROP INDEX idx_fulltext_test');
    } catch (Exception $e) {
        // تجاهل الخطأ
    }

    // إنشاء فهرس جديد
    DB::statement('ALTER TABLE persons ADD FULLTEXT INDEX idx_fulltext_test (CI_FIRST_ARB, CI_FATHER_ARB, CI_FAMILY_ARB)');
    echo "✅ تم إنشاء FULLTEXT INDEX بنجاح!\n";

    // اختبار البحث
    $testResults = DB::select("
        SELECT COUNT(*) as count
        FROM persons
        WHERE MATCH(CI_FIRST_ARB, CI_FATHER_ARB, CI_FAMILY_ARB)
        AGAINST('احمد محمد' IN BOOLEAN MODE)
    ");

    echo "✅ اختبار البحث بالنص الكامل: " . $testResults[0]->count . " نتيجة\n";

} catch (Exception $e) {
    echo "❌ فشل في إنشاء FULLTEXT INDEX: " . $e->getMessage() . "\n";
}

echo "\n=== انتهت العملية ===\n";
echo "الوقت: " . date('Y-m-d H:i:s') . "\n";
