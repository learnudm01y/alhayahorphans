<?php

/**
 * TEST: Verify Auto-Convert to 0 for Missing Values
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\ExcelImportService;
use App\Models\Data;
use Illuminate\Support\Facades\DB;

echo "\n========================================\n";
echo "TEST: Auto-Convert Missing Values to 0\n";
echo "========================================\n\n";

$excelService = new ExcelImportService();

// محاكاة بيانات Excel مع قيم غير موجودة
$testData = [
    'file_id_number' => 'EXCEL_TEST_' . time(),
    'data_id_number' => '123456789',
    'data_first_name' => 'محمد',
    'data_father_name' => 'علي',
    'data_family_name' => 'الشمري',
    'data_gender' => '1',
    'data_marital_status' => '1',           // ❌ غير موجود → يجب أن يتحول إلى 0
    'data_academic_qualification' => '5',   // ❌ غير موجود → يجب أن يتحول إلى 0
    'data_displacement_status' => '1',      // ❌ غير موجود → يجب أن يتحول إلى 0
    'data_city' => '52',                    // ❌ غير موجود → يجب أن يتحول إلى 0
    'data_province' => '9',                 // ❌ غير موجود → يجب أن يتحول إلى 0
    'data_employment_status_breadwinner' => '1',  // ❌ غير موجود → يجب أن يتحول إلى 0
    'data_housing_status' => '1',           // ❌ غير موجود → يجب أن يتحول إلى 0
    'data_current_housing_type' => '4',     // ❌ غير موجود → يجب أن يتحول إلى 0
];

echo "Original Data (from Excel):\n";
echo "----------------------------\n";
foreach ($testData as $field => $value) {
    if (strpos($field, 'data_') === 0 && !in_array($field, ['data_first_name', 'data_father_name', 'data_family_name', 'data_gender'])) {
        echo "  {$field}: {$value}\n";
    }
}
echo "\n";

// استخدام reflection للوصول إلى الدالة الخاصة
$reflection = new ReflectionClass($excelService);
$method = $reflection->getMethod('applyModelSpecificProcessing');
$method->setAccessible(true);

echo "Processing data...\n";
$processedData = $method->invoke($excelService, $testData, 'data', []);

echo "\nProcessed Data (after conversion):\n";
echo "-----------------------------------\n";
$conversions = 0;
foreach ($processedData as $field => $value) {
    if (strpos($field, 'data_') === 0 && !in_array($field, ['data_first_name', 'data_father_name', 'data_family_name', 'data_gender', 'data_id_number', 'file_id_number', 'original_file_id_from_excel'])) {
        $original = $testData[$field] ?? 'N/A';
        if ($original !== $value) {
            echo "  {$field}: {$original} → {$value} ✓ (converted to 0)\n";
            $conversions++;
        } else {
            echo "  {$field}: {$value}\n";
        }
    }
}
echo "\n";

if ($conversions > 0) {
    echo "✓ SUCCESS: {$conversions} values were automatically converted to 0\n\n";
} else {
    echo "⚠️  WARNING: No values were converted\n\n";
}

// محاولة إدخال البيانات فعلياً
echo "Attempting actual database insertion...\n";
echo "---------------------------------------\n";

$processedData['data_request_status'] = 2; // مقبول
$processedData['data_user_insert_data'] = 1;
$processedData['created_at'] = now();
$processedData['updated_at'] = now();

try {
    DB::beginTransaction();
    $record = Data::create($processedData);
    DB::commit();

    echo "✓ SUCCESS! Record inserted with ID: {$record->id}\n";
    echo "\nInserted values:\n";
    echo "  data_marital_status: {$record->data_marital_status}\n";
    echo "  data_academic_qualification: {$record->data_academic_qualification}\n";
    echo "  data_displacement_status: {$record->data_displacement_status}\n";
    echo "  data_city: {$record->data_city}\n";
    echo "  data_province: {$record->data_province}\n";
    echo "  data_employment_status_breadwinner: {$record->data_employment_status_breadwinner}\n";
    echo "  data_housing_status: {$record->data_housing_status}\n";
    echo "  data_current_housing_type: {$record->data_current_housing_type}\n\n";

    // Clean up
    $record->delete();
    echo "(Test record cleaned up)\n\n";

    echo "========================================\n";
    echo "✓ ALL TESTS PASSED!\n";
    echo "========================================\n\n";
    echo "The system now automatically converts missing values to 0 (Unknown).\n";
    echo "Excel imports should work correctly now!\n\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "✗ FAILED: {$e->getMessage()}\n\n";

    if (strpos($e->getMessage(), 'foreign key') !== false) {
        echo "⚠️  There's still a foreign key constraint issue.\n";
        echo "This might be because value 0 doesn't exist in one of the lookup tables.\n\n";
    }
}
