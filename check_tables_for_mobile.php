<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== bank_names columns ===\n";
try {
    $cols = DB::select('SHOW COLUMNS FROM bank_names');
    foreach($cols as $c) echo "- {$c->Field} ({$c->Type})\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== health_statuses columns ===\n";
try {
    $cols = DB::select('SHOW COLUMNS FROM health_statuses');
    foreach($cols as $c) echo "- {$c->Field} ({$c->Type})\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== city columns ===\n";
try {
    $cols = DB::select('SHOW COLUMNS FROM city');
    foreach($cols as $c) echo "- {$c->Field} ({$c->Type})\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Sample data ===\n";
echo "bank_names count: " . DB::table('bank_names')->count() . "\n";
echo "health_statuses count: " . DB::table('health_statuses')->count() . "\n";
echo "city count: " . DB::table('city')->count() . "\n";
