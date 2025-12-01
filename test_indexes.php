<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n=== Checking Indexes on persons table (civilregistry) ===\n";
$indexes = DB::connection('civilregistry')->select('SHOW INDEX FROM persons WHERE Key_name LIKE "idx_%"');
foreach ($indexes as $idx) {
    echo sprintf("Index: %s | Column: %s | Type: %s\n", $idx->Key_name, $idx->Column_name, $idx->Index_type);
}

echo "\n=== Checking Indexes on data table (aso) ===\n";
$indexes = DB::connection('mysql')->select('SHOW INDEX FROM data WHERE Key_name LIKE "idx_%"');
foreach ($indexes as $idx) {
    echo sprintf("Index: %s | Column: %s | Type: %s\n", $idx->Key_name, $idx->Column_name, $idx->Index_type);
}

echo "\n=== Checking Indexes on re_people table (aso) ===\n";
$indexes = DB::connection('mysql')->select('SHOW INDEX FROM re_people WHERE Key_name LIKE "idx_%"');
foreach ($indexes as $idx) {
    echo sprintf("Index: %s | Column: %s | Type: %s\n", $idx->Key_name, $idx->Column_name, $idx->Index_type);
}

echo "\n✅ فحص الفهارس اكتمل بنجاح!\n";
