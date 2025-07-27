<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "📋 فحص بنية جدول persons في قاعدة civilregistry:\n";
echo str_repeat('=', 50) . "\n";

try {
    $columns = DB::connection('civilregistry')->select('DESCRIBE persons');

    echo "الأعمدة المتاحة:\n";
    foreach ($columns as $column) {
        echo "- {$column->Field} ({$column->Type})\n";
    }

    echo "\n📊 عدد السجلات: " . DB::connection('civilregistry')->table('persons')->count() . "\n";

    echo "\n📝 عينة من البيانات:\n";
    $sample = DB::connection('civilregistry')->table('persons')->limit(3)->get();
    foreach ($sample as $record) {
        echo "- رقم الهوية: {$record->CI_ID_NUM}, الاسم: {$record->CI_FIRST_ARB} {$record->CI_FATHER_ARB}\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
