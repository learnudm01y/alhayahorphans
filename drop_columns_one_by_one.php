<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "حذف الأعمدة المطبّعة واحداً واحداً...\n\n";

$columns = [
    'CI_FIRST_ARB_NORMALIZED',
    'CI_FATHER_ARB_NORMALIZED',
    'CI_GRAND_FATHER_ARB_NORMALIZED',
    'CI_FAMILY_ARB_NORMALIZED'
];

foreach ($columns as $column) {
    try {
        echo "حذف $column... ";
        DB::connection('civilregistry')->statement("ALTER TABLE persons DROP COLUMN $column");
        echo "✅\n";
    } catch (\Exception $e) {
        echo "❌ " . $e->getMessage() . "\n";
    }
}

echo "\n✅ تم الانتهاء!\n";
