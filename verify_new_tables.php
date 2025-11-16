<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = [
    'bank_names',
    'aid_status',
    'association_employees',
    'currency_types',
    'death_reasons',
    'displacement_statuses',
    'document_types',
    'sponsorship_statuses',
    'type_of_guarantee',
];

echo "=================================================\n";
echo "   التحقق من الجداول الجديدة\n";
echo "=================================================\n\n";

foreach ($tables as $table) {
    echo "📋 جدول: {$table}\n";
    $record = DB::table($table)->where('id', 0)->first();

    if ($record) {
        echo "   ✅ القيمة موجودة (id = 0)\n";
        echo "   📊 البيانات:\n";
        foreach ($record as $key => $value) {
            if (strlen($value) > 50) {
                $value = substr($value, 0, 50) . '...';
            }
            echo "      • {$key}: {$value}\n";
        }
    } else {
        echo "   ❌ القيمة غير موجودة\n";
    }
    echo "\n";
}

echo "=================================================\n";
echo "✨ تم الانتهاء من التحقق\n";
echo "=================================================\n";
