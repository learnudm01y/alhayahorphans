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
echo "   حذف القيم الافتراضية القديمة (تنظيف)\n";
echo "=================================================\n\n";

DB::statement('SET FOREIGN_KEY_CHECKS=0;');

foreach ($tables as $table) {
    echo "🗑️  حذف من جدول: {$table}\n";
    try {
        $count = DB::table($table)->where('id', 0)->delete();
        echo "   ✅ تم حذف {$count} سجل\n\n";
    } catch (\Exception $e) {
        echo "   ⚠️  خطأ: " . $e->getMessage() . "\n\n";
    }
}

DB::statement('SET FOREIGN_KEY_CHECKS=1;');

echo "=================================================\n";
echo "✨ تم الانتهاء من التنظيف\n";
echo "=================================================\n";
