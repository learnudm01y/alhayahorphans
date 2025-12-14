<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "فحص هيكل الجداول...\n\n";

// جدول provinces
echo "=== جدول provinces ===\n";
$province = DB::table('provinces')->first();
if ($province) {
    echo "الأعمدة:\n";
    foreach (get_object_vars($province) as $key => $value) {
        echo "  - {$key}: {$value}\n";
    }
}

// جدول city
echo "\n=== جدول city ===\n";
$city = DB::table('city')->first();
if ($city) {
    echo "الأعمدة:\n";
    foreach (get_object_vars($city) as $key => $value) {
        echo "  - {$key}: {$value}\n";
    }
}

// جدول data
echo "\n=== أعمدة المحافظة والمدينة في data ===\n";
$columns = DB::select("SHOW COLUMNS FROM data LIKE '%province%' OR LIKE '%city%'");
foreach ($columns as $col) {
    echo "  - {$col->Field}\n";
}
