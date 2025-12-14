<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Checking Table Structures ===\n\n";

// Check dead_people columns
echo "DEAD_PEOPLE columns:\n";
$deadColumns = DB::select("SHOW COLUMNS FROM dead_people");
foreach ($deadColumns as $col) {
    echo "  - {$col->Field} ({$col->Type})\n";
}

echo "\nRE_PEOPLE columns:\n";
$reColumns = DB::select("SHOW COLUMNS FROM re_people");
foreach ($reColumns as $col) {
    echo "  - {$col->Field} ({$col->Type})\n";
}

echo "\nDATA columns (sample):\n";
$dataColumns = DB::select("SHOW COLUMNS FROM data LIKE '%id%'");
foreach ($dataColumns as $col) {
    echo "  - {$col->Field} ({$col->Type})\n";
}

echo "\nSPONSORSHIPS columns:\n";
$sponsorColumns = DB::select("SHOW COLUMNS FROM sponsorships");
foreach ($sponsorColumns as $col) {
    echo "  - {$col->Field} ({$col->Type})\n";
}

echo "\n=== Done ===\n";
