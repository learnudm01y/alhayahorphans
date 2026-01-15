<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== أنواع الأعمدة في جدول data ===\n";
$cols = DB::select("SHOW COLUMNS FROM data WHERE Field IN ('data_id_number', 'data_phone_number', 'data_alt_phone_number', 'file_id_number')");
foreach($cols as $c) {
    echo $c->Field . ' => ' . $c->Type . "\n";
}
