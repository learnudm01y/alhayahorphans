<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "حذف الأعمدة المطبّعة...\n\n";

try {
    DB::connection('civilregistry')->statement("
        ALTER TABLE persons
        DROP COLUMN CI_FIRST_ARB_NORMALIZED,
        DROP COLUMN CI_FATHER_ARB_NORMALIZED,
        DROP COLUMN CI_GRAND_FATHER_ARB_NORMALIZED,
        DROP COLUMN CI_FAMILY_ARB_NORMALIZED
    ");
    echo "✅ تم حذف الأعمدة بنجاح!\n";
} catch (\Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
