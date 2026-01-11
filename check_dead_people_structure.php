<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== dead_people columns ===\n";
$cols = DB::select('SHOW COLUMNS FROM dead_people');
foreach($cols as $c) {
    echo "- " . $c->Field . " (" . $c->Type . ")\n";
}

echo "\n=== Sample record ===\n";
$sample = DB::table('dead_people')->first();
if ($sample) {
    foreach((array)$sample as $key => $value) {
        if ($value !== null && $value !== '') {
            echo "$key: $value\n";
        }
    }
}

echo "\n=== Checking sponsorships with person_type ===\n";
$sponsorship = DB::table('sponsorships')
    ->whereNotNull('person_type')
    ->first();
if ($sponsorship) {
    echo "Sponsorship ID: " . $sponsorship->id . "\n";
    echo "person_type: " . $sponsorship->person_type . "\n";
    echo "identity_number: " . $sponsorship->identity_number . "\n";
}

echo "\n=== person_type values in sponsorships ===\n";
$types = DB::table('sponsorships')
    ->select('person_type', DB::raw('count(*) as cnt'))
    ->groupBy('person_type')
    ->get();
foreach($types as $t) {
    echo "person_type=" . ($t->person_type ?? 'NULL') . " => " . $t->cnt . "\n";
}
