<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\ExcelValidationService;

$filePath = 'I:\\unit test\\alhayahorphans\\ASO - Copy\\storage\\app\\public\\excel_files\\a0Kzz51H4OYdH6KTqT8iArtj1p6R4RQi4jOc6G0o.xlsx';

if (!file_exists($filePath)) {
    die("❌ الملف غير موجود\n");
}

echo "📂 الملف موجود\n\n";

$validationService = new ExcelValidationService();
$validationResult = $validationService->validateExcelFile($filePath, 'data');

echo "📊 نتائج التحقق:\n";
echo "  - إجمالي الصفوف: " . ($validationResult['statistics']['total_rows'] ?? 0) . "\n";
echo "  - صفوف صحيحة: " . ($validationResult['statistics']['valid_rows'] ?? 0) . "\n";
echo "  - صفوف خاطئة: " . ($validationResult['statistics']['invalid_rows'] ?? 0) . "\n";
echo "  - صفوف مكررة: " . ($validationResult['statistics']['duplicate_rows'] ?? 0) . "\n\n";

// عرض أول 10 أخطاء
if (!empty($validationResult['invalid_rows'])) {
    echo "❌ عينة من الأخطاء (أول 10 صفوف):\n\n";

    $errors = array_slice($validationResult['invalid_rows'], 0, 10);

    foreach ($errors as $i => $error) {
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "الصف رقم: " . ($error['row_number'] ?? '?') . "\n";

        if (isset($error['data'])) {
            echo "البيانات:\n";
            echo "  - رقم الهوية: " . ($error['data']['data_id_number'] ?? 'null') . "\n";
            echo "  - الاسم: " . ($error['data']['data_first_name'] ?? 'null') . "\n";
            echo "  - تاريخ الميلاد: " . ($error['data']['data_birth_date'] ?? 'null') . "\n";
        }

        if (isset($error['errors'])) {
            echo "الأخطاء:\n";
            foreach ($error['errors'] as $err) {
                if (is_array($err)) {
                    echo "  ⚠️  " . ($err['message'] ?? json_encode($err, JSON_UNESCAPED_UNICODE)) . "\n";
                    if (isset($err['field'])) {
                        echo "      الحقل: " . $err['field'] . "\n";
                    }
                    if (isset($err['type'])) {
                        echo "      النوع: " . $err['type'] . "\n";
                    }
                } else {
                    echo "  ⚠️  " . $err . "\n";
                }
            }
        }

        echo "\n";
    }
}

echo "\n✅ انتهى الفحص\n";
