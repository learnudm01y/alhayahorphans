<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\ExcelValidationService;
use App\Models\Data;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "========================================\n";
echo "🧪 اختبار إدخال البيانات مع status = 2\n";
echo "========================================\n\n";

try {
    // استخدام ملف Excel الموجود
    $excelFile = storage_path('app/public/documents/excel/1762772799_Enet_Data_9-11-2025.xlsx');

    if (!file_exists($excelFile)) {
        echo "❌ الملف غير موجود: $excelFile\n";
        exit(1);
    }

    echo "📄 الملف: " . basename($excelFile) . "\n\n";

    // التحقق من الملف
    $validationService = new ExcelValidationService();
    $result = $validationService->validateExcelFile($excelFile, 'data', [
        'header_row' => true,
        'skip_empty_rows' => true
    ]);

    echo "📊 نتائج التحقق:\n";
    echo "  ✅ صفوف صحيحة: " . ($result['statistics']['valid_rows'] ?? 0) . "\n";
    echo "  ❌ صفوف خاطئة: " . ($result['statistics']['invalid_rows'] ?? 0) . "\n";
    echo "  ⚠️  تحذيرات: " . ($result['total_warnings_count'] ?? 0) . "\n\n";

    // الحصول على الصفوف الصحيحة
    $validRows = $validationService->getValidRowsForInsertion();

    if (empty($validRows)) {
        echo "❌ لا توجد صفوف صحيحة للإدخال!\n";
        exit(1);
    }

    echo "🔢 عدد الصفوف الجاهزة للإدخال: " . count($validRows) . "\n\n";

    // أخذ أول صف للاختبار
    $testRow = $validRows[0];

    echo "📝 بيانات الصف الأول:\n";
    echo "  - data_id_number: " . ($testRow['data_id_number'] ?? 'null') . "\n";
    echo "  - data_first_name: " . ($testRow['data_first_name'] ?? 'null') . "\n";
    echo "  - data_father_name: " . ($testRow['data_father_name'] ?? 'null') . "\n";
    echo "  - data_birth_date (الأصلي): " . ($testRow['data_birth_date'] ?? 'null') . "\n";
    echo "  - data_request_status (قبل): " . ($testRow['data_request_status'] ?? 'null') . "\n\n";

    // فحص تنسيق التاريخ وتحويله إذا لزم الأمر
    if (isset($testRow['data_birth_date']) && !empty($testRow['data_birth_date'])) {
        $birthDate = $testRow['data_birth_date'];

        // التحقق من التنسيق DD-MM-YYYY وتحويله إلى YYYY-MM-DD
        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $birthDate)) {
            try {
                $dateObj = DateTime::createFromFormat('d-m-Y', $birthDate);
                if ($dateObj) {
                    $testRow['data_birth_date'] = $dateObj->format('Y-m-d');
                    echo "✓ تم تحويل التاريخ من $birthDate إلى {$testRow['data_birth_date']}\n\n";
                }
            } catch (Exception $e) {
                echo "⚠ فشل تحويل التاريخ: " . $e->getMessage() . "\n\n";
            }
        }
    }

    // تعيين data_request_status = 2 إذا كان فارغًا
    if (!isset($testRow['data_request_status']) || empty($testRow['data_request_status'])) {
        $testRow['data_request_status'] = 2;
        echo "✓ تم تعيين data_request_status = 2\n\n";
    }

    // محاولة الإدخال
    DB::beginTransaction();

    try {
        // حذف السجل إذا كان موجودًا للاختبار
        if (isset($testRow['data_id_number'])) {
            Data::where('data_id_number', $testRow['data_id_number'])->delete();
        }

        // الإدخال
        $record = Data::create($testRow);

        DB::commit();

        echo "========================================\n";
        echo "✅ نجح الإدخال!\n";
        echo "========================================\n\n";

        echo "معلومات السجل المُدخل:\n";
        echo "  - ID: {$record->id}\n";
        echo "  - data_id_number: {$record->data_id_number}\n";
        echo "  - data_request_status: {$record->data_request_status}\n";
        echo "  - file_id_number: {$record->file_id_number}\n\n";

        // حذف السجل التجريبي
        $record->delete();
        echo "✓ تم حذف السجل التجريبي\n";

    } catch (\Exception $e) {
        DB::rollBack();

        echo "========================================\n";
        echo "❌ فشل الإدخال!\n";
        echo "========================================\n\n";

        echo "الخطأ: " . $e->getMessage() . "\n";
        echo "الكود: " . $e->getCode() . "\n\n";

        // فحص نوع الخطأ
        if (strpos($e->getMessage(), 'foreign key') !== false ||
            strpos($e->getMessage(), 'Integrity constraint') !== false) {
            echo "🔍 السبب: خطأ في المفاتيح الخارجية\n\n";

            // فحص القيم
            echo "قيم المفاتيح الخارجية:\n";
            $foreignKeys = [
                'data_marital_status', 'data_academic_qualification',
                'data_displacement_status', 'data_city', 'data_province',
                'data_health_status', 'data_employment_status_breadwinner',
                'data_housing_status', 'data_current_housing_type', 'data_request_status'
            ];

            foreach ($foreignKeys as $key) {
                if (isset($testRow[$key])) {
                    echo "  - $key: {$testRow[$key]}\n";
                }
            }
        }

        throw $e;
    }

} catch (\Exception $e) {
    echo "\n❌ خطأ عام: " . $e->getMessage() . "\n";
    exit(1);
}
