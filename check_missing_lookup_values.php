<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n========================================\n";
echo "CHECK MISSING VALUES IN LOOKUP TABLES\n";
echo "========================================\n\n";

// القيم التي يحاول Excel استخدامها
$valuesToCheck = [
    ['table' => 'marital_status', 'values' => [0, 1, 2, 3, 4]],
    ['table' => 'academic_degrees', 'values' => [0, 1, 2, 3, 4, 5, 6, 7, 8]],
    ['table' => 'displacement_statuses', 'values' => [0, 1, 2, 3]],
    ['table' => 'employment', 'values' => [0, 1, 2, 3]],
    ['table' => 'housing_status', 'values' => [0, 1, 2, 3]],
    ['table' => 'type_of_accommodation', 'values' => [0, 1, 2, 3, 4, 5]],
    ['table' => 'provinces', 'values' => [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10]],
    ['table' => 'city', 'values' => [0, 52, 54]],
];

$allMissingValues = [];

foreach ($valuesToCheck as $check) {
    $table = $check['table'];
    echo "Checking table: {$table}\n";
    echo str_repeat('-', 50) . "\n";

    $existingValues = DB::table($table)->pluck('id')->toArray();
    echo "Existing IDs: " . implode(', ', $existingValues) . "\n";

    $missingValues = [];
    foreach ($check['values'] as $value) {
        if (!in_array($value, $existingValues)) {
            $missingValues[] = $value;
        }
    }

    if (!empty($missingValues)) {
        echo "❌ MISSING: " . implode(', ', $missingValues) . "\n";
        $allMissingValues[$table] = $missingValues;
    } else {
        echo "✓ All values exist\n";
    }
    echo "\n";
}

echo "\n========================================\n";
echo "SUMMARY\n";
echo "========================================\n\n";

if (empty($allMissingValues)) {
    echo "✓ All required values exist in lookup tables!\n\n";
} else {
    echo "❌ MISSING VALUES DETECTED:\n\n";
    foreach ($allMissingValues as $table => $values) {
        echo "{$table}: " . implode(', ', $values) . "\n";
    }
    echo "\n";
    echo "⚠️ THIS IS WHY EXCEL IMPORTS FAIL!\n";
    echo "These values are being used in the Excel file but don't exist in the database.\n\n";

    echo "SOLUTION:\n";
    echo "---------\n";
    echo "Run the insert_default_values.php script to add missing values,\n";
    echo "OR manually add these values to the lookup tables.\n\n";
}
