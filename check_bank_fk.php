<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "═══════════════════════════════════════════════════════════════\n";
echo "📋 فحص قيود guardian_bank_accounts\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// جلب الـ foreign keys
$constraints = DB::select("
    SELECT
        CONSTRAINT_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'guardian_bank_accounts'
    AND REFERENCED_TABLE_NAME IS NOT NULL
");

echo "📌 Foreign Keys الحالية:\n";
foreach ($constraints as $c) {
    echo "   - {$c->CONSTRAINT_NAME}\n";
    echo "     العمود: {$c->COLUMN_NAME}\n";
    echo "     يشير إلى: {$c->REFERENCED_TABLE_NAME}.{$c->REFERENCED_COLUMN_NAME}\n\n";
}

// فحص أعمدة الجدول
echo "📌 أعمدة guardian_bank_accounts:\n";
$columns = DB::select("DESCRIBE guardian_bank_accounts");
foreach ($columns as $col) {
    echo "   - {$col->Field} | {$col->Type} | " . ($col->Null == 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}
