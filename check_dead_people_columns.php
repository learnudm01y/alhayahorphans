<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== أعمدة جدول dead_people ===\n";
$cols = DB::select("SHOW COLUMNS FROM dead_people");
foreach($cols as $c) {
    echo $c->Field . " - " . $c->Type . "\n";
}

echo "\n=== أعمدة جدول re_people ===\n";
$cols = DB::select("SHOW COLUMNS FROM re_people");
foreach($cols as $c) {
    echo $c->Field . " - " . $c->Type . "\n";
}

echo "\n=== أعمدة جدول data (البحث عن birth_date) ===\n";
$cols = DB::select("SHOW COLUMNS FROM data WHERE Field LIKE '%birth%'");
foreach($cols as $c) {
    echo $c->Field . " - " . $c->Type . "\n";
}
