<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== فحص الرقم 003624 ===\n";
$reserved = DB::table('reserved_codes')->where('code', '003624')->first();
if ($reserved) {
    echo "موجود في reserved_codes:\n";
    echo "  - id: {$reserved->id}\n";
    echo "  - used: " . ($reserved->used ? 'true' : 'false') . "\n";
    echo "  - used_at: " . ($reserved->used_at ?? 'NULL') . "\n";
} else {
    echo "غير موجود في reserved_codes!\n";
}

// فحص في data
$data = DB::table('data')->where('file_id_number', '003624')->first();
if ($data) {
    echo "\nموجود في data:\n";
    echo "  - id: {$data->id}\n";
    echo "  - data_first_name: " . ($data->data_first_name ?? 'NULL') . "\n";
}
