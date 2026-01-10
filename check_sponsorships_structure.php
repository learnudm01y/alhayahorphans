<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== sponsorships table ===" . PHP_EOL;
$cols = Schema::getColumnListing('sponsorships');
echo "Columns: " . implode(', ', $cols) . PHP_EOL;
$count = DB::table('sponsorships')->count();
echo "Count: " . $count . PHP_EOL;
$sample = DB::table('sponsorships')->limit(3)->get();
print_r($sample->toArray());

echo PHP_EOL . "=== sponsors table ===" . PHP_EOL;
$sponsors = DB::table('sponsors')->select('id', 'sponsor_name', 'sponsor_short_name')->get();
echo "Count: " . count($sponsors) . PHP_EOL;
print_r($sponsors->toArray());

echo PHP_EOL . "=== sponsorship_statuses ===" . PHP_EOL;
$statuses = DB::table('sponsorship_statuses')->get();
print_r($statuses->toArray());

echo PHP_EOL . "=== Check civilregistry persons ===" . PHP_EOL;
try {
    $person = DB::connection('civilregistry')->table('persons')->select('ID', 'CI_ID_NUM', 'CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_GRAND_FATHER_ARB', 'CI_FAMILY_ARB', 'CI_BIRTH_DT', 'CI_SEX_CD')->limit(1)->first();
    print_r($person);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
