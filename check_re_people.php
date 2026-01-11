<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== re_people columns ===\n";
$cols = DB::select('SHOW COLUMNS FROM re_people');
foreach($cols as $c) {
    echo $c->Field . " (" . $c->Type . ")\n";
}

echo "\n=== dead_people columns ===\n";
$cols = DB::select('SHOW COLUMNS FROM dead_people');
foreach($cols as $c) {
    echo $c->Field . " (" . $c->Type . ")\n";
}

echo "\n=== Sample from re_people ===\n";
$sample = DB::table('re_people')->first();
if ($sample) {
    foreach((array)$sample as $key => $value) {
        if (stripos($key, 'id') !== false || stripos($key, 'gender') !== false || stripos($key, 'name') !== false) {
            echo "$key: $value\n";
        }
    }
}
