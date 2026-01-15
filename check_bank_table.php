<?php
/**
 * التحقق من هيكل جدول guardian_bank_accounts
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "===========================================\n";
echo "🏦 هيكل جدول guardian_bank_accounts\n";
echo "===========================================\n\n";

$columns = DB::select('SHOW COLUMNS FROM guardian_bank_accounts');

echo "الأعمدة:\n";
echo str_repeat("-", 60) . "\n";
foreach ($columns as $col) {
    echo sprintf("%-30s %-20s Null: %-5s\n", $col->Field, $col->Type, $col->Null);
}

echo "\n\nآخر 5 سجلات:\n";
echo str_repeat("-", 60) . "\n";

$lastRecords = DB::table('guardian_bank_accounts')
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get();

foreach ($lastRecords as $record) {
    echo "ID: " . $record->id . "\n";
    echo "guardian_registration: " . $record->guardian_registration . "\n";
    echo "bank_name: " . $record->bank_name . "\n";
    echo "re_guardian_name: " . ($record->re_guardian_name ?? 'NULL') . "\n";
    echo "check_account: " . $record->check_account . "\n";
    echo str_repeat("-", 40) . "\n";
}
