<?php

/**
 * FIX SCRIPT: Change data_id_number from INT to VARCHAR
 *
 * This fixes the critical issue preventing Excel imports.
 * The data_id_number column must be VARCHAR to store national ID numbers.
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "\n========================================\n";
echo "FIX: Change data_id_number to VARCHAR\n";
echo "========================================\n\n";

try {
    echo "Checking current column type...\n";
    $columnInfo = DB::select("
        SELECT COLUMN_TYPE, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = 'aso'
        AND TABLE_NAME = 'data'
        AND COLUMN_NAME = 'data_id_number'
    ");

    if (!empty($columnInfo)) {
        $info = $columnInfo[0];
        echo "Current type: {$info->COLUMN_TYPE}\n";
        echo "Data type: {$info->DATA_TYPE}\n\n";

        if ($info->DATA_TYPE === 'int' || $info->DATA_TYPE === 'bigint') {
            echo "⚠️  PROBLEM CONFIRMED: Column is INTEGER type!\n";
            echo "This prevents storing national ID numbers with letters or leading zeros.\n\n";

            echo "Applying fix...\n";
            DB::statement("ALTER TABLE `data` MODIFY COLUMN `data_id_number` VARCHAR(50) NULL");
            echo "✓ SUCCESS: Column changed to VARCHAR(50)\n\n";

            // Verify the change
            $verifyInfo = DB::select("
                SELECT COLUMN_TYPE, DATA_TYPE
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = 'aso'
                AND TABLE_NAME = 'data'
                AND COLUMN_NAME = 'data_id_number'
            ");

            if (!empty($verifyInfo)) {
                $verify = $verifyInfo[0];
                echo "Verified new type: {$verify->COLUMN_TYPE}\n";
                echo "✓ Fix applied successfully!\n\n";
            }

        } else {
            echo "✓ Column is already VARCHAR - no fix needed.\n\n";
        }
    } else {
        echo "✗ ERROR: Column 'data_id_number' not found!\n\n";
    }

    // Also check and fix file_id_number if needed
    echo "Checking file_id_number column...\n";
    $fileIdInfo = DB::select("
        SELECT COLUMN_TYPE, DATA_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = 'aso'
        AND TABLE_NAME = 'data'
        AND COLUMN_NAME = 'file_id_number'
    ");

    if (!empty($fileIdInfo)) {
        $info = $fileIdInfo[0];
        echo "Current type: {$info->COLUMN_TYPE}\n";

        if ($info->DATA_TYPE === 'int' || $info->DATA_TYPE === 'bigint') {
            echo "⚠️  file_id_number is also INTEGER - fixing...\n";
            DB::statement("ALTER TABLE `data` MODIFY COLUMN `file_id_number` VARCHAR(50) NULL");
            echo "✓ Fixed file_id_number to VARCHAR(50)\n\n";
        } else {
            echo "✓ file_id_number is already VARCHAR\n\n";
        }
    }

    echo "========================================\n";
    echo "FIX COMPLETE\n";
    echo "========================================\n\n";

    echo "Now try importing your Excel file again.\n";
    echo "The data should insert successfully.\n\n";

} catch (\Exception $e) {
    echo "✗ ERROR: {$e->getMessage()}\n";
    echo "SQL Error Code: {$e->getCode()}\n\n";
}
