<?php

/**
 * DEBUG SCRIPT: Test Excel Import - Find Why No Data Inserts
 *
 * This script tests the Excel import process step by step to find
 * where data is being blocked from reaching the database.
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\ExcelImportService;
use App\Models\Data;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "\n========================================\n";
echo "EXCEL IMPORT DEBUG - Finding Data Blockage\n";
echo "========================================\n\n";

// Enable query logging
DB::enableQueryLog();

$excelService = new ExcelImportService();

// Test 1: Check if we can manually insert a test record
echo "TEST 1: Manual Record Insertion\n";
echo "--------------------------------\n";
try {
    $testData = [
        'file_id_number' => 'TEST_' . time(),
        'data_id_number' => 'TEST_ID_' . time(),
        'data_first_name' => 'Test',
        'data_father_name' => 'Name',
        'data_family_name' => 'Family',
        'data_request_status' => 1, // Make sure this is the "Accepted" status ID
        'data_user_insert_data' => 1,
        'created_at' => now(),
        'updated_at' => now()
    ];

    DB::beginTransaction();
    $record = Data::create($testData);
    DB::commit();

    echo "✓ SUCCESS: Manually created record ID: {$record->id}\n";
    echo "  File ID: {$record->file_id_number}\n";

    // Delete the test record
    $record->delete();
    echo "  (Test record cleaned up)\n\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "✗ FAILED: {$e->getMessage()}\n";
    echo "  This means the Data model itself has an issue.\n\n";
}

// Test 2: Check accepted status ID
echo "TEST 2: Check Accepted Status ID\n";
echo "---------------------------------\n";
try {
    $acceptedStatus = DB::table('request_status')
        ->where('description', 'مقبول')
        ->first();

    if ($acceptedStatus) {
        echo "✓ Found: Accepted status ID = {$acceptedStatus->id}\n";
        echo "  Description: {$acceptedStatus->description}\n\n";
    } else {
        echo "✗ WARNING: No 'مقبول' status found in request_status table!\n";
        echo "  This could cause all imports to fail.\n\n";
    }
} catch (\Exception $e) {
    echo "✗ ERROR: {$e->getMessage()}\n\n";
}

// Test 3: Check for recent import attempts
echo "TEST 3: Recent Import Attempts\n";
echo "-------------------------------\n";
$recentData = Data::orderBy('created_at', 'desc')
    ->limit(5)
    ->get(['id', 'file_id_number', 'data_first_name', 'created_at']);

if ($recentData->count() > 0) {
    echo "✓ Found {$recentData->count()} recent records:\n";
    foreach ($recentData as $data) {
        echo "  - ID: {$data->id}, File: {$data->file_id_number}, Name: {$data->data_first_name}, Created: {$data->created_at}\n";
    }
    echo "\n";
} else {
    echo "✗ No records found in Data table.\n";
    echo "  This confirms NO DATA has been imported recently.\n\n";
}

// Test 4: Check foreign key constraints
echo "TEST 4: Foreign Key Constraints\n";
echo "--------------------------------\n";
try {
    $constraints = DB::select("
        SELECT
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM
            INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE
            TABLE_SCHEMA = 'aso'
            AND TABLE_NAME = 'data'
            AND REFERENCED_TABLE_NAME IS NOT NULL
    ");

    echo "✓ Found " . count($constraints) . " foreign key constraints on 'data' table:\n";
    foreach ($constraints as $constraint) {
        echo "  - {$constraint->COLUMN_NAME} → {$constraint->REFERENCED_TABLE_NAME}.{$constraint->REFERENCED_COLUMN_NAME}\n";

        // Check if value 0 exists in referenced table
        $hasZero = DB::table($constraint->REFERENCED_TABLE_NAME)
            ->where($constraint->REFERENCED_COLUMN_NAME, 0)
            ->exists();

        if ($hasZero) {
            echo "    ✓ Value 0 exists in {$constraint->REFERENCED_TABLE_NAME}\n";
        } else {
            echo "    ✗ WARNING: Value 0 NOT found in {$constraint->REFERENCED_TABLE_NAME}!\n";
            echo "      This will cause imports with value 0 to FAIL!\n";
        }
    }
    echo "\n";
} catch (\Exception $e) {
    echo "✗ ERROR: {$e->getMessage()}\n\n";
}

// Test 5: Simulate import with test data
echo "TEST 5: Simulate Import Process\n";
echo "--------------------------------\n";
echo "Creating a test record with all required fields...\n";
try {
    $testImportData = [
        'file_id_number' => 'SIM_' . time(),
        'data_id_number' => 'SIM_ID_' . time(),
        'data_first_name' => 'محمد',
        'data_father_name' => 'علي',
        'data_family_name' => 'الشمري',
        'data_gender' => 1,
        'data_marital_status' => 0, // Using 0 (Unknown)
        'data_academic_qualification' => 0,
        'data_displacement_status' => 0,
        'data_city' => 0,
        'data_province' => 0,
        'data_employment_status_breadwinner' => 0,
        'data_housing_status' => 0,
        'data_current_housing_type' => 0,
        'data_request_status' => 1, // Accepted
        'data_user_insert_data' => 1,
        'original_file_id_from_excel' => 'TEST_EXCEL_IMPORT',
        'created_at' => now(),
        'updated_at' => now()
    ];

    DB::beginTransaction();

    echo "  Attempting Model::create()...\n";
    $simRecord = Data::create($testImportData);

    echo "  ✓ Created successfully! ID: {$simRecord->id}\n";
    echo "  Checking if visible in database...\n";

    $found = Data::find($simRecord->id);
    if ($found) {
        echo "  ✓ Record is visible in database\n";
        echo "  Status: {$found->data_request_status}\n";
    } else {
        echo "  ✗ Record NOT visible after creation!\n";
    }

    DB::commit();
    echo "  ✓ Transaction committed\n";

    // Clean up
    $simRecord->delete();
    echo "  (Cleaned up test record)\n\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "  ✗ FAILED: {$e->getMessage()}\n";
    echo "  SQL Error Code: {$e->getCode()}\n";
    echo "  THIS IS THE PROBLEM: Records cannot be inserted!\n\n";

    // Show query log
    $queries = DB::getQueryLog();
    if (!empty($queries)) {
        echo "  Last SQL Query:\n";
        $lastQuery = end($queries);
        echo "  {$lastQuery['query']}\n";
        echo "  Bindings: " . json_encode($lastQuery['bindings']) . "\n";
    }
    echo "\n";
}

// Test 6: Check query log
echo "TEST 6: Review Query Log\n";
echo "------------------------\n";
$queries = DB::getQueryLog();
echo "Total queries executed: " . count($queries) . "\n";
if (count($queries) > 0) {
    echo "Last 3 queries:\n";
    $last3 = array_slice($queries, -3);
    foreach ($last3 as $i => $query) {
        echo "\n" . ($i + 1) . ". {$query['query']}\n";
        if (!empty($query['bindings'])) {
            echo "   Bindings: " . json_encode($query['bindings'], JSON_UNESCAPED_UNICODE) . "\n";
        }
        echo "   Time: {$query['time']}ms\n";
    }
}

echo "\n========================================\n";
echo "DIAGNOSTIC COMPLETE\n";
echo "========================================\n\n";

echo "SUMMARY:\n";
echo "--------\n";
echo "If Test 5 failed with a SQL error, that's your problem.\n";
echo "If Test 5 succeeded, then the issue is in ExcelImportService logic.\n";
echo "Check for:\n";
echo "  1. All records being marked as duplicates\n";
echo "  2. Foreign key constraint failures\n";
echo "  3. Missing 'مقبول' status in request_status table\n";
echo "  4. Missing value 0 in lookup tables\n\n";
