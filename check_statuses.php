<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== أعمدة جدول sponsorship_statuses ===\n";
$columns = DB::select('SHOW COLUMNS FROM sponsorship_statuses');
foreach($columns as $c) {
    echo "- {$c->Field}\n";
}

echo "\n=== حالات الكفالة ===\n";
$statuses = DB::table('sponsorship_statuses')->get();
foreach($statuses as $s) {
    $arr = (array)$s;
    print_r($arr);
}
