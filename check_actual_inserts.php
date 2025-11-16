<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = [
    'marital_status',
    'academic_degrees',
    'general_category',
];

echo "=================================================\n";
echo "   فحص آخر السجلات المدخلة\n";
echo "=================================================\n\n";

foreach ($tables as $table) {
    echo "📋 جدول: {$table}\n";
    $records = DB::table($table)->orderBy('id', 'desc')->limit(3)->get();

    echo "   📊 آخر 3 سجلات:\n";
    foreach ($records as $record) {
        echo "      • ID: {$record->id}\n";
        foreach ($record as $key => $value) {
            if ($key != 'id') {
                echo "        - {$key}: {$value}\n";
            }
        }
        echo "\n";
    }
}
