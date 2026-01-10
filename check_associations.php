<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== Checking data table columns for sponsor/association ===\n\n";

$cols = Schema::getColumnListing('data');
echo "Relevant columns:\n";
foreach($cols as $c) {
    if (strpos(strtolower($c), 'sponsor') !== false ||
        strpos(strtolower($c), 'assoc') !== false ||
        strpos(strtolower($c), 'represent') !== false) {
        echo "  - $c\n";
    }
}

echo "\n=== All data table columns ===\n";
echo implode(', ', $cols) . "\n";

echo "\n\n=== Checking sponsors table ===\n";
$sponsors = DB::table('sponsors')->select('id', 'sponsor_name', 'sponsor_short_name')->get();
echo "Sponsors:\n";
foreach ($sponsors as $s) {
    echo "  ID: {$s->id} | Name: {$s->sponsor_name} | Short: {$s->sponsor_short_name}\n";
}

echo "\n\n=== Checking sponsorships table for sponsor link ===\n";
$sample = DB::table('sponsorships')->take(5)->get();
echo "Sample sponsorships:\n";
print_r($sample->toArray());

echo "\n\n=== Checking re_people link to sponsors ===\n";
$orphan = DB::table('re_people')->first();
if ($orphan) {
    echo "Sample orphan:\n";
    print_r($orphan);
}
