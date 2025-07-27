<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "إنشاء فهارس قاعدة البيانات للبحث السريع...\n";
echo str_repeat("=", 60) . "\n";

try {
    $connection = \Illuminate\Support\Facades\DB::connection('civilregistry');

    // فحص الفهارس الموجودة
    echo "فحص الفهارس الموجودة...\n";
    $indexes = $connection->select("SHOW INDEX FROM persons");
    $existingIndexes = array_column($indexes, 'Key_name');

    echo "الفهارس الموجودة: " . implode(', ', array_unique($existingIndexes)) . "\n\n";

    // إنشاء فهارس جديدة
    $indexesToCreate = [
        'idx_ci_id_num' => 'CI_ID_NUM',
        'idx_ci_first_arb' => 'CI_FIRST_ARB',
        'idx_ci_father_arb' => 'CI_FATHER_ARB',
        'idx_ci_family_arb' => 'CI_FAMILY_ARB',
        'idx_mother_name' => 'MOTHER_NAME1',
        'idx_sex_cd' => 'CI_SEX_CD',
        'idx_birth_dt' => 'CI_BIRTH_DT',
        'idx_dead_dt' => 'CI_DEAD_DT'
    ];

    foreach ($indexesToCreate as $indexName => $column) {
        if (!in_array($indexName, $existingIndexes)) {
            try {
                echo "إنشاء فهرس: $indexName على عمود $column...";
                $connection->statement("CREATE INDEX $indexName ON persons($column)");
                echo " ✅ تم\n";
            } catch (\Exception $e) {
                echo " ❌ خطأ: " . $e->getMessage() . "\n";
            }
        } else {
            echo "فهرس $indexName موجود بالفعل ✓\n";
        }
    }

    // فهرس مركب للاسم الكامل
    if (!in_array('idx_full_name', $existingIndexes)) {
        try {
            echo "إنشاء فهرس مركب للاسم الكامل...";
            $connection->statement("CREATE INDEX idx_full_name ON persons(CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB)");
            echo " ✅ تم\n";
        } catch (\Exception $e) {
            echo " ❌ خطأ: " . $e->getMessage() . "\n";
        }
    } else {
        echo "فهرس الاسم الكامل موجود بالفعل ✓\n";
    }

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "تم الانتهاء من إنشاء الفهارس!\n";

    // اختبار البحث السريع
    echo "\nاختبار سرعة البحث...\n";

    $startTime = microtime(true);
    $result = $connection->table('persons')->where('CI_ID_NUM', '407015692')->first();
    $endTime = microtime(true);

    $searchTime = round(($endTime - $startTime) * 1000, 2);
    echo "البحث برقم الهوية 407015692: " . $searchTime . " ms";

    if ($result) {
        echo " ✅ تم العثور على: " . $result->CI_FIRST_ARB . " " . $result->CI_FATHER_ARB . "\n";
    } else {
        echo " ❌ لم يتم العثور على نتائج\n";
    }

} catch (Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
}
