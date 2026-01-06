<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "═══════════════════════════════════════════════════════════════\n";
echo "           فحص بنية جدول re_people\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$columns = DB::select('SHOW COLUMNS FROM re_people');
foreach ($columns as $col) {
    echo "   - {$col->Field} ({$col->Type}) " . ($col->Null === 'NO' ? 'NOT NULL' : 'NULL') . "\n";
}

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "           فحص بنية جدول data\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$columns = DB::select('SHOW COLUMNS FROM data');
foreach ($columns as $col) {
    echo "   - {$col->Field} ({$col->Type})\n";
}

echo "\n";
