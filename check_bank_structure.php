<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== guardian_bank_accounts structure ===" . PHP_EOL;
$columns = Schema::getColumnListing('guardian_bank_accounts');
foreach ($columns as $col) {
    echo "- " . $col . PHP_EOL;
}

echo PHP_EOL . "=== Sample data ===" . PHP_EOL;
$sample = DB::table('guardian_bank_accounts')->first();
if ($sample) {
    foreach ((array)$sample as $key => $value) {
        echo $key . ": " . ($value ?? 'NULL') . PHP_EOL;
    }
}

echo PHP_EOL . "=== Total records ===" . PHP_EOL;
echo "Count: " . DB::table('guardian_bank_accounts')->count() . PHP_EOL;

echo PHP_EOL . "=== Checking sponsorships with person_type ===" . PHP_EOL;
$sponsorships = DB::table('sponsorships')
    ->whereNotNull('person_type')
    ->select('id', 'identity_number', 'person_type', 'guardian_identity_number', 'relation_id_number')
    ->limit(5)
    ->get();

foreach ($sponsorships as $sp) {
    echo "ID: {$sp->id}, Type: {$sp->person_type}, Identity: {$sp->identity_number}, Guardian: {$sp->guardian_identity_number}" . PHP_EOL;
}
