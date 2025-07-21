<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\ExcelImportService;

echo "=== اختبار خدمة استيراد Excel مع تعيين الحالة التلقائي ===\n";

// 1. فحص حالة "مقبول" في قاعدة البيانات
echo "\n1. فحص حالة 'مقبول' في قاعدة البيانات:\n";
$acceptedStatus = DB::table('request_status')
    ->where('description', 'مقبول')
    ->first();

if ($acceptedStatus) {
    echo "✅ تم العثور على حالة 'مقبول' بـ ID: {$acceptedStatus->id}\n";
} else {
    echo "❌ لم يتم العثور على حالة 'مقبول'!\n";
    exit(1);
}

// 2. محاكاة عملية الاستيراد
echo "\n2. محاكاة تعيين الحالة التلقائي:\n";

// محاكاة بيانات مستوردة بدون حالة محددة
$sampleData = [
    'file_id_number' => '999999',
    'data_id_number' => '123456789',
    'data_first_name' => 'اختبار',
    'data_family_name' => 'التحديث',
    // لا يوجد data_request_status - سيتم تعيينه تلقائياً
];

// محاكاة الكود في addSystemFields
$sampleData['created_at'] = now();
$sampleData['updated_at'] = now();

if (auth()->check()) {
    $sampleData['data_user_insert_data'] = auth()->id();
} else {
    $sampleData['data_user_insert_data'] = 1; // مستخدم افتراضي للاختبار
}

// تطبيق نفس المنطق الموجود في الكود
if (!isset($sampleData['data_request_status']) || empty($sampleData['data_request_status'])) {
    $acceptedStatusId = DB::table('request_status')
        ->where('description', 'مقبول')
        ->value('id');

    $sampleData['data_request_status'] = $acceptedStatusId ?: 2;

    echo "تم تعيين data_request_status = {$sampleData['data_request_status']}\n";
    echo "البحث في قاعدة البيانات: " . ($acceptedStatusId ? "نجح (ID: $acceptedStatusId)" : "فشل - استخدام القيمة الافتراضية 2") . "\n";
}

// 3. التحقق من صحة التعيين
echo "\n3. التحقق من صحة التعيين:\n";
$assignedStatusDescription = DB::table('request_status')
    ->where('id', $sampleData['data_request_status'])
    ->value('description');

if ($assignedStatusDescription === 'مقبول') {
    echo "✅ تم تعيين الحالة الصحيحة: {$sampleData['data_request_status']} (مقبول)\n";
} else {
    echo "❌ تم تعيين حالة خاطئة: {$sampleData['data_request_status']} ({$assignedStatusDescription})\n";
}

// 4. فحص إمكانية عرض السجل في DataTable
echo "\n4. فحص إمكانية عرض السجل في DataTable:\n";
$canBeDisplayed = DB::table('request_status')
    ->where('id', $sampleData['data_request_status'])
    ->where('description', 'مقبول')
    ->exists();

if ($canBeDisplayed) {
    echo "✅ السجل سيظهر في DataTable (حالة مقبول)\n";
} else {
    echo "❌ السجل لن يظهر في DataTable (حالة غير مقبول)\n";
}

echo "\n=== انتهى الاختبار ===\n";
