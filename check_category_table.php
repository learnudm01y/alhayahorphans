<?php
/**
 * فحص جدول category_of_relations
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n=================================================================\n";
echo "        فحص جدول category_of_relations                          \n";
echo "=================================================================\n\n";

// 1. فحص وجود الجدول
echo "1️⃣ فحص وجود جدول category_of_relations\n";
echo "-----------------------------------------------------------------\n";
try {
    $tableExists = DB::connection('civilregistry')
        ->select("SHOW TABLES LIKE 'category_of_relations'");

    if (count($tableExists) > 0) {
        echo "   ✅ الجدول موجود\n";

        // عدد السجلات
        $count = DB::connection('civilregistry')->table('category_of_relations')->count();
        echo "   📊 عدد السجلات: {$count}\n";

        // عرض أول 10 سجلات
        $records = DB::connection('civilregistry')
            ->table('category_of_relations')
            ->limit(10)
            ->get();

        echo "   📋 أول 10 سجلات:\n";
        foreach ($records as $record) {
            echo "      - ID: {$record->id}, Attribute: {$record->attribute}\n";
        }
    } else {
        echo "   ❌ الجدول غير موجود!\n";
        echo "   💡 الحل: إنشاء الجدول أو تعديل الكود لعدم استخدامه\n";
    }

    echo "\n";
} catch (\Exception $e) {
    echo "   ❌ خطأ: " . $e->getMessage() . "\n\n";
}

// 2. اختبار الـ JOIN
echo "2️⃣ اختبار JOIN بين relations و category_of_relations\n";
echo "-----------------------------------------------------------------\n";
try {
    $testId = '407015692';

    $result = DB::connection('civilregistry')
        ->table('relations as r')
        ->select('r.CF_ID_NUM', 'r.CF_ID_RELATIVE', 'r.CF_RELATIVE_CD', 'cat.attribute')
        ->leftJoin('category_of_relations as cat', 'r.CF_RELATIVE_CD', '=', 'cat.id')
        ->where('r.CF_ID_NUM', $testId)
        ->get();

    echo "   ✅ JOIN نجح\n";
    echo "   📊 عدد النتائج: " . count($result) . "\n";

    foreach ($result as $row) {
        echo "      - CF_RELATIVE_CD: {$row->CF_RELATIVE_CD}, attribute: " . ($row->attribute ?? 'NULL') . "\n";
    }

    echo "\n";
} catch (\Exception $e) {
    echo "   ❌ JOIN فشل: " . $e->getMessage() . "\n\n";
}

echo "=================================================================\n";
echo "✅ انتهى الفحص\n";
echo "=================================================================\n\n";
