<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "فحص هيكل جدول المدن:\n";
echo "====================\n";

try {
    // الحصول على أعمدة الجدول
    $columns = Illuminate\Support\Facades\DB::select('DESCRIBE city');

    echo "أعمدة جدول city:\n";
    foreach ($columns as $column) {
        echo "- " . $column->Field . " (" . $column->Type . ")\n";
    }

    echo "\nبيانات المدينة رقم 7:\n";
    echo "===================\n";

    $city = Illuminate\Support\Facades\DB::table('city')->where('id', 7)->first();
    if ($city) {
        foreach ($city as $key => $value) {
            echo "$key: " . ($value ?? 'NULL') . "\n";
        }
    }

    echo "\nأول 5 مدن مع كل البيانات:\n";
    echo "==========================\n";

    $cities = Illuminate\Support\Facades\DB::table('city')->limit(5)->get();
    foreach ($cities as $index => $city) {
        echo "المدينة " . ($index + 1) . ":\n";
        foreach ($city as $key => $value) {
            echo "  $key: " . ($value ?? 'NULL') . "\n";
        }
        echo "---\n";
    }

} catch (Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
}

?>
