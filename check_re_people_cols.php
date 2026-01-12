<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== re_people columns ===\n";
$cols = DB::select('SHOW COLUMNS FROM re_people');
foreach($cols as $c) {
    echo $c->Field . "\n";
}

echo "\n=== dead_people columns ===\n";
$cols = DB::select('SHOW COLUMNS FROM dead_people');
foreach($cols as $c) {
    echo $c->Field . "\n";
}
