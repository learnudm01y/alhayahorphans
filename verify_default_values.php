<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = [
    'marital_status',
    'academic_degrees',
    'general_category',
    'employment',
    'housing_status',
    'type_of_accommodation',
    'city',
    'provinces',
    'category_of_relations',
    'health_statuses',
];

echo "=================================================\n";
echo "   التحقق من القيم الافتراضية المدخلة\n";
echo "=================================================\n\n";

foreach ($tables as $table) {
    echo "📋 جدول: {$table}\n";
    $record = DB::table($table)->where('id', 0)->first();

    if ($record) {
        echo "   ✅ القيمة موجودة\n";
        echo "   📊 البيانات:\n";
        foreach ($record as $key => $value) {
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
