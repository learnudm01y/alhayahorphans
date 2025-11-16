<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\ExcelValidationService;
use App\Services\ExcelImportService;

// المسار للملف - استخدام آخر ملف موجود
$filePath = 'I:\\unit test\\alhayahorphans\\ASO - Copy\\storage\\app\\public\\excel_files\\a0Kzz51H4OYdH6KTqT8iArtj1p6R4RQi4jOc6G0o.xlsx';

if (!file_exists($filePath)) {
    die("❌ الملف غير موجود: $filePath\n");
}

echo "📂 الملف موجود\n";
echo "📏 حجم الملف: " . filesize($filePath) . " بايت\n\n";

// إنشاء الخدمات
$validationService = new ExcelValidationService();
$importService = new ExcelImportService();

// التحقق من الملف
echo "🔍 جاري التحقق من الملف...\n";
$validationResult = $validationService->validateExcelFile($filePath, 'data');

echo "📊 نتائج التحقق:\n";
echo "  ✅ صفوف صحيحة: " . count($validationResult['valid_rows']) . "\n";
echo "  ❌ صفوف خاطئة: " . count($validationResult['invalid_rows']) . "\n\n";

if (empty($validationResult['valid_rows'])) {
    die("❌ لا توجد صفوف صحيحة للإدخال\n");
}

// محاولة إدخال أول 3 صفوف فقط مع تسجيل الأخطاء بالتفصيل
echo "🔄 محاولة إدخال أول 3 صفوف...\n\n";

$validRows = array_slice($validationResult['valid_rows'], 0, 3);

DB::beginTransaction();

$insertedCount = 0;
$failedInserts = [];

foreach ($validRows as $index => $row) {
    $rowNumber = $row['row_number'];
    $rowData = $row['data'];

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🔄 معالجة الصف رقم: $rowNumber\n";
    echo "📝 البيانات الأولية:\n";
    echo "  - رقم الهوية: " . ($rowData['data_id_number'] ?? 'null') . "\n";
    echo "  - الاسم: " . ($rowData['data_first_name'] ?? 'null') . "\n";
    echo "  - تاريخ الميلاد: " . ($rowData['data_birth_date'] ?? 'null') . "\n";
    echo "  - data_request_status: " . ($rowData['data_request_status'] ?? 'null') . "\n\n";

    try {
        // تطبيق المعالجة
        echo "⚙️  تطبيق المعالجة (processRowData)...\n";
        $processedData = $importService->processRowData($rowData, 'data');

        echo "📝 البيانات بعد المعالجة:\n";
        echo "  - تاريخ الميلاد: " . ($processedData['data_birth_date'] ?? 'null') . "\n";
        echo "  - data_request_status: " . ($processedData['data_request_status'] ?? 'null') . "\n";
        echo "  - created_at: " . ($processedData['created_at'] ?? 'null') . "\n";
        echo "  - updated_at: " . ($processedData['updated_at'] ?? 'null') . "\n\n";

        // محاولة الإدخال
        echo "💾 محاولة الإدخال إلى قاعدة البيانات...\n";
        DB::table('data')->insert($processedData);

        $insertedCount++;
        echo "✅ نجح الإدخال!\n";

    } catch (\Exception $e) {
        $failedInserts[] = [
            'row_number' => $rowNumber,
            'data' => $rowData,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];

        echo "❌ فشل الإدخال!\n";
        echo "🔴 الخطأ: " . $e->getMessage() . "\n";
        echo "📍 السطر: " . $e->getLine() . "\n";
        echo "📄 الملف: " . $e->getFile() . "\n\n";
        echo "🔍 تفاصيل الخطأ الكاملة:\n";
        echo $e->getTraceAsString() . "\n";
    }
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📊 النتائج النهائية:\n";
echo "  ✅ نجحت: $insertedCount صف\n";
echo "  ❌ فشلت: " . count($failedInserts) . " صف\n\n";

if ($insertedCount > 0) {
    DB::commit();
    echo "✅ تم حفظ البيانات (commit)\n\n";

    // التحقق من البيانات المُدخلة
    echo "🔍 التحقق من البيانات في قاعدة البيانات...\n";
    $lastInserted = DB::table('data')
        ->orderBy('data_id', 'desc')
        ->limit($insertedCount)
        ->get();

    echo "📊 آخر $insertedCount سجل مُدخل:\n";
    foreach ($lastInserted as $record) {
        echo "  - ID: {$record->data_id}\n";
        echo "    رقم الهوية: {$record->data_id_number}\n";
        echo "    تاريخ الميلاد: {$record->data_birth_date}\n";
        echo "    data_request_status: {$record->data_request_status}\n\n";
    }
} else {
    DB::rollBack();
    echo "❌ تم التراجع عن جميع التغييرات (rollback)\n\n";

    echo "🔴 أسباب الفشل:\n";
    foreach ($failedInserts as $i => $fail) {
        echo "\n  الصف #{$fail['row_number']}:\n";
        echo "    الخطأ: {$fail['error']}\n";
    }
}

echo "\n✅ انتهى الاختبار\n";
