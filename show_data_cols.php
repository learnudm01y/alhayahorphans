<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== جميع أعمدة data ===\n";
$cols = DB::select('SHOW COLUMNS FROM data');
foreach($cols as $c) {
    echo "  - {$c->Field}\n";
}
