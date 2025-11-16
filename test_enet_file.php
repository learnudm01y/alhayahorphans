<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\ExcelValidationService;
use App\Services\ExcelImportService;
use PhpOffice\PhpSpreadsheet\IOFactory;

// استخدام آخر ملف
$filePath = 'I:\\unit test\\alhayahorphans\\ASO - Copy\\storage\\app\\public\\documents\\excel\\1762772799_Enet_Data_9-11-2025.xlsx';

if (!file_exists($filePath)) {
    die("❌ الملف غير موجود: $filePath\n");
}

echo "📂 الملف موجود\n";
echo "📏 حجم الملف: " . filesize($filePath) . " بايت\n\n";

// أولاً: فحص محتويات الملف
echo "═══════════════════════════════════════════════════════\n";
echo "  الجزء 1: فحص محتويات ملف Excel الفعلية\n";
echo "═══════════════════════════════════════════════════════\n\n";

try {
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    $highestRow = $sheet->getHighestRow();
    $highestColumn = $sheet->getHighestColumn();

    echo "📊 معلومات الملف:\n";
    echo "  - عدد الصفوف: $highestRow\n";
    echo "  - آخر عمود: $highestColumn\n\n";

    // قراءة العناوين
    echo "📋 العناوين (أول 15 عمود):\n";
    $headers = [];
    $columnIndex = 0;
    foreach (range('A', 'Z') as $col) {
        if ($columnIndex >= 15) break;
        $value = $sheet->getCell($col . '1')->getValue();
        $headers[] = $value;
        echo "  [$col] " . ($value ?? 'null') . "\n";
        $columnIndex++;
        if ($col == $highestColumn) break;
    }

    echo "\n📝 أول صف بيانات (الصف 2):\n";
    $columnIndex = 0;
    foreach (range('A', 'Z') as $col) {
        if ($columnIndex >= 15) break;
        $value = $sheet->getCell($col . '2')->getValue();
        $header = $headers[$columnIndex] ?? "عمود $col";
        echo "  $header: " . ($value ?? 'null') . "\n";
        $columnIndex++;
        if ($col == $highestColumn) break;
    }

} catch (\Exception $e) {
    echo "❌ خطأ في قراءة الملف: " . $e->getMessage() . "\n";
    exit;
}

echo "\n\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  الجزء 2: اختبار التحقق من الصحة (Validation)\n";
echo "═══════════════════════════════════════════════════════\n\n";

$validationService = new ExcelValidationService();
$validationResult = $validationService->validateExcelFile($filePath, 'data');

echo "📊 نتائج التحقق:\n";
echo "  - إجمالي الصفوف: " . ($validationResult['statistics']['total_rows'] ?? 0) . "\n";
echo "  - صفوف صحيحة: " . ($validationResult['statistics']['valid_rows'] ?? 0) . " ✅\n";
echo "  - صفوف خاطئة: " . ($validationResult['statistics']['invalid_rows'] ?? 0) . " ❌\n";
echo "  - صفوف مكررة: " . ($validationResult['statistics']['duplicate_rows'] ?? 0) . " ⚠️\n\n";

if (empty($validationResult['valid_rows'])) {
    echo "❌ لا توجد صفوف صحيحة للإدخال!\n\n";

    // عرض أول 5 أخطاء
    if (!empty($validationResult['invalid_rows'])) {
        echo "🔴 أول 5 أخطاء:\n\n";

        $errors = array_slice($validationResult['invalid_rows'], 0, 5);

        foreach ($errors as $error) {
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "الصف: " . ($error['row_number'] ?? '?') . "\n";

            if (isset($error['errors']) && is_array($error['errors'])) {
                foreach ($error['errors'] as $err) {
                    if (is_array($err)) {
                        echo "  ❌ " . ($err['error'] ?? json_encode($err, JSON_UNESCAPED_UNICODE)) . "\n";
                    }
                }
            }
        }
    }

    exit;
}

echo "✅ التحقق نجح! يوجد " . count($validationResult['valid_rows']) . " صف صحيح\n\n";

echo "═══════════════════════════════════════════════════════\n";
echo "  الجزء 3: اختبار الإدخال إلى قاعدة البيانات\n";
echo "═══════════════════════════════════════════════════════\n\n";

$importService = new ExcelImportService();

// اختبار إدخال أول 3 صفوف فقط
$testRows = array_slice($validationResult['valid_rows'], 0, 3);

echo "🔄 محاولة إدخال " . count($testRows) . " صفوف...\n\n";

DB::beginTransaction();

$insertedCount = 0;
$failedInserts = [];

foreach ($testRows as $index => $row) {
    $rowNumber = $row['row_number'];
    $rowData = $row['data'];

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🔄 الصف رقم: $rowNumber\n";
    echo "📝 البيانات الأولية:\n";
    echo "  - رقم الهوية: " . ($rowData['data_id_number'] ?? 'null') . "\n";
    echo "  - الاسم: " . ($rowData['data_first_name'] ?? 'null') . "\n";
    echo "  - تاريخ الميلاد: " . ($rowData['data_birth_date'] ?? 'null') . "\n";
    echo "  - data_request_status: " . ($rowData['data_request_status'] ?? 'null') . "\n\n";

    try {
        // تطبيق المعالجة
        echo "⚙️  تطبيق المعالجة...\n";
        $processedData = $importService->processRowData($rowData, 'data');

        echo "📝 البيانات بعد المعالجة:\n";
        echo "  - تاريخ الميلاد: " . ($processedData['data_birth_date'] ?? 'null') . "\n";
        echo "  - data_request_status: " . ($processedData['data_request_status'] ?? 'null') . "\n\n";

        // محاولة الإدخال
        echo "💾 محاولة الإدخال...\n";
        DB::table('data')->insert($processedData);

        $insertedCount++;
        echo "✅ نجح!\n\n";

    } catch (\Exception $e) {
        $failedInserts[] = [
            'row' => $rowNumber,
            'error' => $e->getMessage()
        ];

        echo "❌ فشل!\n";
        echo "🔴 الخطأ: " . $e->getMessage() . "\n\n";
    }
}

echo "═══════════════════════════════════════════════════════\n";
echo "📊 النتائج النهائية:\n";
echo "  ✅ نجحت: $insertedCount صف\n";
echo "  ❌ فشلت: " . count($failedInserts) . " صف\n\n";

if ($insertedCount > 0) {
    DB::commit();
    echo "✅ تم حفظ البيانات في قاعدة البيانات\n\n";

    // عرض البيانات المدخلة
    $lastInserted = DB::table('data')
        ->orderBy('data_id', 'desc')
        ->limit($insertedCount)
        ->get();

    echo "📋 البيانات المُدخلة:\n";
    foreach ($lastInserted as $record) {
        echo "  - ID: {$record->data_id} | الهوية: {$record->data_id_number} | التاريخ: {$record->data_birth_date} | الحالة: {$record->data_request_status}\n";
    }
} else {
    DB::rollBack();
    echo "❌ تم التراجع عن جميع التغييرات\n\n";

    if (!empty($failedInserts)) {
        echo "🔴 أسباب الفشل:\n";
        foreach ($failedInserts as $fail) {
            echo "  الصف {$fail['row']}: {$fail['error']}\n";
        }
    }
}

echo "\n✅ انتهى الاختبار\n";
