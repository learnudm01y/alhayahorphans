<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== فحص وإصلاح شامل لحالات السجلات ===\n";

// 1. فحص جدول request_status
echo "\n1. فحص جدول request_status:\n";
$statuses = DB::table('request_status')->orderBy('id')->get();
$acceptedStatusId = null;

foreach ($statuses as $status) {
    echo "ID: {$status->id} - الوصف: {$status->description}\n";
    if ($status->description === 'مقبول') {
        $acceptedStatusId = $status->id;
    }
}

if ($acceptedStatusId) {
    echo "✅ تم العثور على حالة 'مقبول' بـ ID: {$acceptedStatusId}\n";
} else {
    echo "❌ لم يتم العثور على حالة 'مقبول' في الجدول!\n";
    exit(1);
}

// 2. فحص السجلات المستوردة من Excel
echo "\n2. فحص السجلات المستوردة من Excel:\n";
$excelRecords = DB::table('data')
    ->whereNotNull('original_file_id_from_excel')
    ->get();

echo "إجمالي السجلات المستوردة من Excel: " . $excelRecords->count() . "\n";

// تجميع حسب الحالة
$statusGroups = $excelRecords->groupBy('data_request_status');
echo "\nتوزيع السجلات حسب الحالة:\n";
foreach ($statusGroups as $statusId => $records) {
    $statusName = DB::table('request_status')->where('id', $statusId)->value('description') ?? 'غير معروف';
    echo "الحالة {$statusId} ({$statusName}): " . $records->count() . " سجل\n";
}

// 3. تحديث السجلات إذا لزم الأمر
$wrongStatusRecords = DB::table('data')
    ->where('data_request_status', '!=', $acceptedStatusId)
    ->whereNotNull('original_file_id_from_excel')
    ->count();

if ($wrongStatusRecords > 0) {
    echo "\n3. تحديث السجلات ذات الحالة الخاطئة:\n";
    echo "عدد السجلات التي تحتاج تحديث: {$wrongStatusRecords}\n";

    $updatedCount = DB::table('data')
        ->where('data_request_status', '!=', $acceptedStatusId)
        ->whereNotNull('original_file_id_from_excel')
        ->update([
            'data_request_status' => $acceptedStatusId,
            'updated_at' => now()
        ]);

    echo "✅ تم تحديث {$updatedCount} سجل بنجاح.\n";
} else {
    echo "\n3. ✅ جميع السجلات المستوردة من Excel لها حالة صحيحة.\n";
}

// 4. التحقق النهائي
echo "\n4. التحقق النهائي:\n";
$correctRecords = DB::table('data')
    ->where('data_request_status', $acceptedStatusId)
    ->whereNotNull('original_file_id_from_excel')
    ->count();

$totalExcelRecords = DB::table('data')
    ->whereNotNull('original_file_id_from_excel')
    ->count();

echo "السجلات المستوردة من Excel مع حالة 'مقبول': {$correctRecords}\n";
echo "إجمالي السجلات المستوردة من Excel: {$totalExcelRecords}\n";

if ($correctRecords === $totalExcelRecords) {
    echo "✅ جميع السجلات المستوردة من Excel لها حالة 'مقبول' الآن!\n";
} else {
    echo "⚠️  هناك " . ($totalExcelRecords - $correctRecords) . " سجل لا يزال بحالة خاطئة.\n";
}

// 5. عرض آخر 5 سجلات مستوردة
echo "\n5. آخر 5 سجلات مستوردة من Excel:\n";
$latestRecords = DB::table('data')
    ->select('file_id_number', 'data_id_number', 'data_request_status', 'original_file_id_from_excel', 'created_at')
    ->whereNotNull('original_file_id_from_excel')
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get();

foreach ($latestRecords as $record) {
    $statusName = DB::table('request_status')->where('id', $record->data_request_status)->value('description') ?? 'غير معروف';
    echo "رقم الملف: {$record->file_id_number} | الرقم الأصلي: {$record->original_file_id_from_excel} | الحالة: {$record->data_request_status} ({$statusName})\n";
}

echo "\n=== انتهى الفحص والإصلاح ===\n";
