<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== أعمدة جدول sponsorships ===\n\n";

$columns = DB::select('SHOW COLUMNS FROM sponsorships');
foreach ($columns as $col) {
    echo $col->Field . "\n";
}
