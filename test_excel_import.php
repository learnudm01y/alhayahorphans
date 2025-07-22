<?php
/**
 * سكريپت اختبار استيراد Excel للتأكد من عمل النظام
 */

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Data;

echo "=== اختبار استيراد Excel ===\n";
echo "تاريخ الاختبار: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // 1. إنشاء سجل تجريبي يحاكي استيراد Excel
    echo "1. إنشاء سجل تجريبي:\n";

    $testData = [
        'file_id_number' => 'TEST_' . time(),
        'original_file_id_from_excel' => 'excel_test_' . time(),
        'data_id_number' => '1234567890',
        'data_first_name' => 'محمد',
        'data_father_name' => 'أحمد',
        'data_grand_father_name' => 'عبدالله',
        'data_family_name' => 'الاختبار',
        'data_phone_number' => '0500000000',
        'data_user_insert_data' => 1, // افتراض وجود مستخدم بـ ID = 1
        'created_at' => now(),
        'updated_at' => now()
    ];

    // إنشاء السجل باستخدام Eloquent (سيطلق Events والObservers)
    $testRecord = Data::create($testData);

    echo "   ✅ تم إنشاء سجل تجريبي بـ ID: {$testRecord->id}\n";
    echo "   ملف Excel رقم: {$testRecord->file_id_number}\n";

    // 2. فحص تعيين الحالة تلقائياً
    echo "\n2. فحص تعيين الحالة:\n";

    $updatedRecord = Data::find($testRecord->id);

    if ($updatedRecord->data_request_status) {
        $statusDescription = DB::table('request_status')
            ->where('id', $updatedRecord->data_request_status)
            ->value('description');

        echo "   ✅ تم تعيين حالة الطلب: {$statusDescription} (ID: {$updatedRecord->data_request_status})\n";
    } else {
        echo "   ❌ لم يتم تعيين حالة للطلب\n";
    }

    // 3. فحص ظهور السجل في DataTable query
    echo "\n3. فحص ظهور السجل في DataTable:\n";

    $isVisibleInRecordsTable = DB::table('data')
        ->join('request_status', 'data.data_request_status', '=', 'request_status.id')
        ->where('data.id', $testRecord->id)
        ->where('request_status.description', 'مقبول')
        ->exists();

    echo "   RecordsManagementeDataTable: " . ($isVisibleInRecordsTable ? "✅ مرئي" : "❌ غير مرئي") . "\n";

    $isVisibleInManageTable = DB::table('data')
        ->join('request_status', 'data.data_request_status', '=', 'request_status.id')
        ->where('data.id', $testRecord->id)
        ->where('request_status.description', '!=', 'مقبول')
        ->exists();

    echo "   ManageTheUserRequestDataTable: " . ($isVisibleInManageTable ? "✅ مرئي" : "❌ غير مرئي") . "\n";

    // 4. إحصائيات شاملة
    echo "\n4. إحصائيات شاملة:\n";

    $totalRecords = Data::count();
    $excelRecords = Data::whereNotNull('original_file_id_from_excel')->count();
    $acceptedRecords = DB::table('data')
        ->join('request_status', 'data.data_request_status', '=', 'request_status.id')
        ->where('request_status.description', 'مقبول')
        ->count();

    echo "   إجمالي السجلات: {$totalRecords}\n";
    echo "   السجلات المستوردة من Excel: {$excelRecords}\n";
    echo "   السجلات المقبولة (مرئية): {$acceptedRecords}\n";

    // 5. تنظيف السجل التجريبي
    echo "\n5. تنظيف السجل التجريبي:\n";

    $testRecord->delete();
    echo "   ✅ تم حذف السجل التجريبي\n";

    // 6. النتيجة النهائية
    echo "\n6. النتيجة النهائية:\n";

    if ($isVisibleInRecordsTable && $updatedRecord->data_request_status) {
        echo "   ✅ النظام يعمل بشكل صحيح!\n";
        echo "   ✅ السجلات المستوردة من Excel ستظهر تلقائياً\n";
        echo "   ✅ لا حاجة لتشغيل أي سكريپت يدوياً\n";
    } else {
        echo "   ❌ هناك مشكلة في النظام\n";
        echo "   تحقق من:\n";
        echo "     - تسجيل DataObserverServiceProvider في config/app.php\n";
        echo "     - رفع ملف Data.php المحدث\n";
        echo "     - مسح cache: php artisan cache:clear\n";
        echo "     - إعادة تشغيل الخادم\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ أثناء الاختبار: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== انتهى اختبار Excel ===\n";
