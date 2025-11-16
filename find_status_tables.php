<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = DB::select('SHOW TABLES');
echo "📋 الجداول التي تحتوي على 'status' أو 'accept':\n\n";

foreach ($tables as $table) {
    $tableName = array_values((array)$table)[0];
    if (stripos($tableName, 'status') !== false || stripos($tableName, 'accept') !== false) {
        echo "  - {$tableName}\n";
    }
}
