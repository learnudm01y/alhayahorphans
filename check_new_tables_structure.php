<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;

$tables = [
    'bank_names',
    'aid_status',
    'association_employees',
    'currency_types',
    'death_reasons',
    'displacement_statuses',
    'document_types',
    'employment',
    'housing_status',
    'sponsorship_statuses',
    'type_of_accommodation',
    'type_of_guarantee',
];

echo "=================================================\n";
echo "   فحص بنية الجداول الجديدة\n";
echo "=================================================\n\n";

foreach ($tables as $table) {
    echo "📋 جدول: {$table}\n";

    if (!Schema::hasTable($table)) {
        echo "   ❌ الجدول غير موجود\n\n";
        continue;
    }

    $columns = Schema::getColumnListing($table);
    echo "   📊 الأعمدة: " . implode(', ', $columns) . "\n\n";
}

echo "=================================================\n";
echo "✨ تم الانتهاء من الفحص\n";
echo "=================================================\n";
