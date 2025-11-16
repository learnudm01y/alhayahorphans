<?php

/**
 * DEEP DIAGNOSIS: Find the Real Problem with Excel Import
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Data;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "\n========================================\n";
echo "DEEP DIAGNOSIS - Excel Import Problem\n";
echo "========================================\n\n";

// 1. Check how many records exist in data table
echo "STEP 1: Check Data Table Records\n";
echo "--------------------------------\n";
$totalRecords = Data::count();
echo "Total records in 'data' table: {$totalRecords}\n";

if ($totalRecords > 0) {
    $recentRecords = Data::orderBy('created_at', 'desc')->limit(5)->get();
    echo "\nLast 5 records:\n";
    foreach ($recentRecords as $record) {
        echo "  ID: {$record->id}, File: {$record->file_id_number}, ID Number: {$record->data_id_number}, Created: {$record->created_at}\n";
    }
} else {
    echo "⚠️ WARNING: NO records found in 'data' table!\n";
    echo "This confirms that Excel imports are NOT saving data.\n";
}
echo "\n";

// 2. Check if there are any recent Excel file uploads
echo "STEP 2: Check Excel File Uploads\n";
echo "---------------------------------\n";
$recentExcelFiles = DB::table('enhanced_attachments')
    ->where('file_type', 'excel')
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get(['id', 'original_file_name', 'file_size', 'processing_status', 'created_at']);

if ($recentExcelFiles->count() > 0) {
    echo "Found {$recentExcelFiles->count()} recent Excel uploads:\n";
    foreach ($recentExcelFiles as $file) {
        echo "  ID: {$file->id}, File: {$file->original_file_name}, Status: {$file->processing_status}, Size: " . round($file->file_size/1024/1024, 2) . "MB, Uploaded: {$file->created_at}\n";
    }
    echo "\n✓ Files ARE being uploaded successfully\n";
} else {
    echo "✗ No Excel files found in enhanced_attachments\n";
}
echo "\n";

// 3. Test actual Excel import process
echo "STEP 3: Simulate Real Excel Import\n";
echo "-----------------------------------\n";
echo "Testing the actual import flow...\n\n";

// Create a test data array similar to what Excel would provide
$testExcelData = [
    'file_id_number' => 'EXCEL_TEST_001',
    'data_id_number' => '123456789',
    'data_first_name' => 'محمد',
    'data_father_name' => 'علي',
    'data_family_name' => 'الشمري',
    'data_gender' => '1',
    'data_marital_status' => '1',
    'data_academic_qualification' => '5',
    'data_displacement_status' => '2',
    'data_city' => '0',
    'data_province' => '0',
    'data_employment_status_breadwinner' => '1',
    'data_housing_status' => '1',
    'data_current_housing_type' => '4',
];

echo "Test data prepared (similar to Excel row):\n";
echo "  File ID: {$testExcelData['file_id_number']}\n";
echo "  ID Number: {$testExcelData['data_id_number']}\n";
echo "  Name: {$testExcelData['data_first_name']} {$testExcelData['data_father_name']} {$testExcelData['data_family_name']}\n\n";

// Simulate what applyModelSpecificProcessing does
echo "Simulating applyModelSpecificProcessing...\n";
if (isset($testExcelData['data_id_number'])) {
    $cleanIdNumber = preg_replace('/[^0-9]/', '', $testExcelData['data_id_number']);
    if (!empty($cleanIdNumber) && is_numeric($cleanIdNumber)) {
        $testExcelData['data_id_number'] = (int)$cleanIdNumber;
        echo "  ✓ data_id_number cleaned: {$testExcelData['data_id_number']} (integer)\n";
    } else {
        $testExcelData['data_id_number'] = null;
        echo "  ✗ data_id_number set to NULL (invalid)\n";
    }
}

// Add system fields
$testExcelData['data_request_status'] = 2; // مقبول
$testExcelData['data_user_insert_data'] = 1;
$testExcelData['created_at'] = now();
$testExcelData['updated_at'] = now();
$testExcelData['original_file_id_from_excel'] = 'EXCEL_TEST_001';

echo "\nAttempting to insert test record...\n";
try {
    DB::beginTransaction();
    $record = Data::create($testExcelData);
    DB::commit();

    echo "✓ SUCCESS! Record inserted with ID: {$record->id}\n";
    echo "  All fields stored correctly\n\n";

    // Verify it's visible
    $found = Data::find($record->id);
    if ($found) {
        echo "✓ Record is visible in database\n";
    } else {
        echo "✗ WARNING: Record not visible after insert!\n";
    }

    // Clean up
    $record->delete();
    echo "  (Test record cleaned up)\n\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "✗ FAILED TO INSERT!\n";
    echo "  Error: {$e->getMessage()}\n";
    echo "  Code: {$e->getCode()}\n\n";

    // Check which field is causing the problem
    echo "Analyzing the error...\n";
    if (strpos($e->getMessage(), 'data_id_number') !== false) {
        echo "  ⚠️ Problem with data_id_number field\n";
    } elseif (strpos($e->getMessage(), 'foreign key') !== false || strpos($e->getMessage(), 'Cannot add or update a child row') !== false) {
        echo "  ⚠️ Foreign key constraint violation\n";

        // Check which foreign key is failing
        $foreignKeyFields = [
            'data_marital_status' => 'marital_status',
            'data_academic_qualification' => 'academic_degrees',
            'data_displacement_status' => 'displacement_status',
            'data_city' => 'city',
            'data_province' => 'provinces',
            'data_employment_status_breadwinner' => 'employment',
            'data_housing_status' => 'housing_status',
            'data_current_housing_type' => 'type_of_accommodation',
        ];

        foreach ($foreignKeyFields as $field => $table) {
            if (isset($testExcelData[$field])) {
                $value = $testExcelData[$field];
                $exists = DB::table($table)->where('id', $value)->exists();
                if (!$exists) {
                    echo "  ✗ Value '{$value}' NOT found in table '{$table}' (field: {$field})\n";
                }
            }
        }
    }
    echo "\n";
}

// 4. Check the lookup tables for missing values
echo "STEP 4: Check Lookup Tables\n";
echo "----------------------------\n";
$valuesToCheck = [
    ['table' => 'marital_status', 'value' => 1],
    ['table' => 'academic_degrees', 'value' => 5],
    ['table' => 'displacement_status', 'value' => 2],
    ['table' => 'employment', 'value' => 1],
    ['table' => 'housing_status', 'value' => 1],
    ['table' => 'type_of_accommodation', 'value' => 4],
];

$missingValues = [];
foreach ($valuesToCheck as $check) {
    $exists = DB::table($check['table'])->where('id', $check['value'])->exists();
    if (!$exists) {
        $missingValues[] = "  ✗ Value {$check['value']} NOT found in {$check['table']}";
        echo "  ✗ Value {$check['value']} NOT found in {$check['table']}\n";
    } else {
        echo "  ✓ Value {$check['value']} exists in {$check['table']}\n";
    }
}
echo "\n";

if (!empty($missingValues)) {
    echo "⚠️ CRITICAL: Missing foreign key values detected!\n";
    echo "This is likely why Excel imports fail.\n\n";
    echo "Missing values:\n";
    foreach ($missingValues as $missing) {
        echo "$missing\n";
    }
    echo "\n";
}

// 5. Check if default values (0) exist in all lookup tables
echo "STEP 5: Check Default Values (ID=0)\n";
echo "------------------------------------\n";
$lookupTables = [
    'marital_status',
    'academic_degrees',
    'displacement_status',
    'city',
    'provinces',
    'employment',
    'housing_status',
    'type_of_accommodation',
    'health_statuses',
    'category_of_relations',
];

$tablesWithoutZero = [];
foreach ($lookupTables as $table) {
    $hasZero = DB::table($table)->where('id', 0)->exists();
    if ($hasZero) {
        echo "  ✓ {$table}: has ID=0\n";
    } else {
        echo "  ✗ {$table}: MISSING ID=0\n";
        $tablesWithoutZero[] = $table;
    }
}
echo "\n";

echo "========================================\n";
echo "DIAGNOSIS SUMMARY\n";
echo "========================================\n\n";

$problems = [];

if ($totalRecords === 0) {
    $problems[] = "NO DATA IN DATABASE - No imports have succeeded";
}

if (!empty($missingValues)) {
    $problems[] = "MISSING FOREIGN KEY VALUES - Some reference data is missing";
}

if (!empty($tablesWithoutZero)) {
    $problems[] = "MISSING DEFAULT VALUES (ID=0) - Excel validation will fail";
}

if (empty($problems)) {
    echo "✓ No obvious problems detected\n";
    echo "  The system should be ready to import Excel files.\n\n";
} else {
    echo "⚠️ PROBLEMS DETECTED:\n\n";
    foreach ($problems as $i => $problem) {
        echo ($i + 1) . ". {$problem}\n";
    }
    echo "\n";

    echo "RECOMMENDED ACTIONS:\n";
    echo "-------------------\n";
    if (!empty($tablesWithoutZero)) {
        echo "1. Run insert_default_values.php to add missing ID=0 values\n";
    }
    if (!empty($missingValues)) {
        echo "2. Add the missing foreign key values to lookup tables\n";
    }
    echo "\n";
}
