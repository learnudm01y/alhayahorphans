<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== التحقق من الكفالة 003405 ===\n";
$s = DB::table('sponsorships')->where('internal_file_number', '003405')->first();

if ($s) {
    echo "relation_id_number: [" . ($s->relation_id_number ?: 'EMPTY') . "]\n";
    echo "guardian_name: " . $s->guardian_name . "\n";
    echo "guardian_identity_number: " . $s->guardian_identity_number . "\n";
    echo "orphan_name: " . $s->orphan_name . "\n";
    echo "identity_number: " . $s->identity_number . "\n";
}

echo "\n=== آخر 3 سجلات في data ===\n";
$dataRecords = DB::table('data')->orderBy('id', 'desc')->limit(3)->get();
foreach ($dataRecords as $d) {
    echo "ID: {$d->id} | file_id_number: {$d->file_id_number} | name: {$d->data_first_name}\n";
}

echo "\n=== آخر 3 سجلات في guardian_bank_accounts ===\n";
$banks = DB::table('guardian_bank_accounts')->orderBy('id', 'desc')->limit(3)->get();
foreach ($banks as $b) {
    echo "ID: {$b->id} | guardian_registration: {$b->guardian_registration}\n";
}
