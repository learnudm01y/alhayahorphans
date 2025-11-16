<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\ExcelValidationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "========================================\n";
echo "🧪 اختبار إدخال الصفوف الصحيحة\n";
echo "========================================\n\n";

$excelFile = storage_path('app/public/documents/excel/1762772799_Enet_Data_9-11-2025.xlsx');

if (!file_exists($excelFile)) {
    echo "❌ الملف غير موجود: {$excelFile}\n";
    exit;
}

echo "📄 الملف: 1762772799_Enet_Data_9-11-2025.xlsx\n\n";

// تنفيذ التحقق
$validationService = new ExcelValidationService();

try {
    echo "⏳ جار التحقق من الملف...\n";
    $result = $validationService->validateExcelFile($excelFile, 'data', [
        'header_row' => true,
        'skip_empty_rows' => true
    ]);

    echo "✅ التحقق مكتمل\n\n";

    // الحصول على الصفوف الصحيحة
    $validRows = $validationService->getValidRowsForInsertion();

    echo "📊 الإحصائيات:\n";
    echo "  ✅ صفوف صحيحة: " . count($validRows) . "\n";
    echo "  ❌ صفوف خاطئة: " . ($result['statistics']['invalid_rows'] ?? 0) . "\n";
    echo "  ⚠️  تحذيرات: " . ($result['total_warnings_count'] ?? 0) . "\n\n";

    if (count($validRows) == 0) {
        echo "❌ لا توجد صفوف صحيحة للإدخال!\n";
        exit;
    }

    // عد السجلات الحالية
    $before = DB::table('data')->count();
    echo "📝 عدد السجلات في جدول data قبل الإدخال: {$before}\n\n";

    // اختبار إدخال أول 3 صفوف
    echo "⏳ جار إدخال أول 3 صفوف صحيحة...\n\n";

    $inserted = 0;
    $failed = 0;
    $errors = [];

    foreach (array_slice($validRows, 0, 3) as $index => $rowData) {
        try {
            echo "▶ صف " . ($index + 1) . ":\n";
            echo "  - data_id_number: " . ($rowData['data_id_number'] ?? 'غير محدد') . "\n";
            echo "  - الاسم: " . ($rowData['data_first_name'] ?? '') . " " . ($rowData['data_father_name'] ?? '') . "\n";

            // تطبيق auto-conversion للمفاتيح الخارجية
            $foreignKeyFields = [
                'data_marital_status' => 'marital_status',
                'data_academic_qualification' => 'academic_degrees',
                'data_displacement_status' => 'general_category',
                'data_city' => 'city',
                'data_province' => 'provinces',
                'data_health_status' => 'health_statuses',
                'data_employment_status_breadwinner' => 'employment',
                'data_housing_status' => 'housing_status',
                'data_current_housing_type' => 'type_of_accommodation',
                'data_relationship' => 'category_of_relations'
            ];

            $conversions = [];
            foreach ($foreignKeyFields as $field => $table) {
                if (isset($rowData[$field]) && !empty($rowData[$field])) {
                    $value = $rowData[$field];
                    $exists = DB::table($table)->where('id', $value)->exists();

                    if (!$exists) {
                        $conversions[] = "{$field}: {$value} → 0";
                        $rowData[$field] = 0;
                    }
                }
            }

            if (!empty($conversions)) {
                echo "  ⚙️  تحويلات: " . implode(', ', $conversions) . "\n";
            }

            // تأكد من وجود الحقول المطلوبة
            if (!isset($rowData['data_request_status']) || empty($rowData['data_request_status'])) {
                $rowData['data_request_status'] = DB::table('request_status')->where('name', 'مقبول')->value('id') ?? 0;
            }

            // محاولة الإدخال
            DB::table('data')->insert($rowData);
            $inserted++;
            echo "  ✅ نجح الإدخال\n\n";

        } catch (\Exception $e) {
            $failed++;
            $error = $e->getMessage();
            $errors[] = [
                'row' => $index + 1,
                'data_id_number' => $rowData['data_id_number'] ?? 'غير محدد',
                'error' => $error
            ];
            echo "  ❌ فشل الإدخال: {$error}\n\n";
        }
    }

    // عد السجلات بعد الإدخال
    $after = DB::table('data')->count();
    echo "========================================\n";
    echo "📊 النتائج:\n";
    echo "========================================\n\n";
    echo "  📝 السجلات قبل: {$before}\n";
    echo "  📝 السجلات بعد: {$after}\n";
    echo "  ➕ السجلات المضافة: " . ($after - $before) . "\n";
    echo "  ✅ نجحت: {$inserted}\n";
    echo "  ❌ فشلت: {$failed}\n\n";

    if (!empty($errors)) {
        echo "❌ تفاصيل الأخطاء:\n";
        foreach ($errors as $error) {
            echo "  - صف {$error['row']} (ID: {$error['data_id_number']}): {$error['error']}\n";
        }
        echo "\n";
    }

    // تنظيف السجلات المدخلة للاختبار
    if ($inserted > 0) {
        echo "🗑️  تنظيف السجلات المدخلة...\n";
        foreach (array_slice($validRows, 0, $inserted) as $rowData) {
            if (isset($rowData['data_id_number'])) {
                DB::table('data')->where('data_id_number', $rowData['data_id_number'])->delete();
            }
        }
        echo "✅ تم تنظيف السجلات\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
