<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== فحص بنية جدول re_people ===" . PHP_EOL;
$cols = DB::getSchemaBuilder()->getColumnListing('re_people');
$relevantCols = array_filter($cols, function($c) {
    return strpos($c, 'name') !== false ||
           strpos($c, 'birth') !== false ||
           strpos($c, 'health') !== false ||
           strpos($c, 'identity') !== false ||
           strpos($c, 'first') !== false ||
           strpos($c, 'father') !== false ||
           strpos($c, 'grand') !== false ||
           strpos($c, 'family') !== false;
});
echo "حقول re_people:" . PHP_EOL;
foreach($relevantCols as $c) echo "  - $c" . PHP_EOL;

echo PHP_EOL . "=== فحص بنية جدول data ===" . PHP_EOL;
$cols = DB::getSchemaBuilder()->getColumnListing('data');
$relevantCols = array_filter($cols, function($c) {
    return strpos($c, 'name') !== false ||
           strpos($c, 'birth') !== false ||
           strpos($c, 'health') !== false ||
           strpos($c, 'identity') !== false ||
           strpos($c, 'first') !== false ||
           strpos($c, 'father') !== false ||
           strpos($c, 'grand') !== false ||
           strpos($c, 'family') !== false;
});
echo "حقول data:" . PHP_EOL;
foreach($relevantCols as $c) echo "  - $c" . PHP_EOL;

echo PHP_EOL . "=== فحص بنية جدول dead_people ===" . PHP_EOL;
$cols = DB::getSchemaBuilder()->getColumnListing('dead_people');
$relevantCols = array_filter($cols, function($c) {
    return strpos($c, 'name') !== false ||
           strpos($c, 'birth') !== false ||
           strpos($c, 'death') !== false ||
           strpos($c, 'address') !== false ||
           strpos($c, 'first') !== false ||
           strpos($c, 'father') !== false ||
           strpos($c, 'grand') !== false ||
           strpos($c, 'family') !== false;
});
echo "حقول dead_people:" . PHP_EOL;
foreach($relevantCols as $c) echo "  - $c" . PHP_EOL;

echo PHP_EOL . "=== انتهى ===" . PHP_EOL;
