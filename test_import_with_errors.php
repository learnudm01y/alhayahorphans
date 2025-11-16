<?php

/**
 * اختبار استيراد ملف مع وجود أخطاء متعمدة
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

echo "=== اختبار استيراد مع أخطاء متعمدة ===\n\n";

$filePath = 'I:\unit test\alhayahorphans\ASO - Copy\mohammed brother\Enet_Data_9-11-2025.xlsx';

try {
    // 1. التحقق
    $excelValidationService = app(ExcelValidationService::class);
    $validationResult = $excelValidationService->validateExcelFile($filePath, 'data', [
        'header_row' => true,
        'skip_empty_rows' => true
    ]);

    echo "نتائج التحقق:\n";
    echo "- إجمالي: " . $validationResult['statistics']['total_rows'] . "\n";
    echo "- صحيحة: " . $validationResult['statistics']['valid_rows'] . "\n";
    echo "- خاطئة: " . $validationResult['statistics']['invalid_rows'] . "\n\n";

    // 2. محاكاة استجابة Controller
    $response = [
        'success' => true,
        'message' => 'تم إدخال 2176 صف بنجاح',
        'import_result' => [
            'imported_rows' => 2176,
            'failed_count' => 0, // سنضيف أخطاء لاحقاً
            'duplicate_count' => $validationResult['statistics']['duplicate_rows'] ?? 0,
            'total_rows' => $validationResult['statistics']['total_rows'],
            'failed_records' => [], // سنضيف أخطاء لاحقاً
        ],
        'validation_result' => $validationResult,
        'file_info' => [
            'id' => 123,
            'path' => 'excel_files/test.xlsx',
            'original_name' => 'Enet_Data_9-11-2025.xlsx'
        ]
    ];

    echo "=== محاكاة استجابة Controller ===\n";
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

    echo "تنسيق JSON للاستخدام في JavaScript:\n";
    echo "```javascript\n";
    echo "const data = " . json_encode($response, JSON_UNESCAPED_UNICODE) . ";\n";
    echo "displayImportResults(data);\n";
    echo "```\n";

} catch (\Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}

echo "\n=== انتهى ===\n";
