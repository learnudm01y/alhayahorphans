<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== أعمدة re_people ===\n";
$cols = DB::select('SHOW COLUMNS FROM re_people');
foreach($cols as $c) {
    echo "  - {$c->Field} ({$c->Type})\n";
}

echo "\n=== عينة من re_people ===\n";
$sample = DB::table('re_people')->limit(3)->get();
foreach($sample as $r) {
    echo "  ID: {$r->id}, person_id: " . ($r->person_id ?? 'NULL') . "\n";
}

echo "\n=== أعمدة data ===\n";
$cols = DB::select('SHOW COLUMNS FROM data');
foreach($cols as $c) {
    if (strpos($c->Field, 'guardian') !== false || strpos($c->Field, 'file') !== false || strpos($c->Field, 'name') !== false) {
        echo "  - {$c->Field}\n";
    }
}

echo "\n=== عينة من data ===\n";
$sample = DB::table('data')->limit(3)->get(['id', 'file_id_number']);
foreach($sample as $r) {
    echo "  ID: {$r->id}, file_id_number: " . ($r->file_id_number ?? 'NULL') . "\n";
}
