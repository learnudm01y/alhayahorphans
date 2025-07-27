<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "فحص هيكل جدول persons في قاعدة civilregistry:\n";
echo str_repeat("=", 60) . "\n";

try {
    $columns = \Illuminate\Support\Facades\DB::connection('civilregistry')->select('DESCRIBE persons');

    foreach($columns as $col) {
        echo sprintf("%-20s | %-15s | Null: %-3s | Key: %-3s\n",
            $col->Field,
            $col->Type,
            $col->Null,
            $col->Key
        );
    }

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "إجمالي الأعمدة: " . count($columns) . "\n";

    // اختبار عدد السجلات
    $count = \Illuminate\Support\Facades\DB::connection('civilregistry')->table('persons')->count();
    echo "إجمالي السجلات: " . number_format($count) . "\n";

    // البحث عن أعمدة مهمة
    echo "\nالأعمدة المهمة للبحث:\n";
    $importantFields = ['ID', 'CI_ID_NUM', 'CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_GRAND_FATHER_ARB', 'CI_FAMILY_ARB'];
    foreach ($columns as $col) {
        if (in_array($col->Field, $importantFields)) {
            echo "✓ " . $col->Field . "\n";
        }
    }

} catch (Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
}
