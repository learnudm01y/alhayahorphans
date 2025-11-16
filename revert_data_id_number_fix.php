<?php

/**
 * REVERT SCRIPT: Change data_id_number back to BIGINT
 *
 * Reverting the database change - we'll fix the issue in code instead.
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n========================================\n";
echo "REVERT: Change data_id_number back to BIGINT\n";
echo "========================================\n\n";

try {
    echo "Checking current column type...\n";
    $columnInfo = DB::select("
        SELECT COLUMN_TYPE, DATA_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = 'aso'
        AND TABLE_NAME = 'data'
        AND COLUMN_NAME = 'data_id_number'
    ");

    if (!empty($columnInfo)) {
        $info = $columnInfo[0];
        echo "Current type: {$info->COLUMN_TYPE}\n\n";

        if ($info->DATA_TYPE === 'varchar') {
            echo "Reverting to BIGINT...\n";
            DB::statement("ALTER TABLE `data` MODIFY COLUMN `data_id_number` BIGINT NULL");
            echo "✓ SUCCESS: Column changed back to BIGINT\n\n";

            // Verify
            $verifyInfo = DB::select("
                SELECT COLUMN_TYPE, DATA_TYPE
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = 'aso'
                AND TABLE_NAME = 'data'
                AND COLUMN_NAME = 'data_id_number'
            ");

            if (!empty($verifyInfo)) {
                $verify = $verifyInfo[0];
                echo "Verified type: {$verify->COLUMN_TYPE}\n";
                echo "✓ Revert completed successfully!\n\n";
            }
        } else {
            echo "✓ Column is already BIGINT - no revert needed.\n\n";
        }
    }

    echo "========================================\n";
    echo "Database structure restored to original state.\n";
    echo "Now we'll fix the issue in the code instead.\n";
    echo "========================================\n\n";

} catch (\Exception $e) {
    echo "✗ ERROR: {$e->getMessage()}\n\n";
}
