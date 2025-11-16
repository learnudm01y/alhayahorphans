<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\ExcelValidationService;
use App\Services\ExcelImportService;
use App\Models\Data;
use Illuminate\Support\Facades\DB;

echo "========================================\n";
echo "✅ اختبار نهائي: إدخال البيانات الكامل\n";
echo "========================================\n\n";

try {
    $excelFile = storage_path('app/public/documents/excel/1762772799_Enet_Data_9-11-2025.xlsx');

    if (!file_exists($excelFile)) {
        echo "❌ الملف غير موجود\n";
        exit(1);
    }

    echo "📄 الملف: 1762772799_Enet_Data_9-11-2025.xlsx\n\n";

    // 1. التحقق
    echo "⏳ المرحلة 1: التحقق من الملف...\n";
    $validationService = new ExcelValidationService();
    $result = $validationService->validateExcelFile($excelFile, 'data', [
        'header_row' => true,
        'skip_empty_rows' => true
    ]);

    echo "  ✅ صفوف صحيحة: " . ($result['statistics']['valid_rows'] ?? 0) . "\n";
    echo "  ❌ صفوف خاطئة: " . ($result['statistics']['invalid_rows'] ?? 0) . "\n";
    echo "  ⚠️  تحذيرات: " . ($result['total_warnings_count'] ?? 0) . "\n\n";

    // 2. الحصول على الصفوف الصحيحة
    $validRows = $validationService->getValidRowsForInsertion();

    if (empty($validRows)) {
        echo "❌ لا توجد صفوف صحيحة للإدخال!\n";
        exit(1);
    }

    echo "🔢 عدد الصفوف الجاهزة: " . count($validRows) . "\n\n";

    // 3. معالجة وإدخال أول 5 صفوف كاختبار
    echo "⏳ المرحلة 2: معالجة وإدخال أول 5 صفوف...\n\n";

    $importService = new ExcelImportService();
    DB::beginTransaction();

    $inserted = 0;
    $failed = 0;
    $errors = [];

    foreach (array_slice($validRows, 0, 5) as $index => $rowData) {
        try {
            echo "▶ صف " . ($index + 1) . ":\n";
            echo "  - ID: " . ($rowData['data_id_number'] ?? 'null') . "\n";

            // معالجة الصف (تحويل القيم، إضافة الحقول النظامية)
            $processedRow = $importService->processRowData($rowData, 'data');

            echo "  - status: " . ($processedRow['data_request_status'] ?? 'null') . "\n";
            echo "  - birth_date: " . ($processedRow['data_birth_date'] ?? 'null') . "\n";

            // حذف إذا كان موجودًا
            if (isset($processedRow['data_id_number'])) {
                Data::where('data_id_number', $processedRow['data_id_number'])->delete();
            }

            // الإدخال
            $record = Data::create($processedRow);
            $inserted++;

            echo "  ✅ نجح الإدخال (ID: {$record->id})\n\n";

        } catch (\Exception $e) {
            $failed++;
            $errors[] = [
                'row' => $index + 1,
                'error' => $e->getMessage()
            ];
            echo "  ❌ فشل: " . substr($e->getMessage(), 0, 100) . "...\n\n";
        }
    }

    DB::commit();

    echo "========================================\n";
    echo "📊 النتائج النهائية:\n";
    echo "========================================\n\n";

    echo "  ✅ نجحت: {$inserted} صف\n";
    echo "  ❌ فشلت: {$failed} صف\n\n";

    if ($inserted > 0) {
        echo "✅ تم إدخال البيانات بنجاح!\n\n";

        // عرض أول سجل
        $firstRecord = Data::orderBy('id', 'desc')->first();
        if ($firstRecord) {
            echo "📝 آخر سجل مُدخل:\n";
            echo "  - ID: {$firstRecord->id}\n";
            echo "  - data_id_number: {$firstRecord->data_id_number}\n";
            echo "  - data_request_status: {$firstRecord->data_request_status}\n";
            echo "  - data_birth_date: {$firstRecord->data_birth_date}\n";
            echo "  - الاسم: {$firstRecord->data_first_name} {$firstRecord->data_father_name}\n\n";
        }

        // تنظيف
        echo "🗑️  تنظيف السجلات التجريبية...\n";
        foreach (array_slice($validRows, 0, $inserted) as $rowData) {
            if (isset($rowData['data_id_number'])) {
                Data::where('data_id_number', $rowData['data_id_number'])->delete();
            }
        }
        echo "✅ تم التنظيف\n";
    }

    if (!empty($errors)) {
        echo "\n❌ الأخطاء:\n";
        foreach ($errors as $error) {
            echo "  - صف {$error['row']}: {$error['error']}\n";
        }
    }

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ خطأ عام: " . $e->getMessage() . "\n";
    exit(1);
}
