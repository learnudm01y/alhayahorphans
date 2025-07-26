<?php

require_once 'vendor/autoload.php';

// إعداد Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== فحص شامل وإصلاح جدول persons ===\n\n";

// فحص شامل لجميع الأعمدة
echo "جاري فحص جميع الأعمدة في الجدول...\n";
$allColumns = DB::select("DESCRIBE persons");

echo "أعمدة الجدول:\n";
$nullableColumns = [];
foreach ($allColumns as $column) {
    $nullable = $column->Null === 'YES' ? 'NULL مسموح' : 'NULL غير مسموح';
    echo "- {$column->Field} ({$column->Type}) - $nullable\n";

    if ($column->Null === 'YES') {
        $nullableColumns[] = $column->Field;
    }
}

echo "\n" . count($nullableColumns) . " عمود يسمح بقيم NULL:\n";
foreach ($nullableColumns as $col) {
    echo "- $col\n";
}

// فحص القيم NULL في جميع الأعمدة المسموحة
echo "\nجاري فحص القيم NULL الفعلية...\n";
$hasNulls = false;
foreach ($nullableColumns as $column) {
    $count = DB::table('persons')->whereNull($column)->count();
    if ($count > 0) {
        echo "- $column: " . number_format($count) . " قيمة NULL\n";
        $hasNulls = true;
    }
}

if (!$hasNulls) {
    echo "✅ لا توجد قيم NULL في أي عمود!\n";
}

// التحقق من الفهارس الحالية
echo "\nجاري فحص الفهارس الحالية...\n";
$indexes = DB::select("SHOW INDEX FROM persons WHERE Index_type = 'FULLTEXT'");

echo "الفهارس النصية الكاملة الموجودة:\n";
if (empty($indexes)) {
    echo "- لا توجد فهارس FULLTEXT\n";
} else {
    foreach ($indexes as $index) {
        echo "- {$index->Key_name} على العمود: {$index->Column_name}\n";
    }
}

// محاولة إنشاء FULLTEXT INDEX محسن
echo "\nجاري إنشاء فهرس FULLTEXT محسن...\n";

// أولاً: تنظيف الفهارس القديمة المتضاربة
$indexesToDrop = ['idx_fulltext_search', 'idx_fulltext_test', 'idx_fulltext_optimized'];
foreach ($indexesToDrop as $indexName) {
    try {
        DB::statement("ALTER TABLE persons DROP INDEX $indexName");
        echo "تم حذف الفهرس القديم: $indexName\n";
    } catch (Exception $e) {
        // تجاهل الخطأ - الفهرس غير موجود
    }
}

// ثانياً: إنشاء فهرس جديد محسن
echo "\nجاري إنشاء فهرس FULLTEXT جديد...\n";

// التحقق من وجود بيانات فارغة أو مشاكل أخرى
$emptyCheck = DB::select("
    SELECT
        COUNT(*) as total,
        COUNT(CASE WHEN CI_FIRST_ARB = '' THEN 1 END) as empty_first,
        COUNT(CASE WHEN CI_FATHER_ARB = '' THEN 1 END) as empty_father,
        COUNT(CASE WHEN CI_FAMILY_ARB = '' THEN 1 END) as empty_family,
        COUNT(CASE WHEN CI_FIRST_ARB IS NULL THEN 1 END) as null_first,
        COUNT(CASE WHEN CI_FATHER_ARB IS NULL THEN 1 END) as null_father,
        COUNT(CASE WHEN CI_FAMILY_ARB IS NULL THEN 1 END) as null_family
    FROM persons
    LIMIT 1
");

if (!empty($emptyCheck)) {
    $stats = $emptyCheck[0];
    echo "إحصائيات البيانات:\n";
    echo "- إجمالي السجلات: " . number_format($stats->total) . "\n";
    echo "- أسماء أولى فارغة: " . number_format($stats->empty_first) . "\n";
    echo "- أسماء آباء فارغة: " . number_format($stats->empty_father) . "\n";
    echo "- أسماء عائلات فارغة: " . number_format($stats->empty_family) . "\n";
    echo "- أسماء أولى NULL: " . number_format($stats->null_first) . "\n";
    echo "- أسماء آباء NULL: " . number_format($stats->null_father) . "\n";
    echo "- أسماء عائلات NULL: " . number_format($stats->null_family) . "\n";
}

// إنشاء فهارس FULLTEXT منفصلة لكل مجموعة
$fulltextIndexes = [
    'idx_fulltext_names' => ['CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_GRAND_FATHER_ARB'],
    'idx_fulltext_family' => ['CI_FAMILY_ARB'],
    'idx_fulltext_mother' => ['MOTHER_NAME1']
];

foreach ($fulltextIndexes as $indexName => $columns) {
    $columnsList = implode(', ', $columns);
    echo "\nجاري إنشاء $indexName على الأعمدة: $columnsList\n";

    try {
        DB::statement("ALTER TABLE persons ADD FULLTEXT INDEX $indexName ($columnsList)");
        echo "✅ تم إنشاء $indexName بنجاح!\n";

        // اختبار الفهرس
        $testQuery = "SELECT COUNT(*) as count FROM persons WHERE MATCH($columnsList) AGAINST('احمد' IN BOOLEAN MODE)";
        $testResult = DB::select($testQuery);
        echo "   اختبار البحث: " . $testResult[0]->count . " نتيجة\n";

    } catch (Exception $e) {
        echo "❌ فشل في إنشاء $indexName: " . $e->getMessage() . "\n";

        // تحليل السبب
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo "   السبب: قيم مكررة في الفهرس\n";
        } elseif (strpos($e->getMessage(), 'key was too long') !== false) {
            echo "   السبب: طول المفتاح يتجاوز الحد المسموح\n";
        }
    }
}

// اختبار الأداء النهائي
echo "\n=== اختبار الأداء النهائي ===\n";

$performanceTests = [
    "البحث بالاسم الأول" => "SELECT COUNT(*) FROM persons WHERE CI_FIRST_ARB LIKE 'احمد%'",
    "البحث بالعائلة" => "SELECT COUNT(*) FROM persons WHERE CI_FAMILY_ARB LIKE 'العلي%'",
    "البحث برقم الهوية" => "SELECT COUNT(*) FROM persons WHERE CI_ID_NUM = '123456789'"
];

foreach ($performanceTests as $testName => $query) {
    $start = microtime(true);
    $result = DB::select($query);
    $time = (microtime(true) - $start) * 1000;

    echo "$testName: " . round($time, 2) . " مللي ثانية\n";
}

// فحص الفهارس النهائية
echo "\n=== الفهارس النهائية ===\n";
$finalIndexes = DB::select("SHOW INDEX FROM persons WHERE Index_type = 'FULLTEXT'");
foreach ($finalIndexes as $index) {
    echo "✅ {$index->Key_name} على العمود: {$index->Column_name}\n";
}

echo "\n=== انتهت العملية ===\n";
echo "الوقت: " . date('Y-m-d H:i:s') . "\n";
