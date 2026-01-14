<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use Illuminate\Support\Facades\DB;

echo "=== فحص جدول bank_names ===\n";
$cols = DB::select('SHOW COLUMNS FROM bank_names');
foreach($cols as $col) {
    echo $col->Field . ' (' . $col->Type . ')' . PHP_EOL;
}

echo "\n=== عينة من البيانات ===\n";
$data = DB::select('SELECT * FROM bank_names LIMIT 5');
foreach($data as $row) {
    print_r($row);
    echo "\n";
}
?>
