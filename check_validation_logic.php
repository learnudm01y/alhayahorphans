<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\ExcelValidationService;
use Illuminate\Support\Facades\Log;

echo "========================================\n";
echo "🔍 فحص منطق التحقق من ملف Excel\n";
echo "========================================\n\n";

// البحث عن آخر ملف Excel
$latestFile = storage_path('app/public/documents/excel/1762772799_Enet_Data_9-11-2025.xlsx');

if (!file_exists($latestFile)) {
    echo "❌ لم يتم العثور على ملف Excel\n";
    echo "المسار: " . $latestFile . "\n";
    exit;
}

echo "📄 الملف المستخدم: " . basename($latestFile) . "\n\n";

// تنفيذ التحقق
$validationService = new ExcelValidationService();

try {
    $result = $validationService->validateExcelFile($latestFile, 'data', [
        'header_row' => true,
        'skip_empty_rows' => true
    ]);

    echo "📊 إحصائيات التحقق:\n";
    echo "  ✅ صفوف صحيحة: " . ($result['statistics']['valid_rows'] ?? 0) . "\n";
    echo "  ❌ صفوف خاطئة: " . ($result['statistics']['invalid_rows'] ?? 0) . "\n";
    echo "  ⚠️  تحذيرات: " . ($result['total_warnings_count'] ?? 0) . "\n";
    echo "  📝 إجمالي الصفوف: " . ($result['statistics']['total_rows'] ?? 0) . "\n\n";

    // فحص validRows
    $validRows = $validationService->getValidRowsForInsertion();
    echo "🔢 عدد الصفوف التي ستُدخل (validRows): " . count($validRows) . "\n\n";

    // فحص التحذيرات
    if (!empty($result['warnings'])) {
        echo "⚠️  أول 5 تحذيرات:\n";
        foreach (array_slice($result['warnings'], 0, 5) as $warning) {
            echo "  - الصف {$warning['row']}: {$warning['message']}\n";
        }
        echo "\n";
    }

    // فحص الأخطاء
    if (!empty($result['invalid_rows'])) {
        echo "❌ أول 3 صفوف خاطئة:\n";
        foreach (array_slice($result['invalid_rows'], 0, 3) as $invalidRow) {
            echo "  - الصف {$invalidRow['row_number']}:\n";
            foreach ($invalidRow['errors'] as $error) {
                $errorType = isset($error['type']) ? $error['type'] : 'general';
                echo "    * نوع الخطأ: {$errorType}\n";
                echo "    * الرسالة: " . ($error['error'] ?? $error['message'] ?? 'غير محدد') . "\n";
            }
            echo "\n";
        }
    }

    echo "========================================\n";
    echo "🎯 التشخيص:\n";
    echo "========================================\n\n";

    if (count($validRows) == 0) {
        echo "❌ المشكلة: لا توجد صفوف في validRows!\n\n";
        echo "📋 السبب المحتمل:\n";
        echo "  - الصفوف التي بها تحذيرات فقط لا تُضاف إلى validRows\n";
        echo "  - ExcelValidationService يضيف الصف إلى validRows فقط إذا كانت \$rowErrors فارغة\n";
        echo "  - لكن التحذيرات تُخزن في مصفوفة منفصلة (\$this->warnings)\n\n";

        echo "💡 الحل:\n";
        echo "  - تعديل منطق validateRow() لفصل الأخطاء عن التحذيرات\n";
        echo "  - إضافة الصفوف التي بها تحذيرات فقط إلى validRows\n";
        echo "  - الصفوف التي بها أخطاء فقط تذهب إلى invalidRows\n";
    } else {
        echo "✅ يوجد " . count($validRows) . " صف في validRows\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ في التحقق: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
