<?php

/**
 * اختبار استيراد الملف الكامل مع تتبع الأخطاء
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\ExcelValidationService;
use App\Services\ExcelImportService;

// تهيئة Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== اختبار استيراد ملف Excel الكامل مع تتبع الأخطاء ===\n\n";

$filePath = 'I:\unit test\alhayahorphans\ASO - Copy\mohammed brother\Enet_Data_9-11-2025.xlsx';

if (!file_exists($filePath)) {
    die("❌ الملف غير موجود: $filePath\n");
}

echo "✅ الملف موجود: " . basename($filePath) . "\n";
echo "📊 حجم الملف: " . round(filesize($filePath) / 1024, 2) . " KB\n\n";

try {
    // 1. التحقق أولاً
    echo "--- المرحلة 1: التحقق من الملف ---\n";
    $excelValidationService = app(ExcelValidationService::class);
    $validationResult = $excelValidationService->validateExcelFile($filePath, 'data', [
        'header_row' => true,
        'skip_empty_rows' => true
    ]);

    echo "إجمالي الصفوف: " . $validationResult['statistics']['total_rows'] . "\n";
    echo "صفوف صحيحة: " . $validationResult['statistics']['valid_rows'] . "\n";
    echo "صفوف خاطئة: " . $validationResult['statistics']['invalid_rows'] . "\n";
    echo "صفوف مكررة: " . ($validationResult['statistics']['duplicate_rows'] ?? 0) . "\n\n";

    // 2. الحصول على الصفوف الصحيحة
    echo "--- المرحلة 2: الحصول على الصفوف الصحيحة ---\n";
    $validRows = $excelValidationService->getValidRowsForInsertion();
    echo "عدد الصفوف الصالحة للإدخال: " . count($validRows) . "\n\n";

    if (empty($validRows)) {
        die("❌ لا توجد صفوف صالحة للإدخال!\n");
    }

    // 3. معالجة الصفوف الصحيحة
    echo "--- المرحلة 3: معالجة وإدخال الصفوف ---\n";
    $excelImportService = app(ExcelImportService::class);

    DB::beginTransaction();

    $insertedCount = 0;
    $failedInserts = [];
    $sampleProcessedRows = [];

    foreach ($validRows as $index => $rowData) {
        try {
            // معالجة الصف
            $processedRow = $excelImportService->processRowData($rowData, 'data');

            // حفظ عينات من الصفوف المعالجة (أول 5)
            if ($index < 5) {
                $sampleProcessedRows[] = [
                    'index' => $index + 1,
                    'file_id' => $processedRow['file_id_number'] ?? 'N/A',
                    'identity' => $processedRow['data_id_number'] ?? 'N/A',
                    'name' => ($processedRow['data_first_name'] ?? '') . ' ' . ($processedRow['data_family_name'] ?? ''),
                    'status' => $processedRow['data_request_status'] ?? 'N/A'
                ];
            }

            // إدخال الصف
            DB::table('data')->insert($processedRow);
            $insertedCount++;

            // تقرير التقدم كل 100 صف
            if ($insertedCount % 100 == 0) {
                echo "✓ تم إدخال $insertedCount صف...\n";
            }

        } catch (\Exception $e) {
            $failedInserts[] = [
                'row_index' => $index + 1,
                'identity' => $rowData['data_id_number'] ?? 'N/A',
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'data' => array_slice($rowData, 0, 5) // أول 5 حقول فقط
            ];

            // عرض أول 5 أخطاء
            if (count($failedInserts) <= 5) {
                echo "❌ خطأ في الصف " . ($index + 1) . ": " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\n--- المرحلة 4: النتائج النهائية ---\n";
    echo "✅ تم إدخال: $insertedCount صف\n";
    echo "❌ فشل إدخال: " . count($failedInserts) . " صف\n";
    echo "📊 نسبة النجاح: " . round(($insertedCount / count($validRows)) * 100, 2) . "%\n\n";

    if ($insertedCount > 0) {
        echo "--- عينات من الصفوف المدخلة (أول 5) ---\n";
        foreach ($sampleProcessedRows as $sample) {
            echo "الصف {$sample['index']}: رقم الملف={$sample['file_id']}, رقم الهوية={$sample['identity']}, الاسم={$sample['name']}, الحالة={$sample['status']}\n";
        }
        echo "\n";

        echo "هل تريد تأكيد الإدخال (commit)؟ [y/n]: ";
        $handle = fopen("php://stdin", "r");
        $confirm = trim(fgets($handle));
        fclose($handle);

        if (strtolower($confirm) === 'y') {
            DB::commit();
            echo "✅ تم تأكيد الإدخال بنجاح!\n";

            // التحقق من قاعدة البيانات
            $lastInserted = DB::table('data')
                ->orderBy('id', 'desc')
                ->limit(5)
                ->get(['id', 'file_id_number', 'data_id_number', 'data_first_name', 'data_family_name', 'data_request_status']);

            echo "\n--- آخر 5 سجلات في قاعدة البيانات ---\n";
            foreach ($lastInserted as $record) {
                echo "ID={$record->id}, ملف={$record->file_id_number}, هوية={$record->data_id_number}, اسم={$record->data_first_name} {$record->data_family_name}, حالة={$record->data_request_status}\n";
            }
        } else {
            DB::rollBack();
            echo "⚠️ تم إلغاء الإدخال (rollback)\n";
        }
    } else {
        DB::rollBack();
        echo "❌ لم يتم إدخال أي سجل! تم إلغاء العملية (rollback)\n";

        if (!empty($failedInserts)) {
            echo "\n--- تفاصيل الأخطاء (أول 10) ---\n";
            foreach (array_slice($failedInserts, 0, 10) as $failed) {
                echo "الصف {$failed['row_index']} (هوية: {$failed['identity']}): {$failed['error']}\n";
                if (!empty($failed['data'])) {
                    echo "  البيانات: " . json_encode($failed['data'], JSON_UNESCAPED_UNICODE) . "\n";
                }
            }
        }
    }

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ خطأ فادح: " . $e->getMessage() . "\n";
    echo "الموقع: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "تتبع الخطأ:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== انتهى الاختبار ===\n";
