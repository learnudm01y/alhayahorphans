<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== أعمدة جدول dead_people ===\n";
$columns = DB::select('SHOW COLUMNS FROM dead_people');
foreach($columns as $c) {
    echo "- " . $c->Field . "\n";
}

echo "\n=== أعمدة جدول portal_general_registration_field_values ===\n";
$columns2 = DB::select('SHOW COLUMNS FROM portal_general_registration_field_values');
foreach($columns2 as $c) {
    echo "- " . $c->Field . " (" . $c->Type . ")\n";
}

echo "\n=== عينة من portal_general_registration_field_values ===\n";
$samples = DB::table('portal_general_registration_field_values')->limit(5)->get();
foreach($samples as $s) {
    echo "ID: {$s->id}, sponsorship_id: {$s->sponsorship_id}, file_id: {$s->file_id_number}, field_key: {$s->field_key}, value: {$s->field_value}\n";
}
