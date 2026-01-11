<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== data table columns ===\n";
$cols = DB::select('SHOW COLUMNS FROM data');
foreach($cols as $c) {
    echo "- " . $c->Field . " (" . $c->Type . ")\n";
}

echo "\n=== Looking for address-related columns ===\n";
foreach($cols as $c) {
    if (stripos($c->Field, 'address') !== false ||
        stripos($c->Field, 'phone') !== false ||
        stripos($c->Field, 'city') !== false ||
        stripos($c->Field, 'street') !== false) {
        echo "Found: " . $c->Field . " (" . $c->Type . ")\n";
    }
}

echo "\n=== Sample data record ===\n";
$sample = DB::table('data')->first();
if ($sample) {
    foreach((array)$sample as $key => $value) {
        if ($value !== null && $value !== '') {
            echo "$key: $value\n";
        }
    }
}
