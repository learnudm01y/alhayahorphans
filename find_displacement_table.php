<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n========================================\n";
echo "Find Displacement Table Name\n";
echo "========================================\n\n";

// Get all tables
$tables = DB::select('SHOW TABLES');
$dbName = 'aso';
$tableKey = "Tables_in_{$dbName}";

echo "All tables with 'displacement' in name:\n";
foreach ($tables as $table) {
    $tableName = $table->$tableKey;
    if (stripos($tableName, 'displacement') !== false) {
        echo "  - {$tableName}\n";
    }
}
echo "\n";

// Check the foreign key constraint name
echo "Foreign key constraints on 'data' table:\n";
$constraints = DB::select("
    SELECT
        CONSTRAINT_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM
        INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE
        TABLE_SCHEMA = 'aso'
        AND TABLE_NAME = 'data'
        AND COLUMN_NAME LIKE '%displacement%'
");

foreach ($constraints as $constraint) {
    echo "  Column: {$constraint->COLUMN_NAME}\n";
    echo "  References: {$constraint->REFERENCED_TABLE_NAME}.{$constraint->REFERENCED_COLUMN_NAME}\n";
    echo "  Constraint: {$constraint->CONSTRAINT_NAME}\n\n";
}
