<?php
/**
 * فحص سريع لحالة السجلات المستوردة من Excel
 */

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== فحص سريع للسجلات المستوردة ===\n";

try {
    // 1. فحص حالة "مقبول"
    $acceptedStatus = DB::table('request_status')->where('description', 'مقبول')->first();
    echo "حالة 'مقبول': ID = {$acceptedStatus->id}\n";

    // 2. فحص السجلات المستوردة
    $excelRecords = DB::table('data')
        ->whereNotNull('original_file_id_from_excel')
        ->selectRaw('data_request_status, COUNT(*) as count')
        ->groupBy('data_request_status')
        ->get();

    echo "\nتوزيع السجلات المستوردة:\n";
    foreach ($excelRecords as $record) {
        $statusName = DB::table('request_status')->where('id', $record->data_request_status)->value('description') ?? 'غير معروف';
        echo "الحالة {$record->data_request_status} ({$statusName}): {$record->count} سجل\n";
    }

    // 3. فحص الظهور في الجداول
    $visibleInRecordsManagement = DB::table('data')
        ->join('request_status', 'data.data_request_status', '=', 'request_status.id')
        ->where('request_status.description', 'مقبول')
        ->whereNotNull('data.original_file_id_from_excel')
        ->count();

    echo "\nالسجلات المرئية في جدول إدارة السجلات: {$visibleInRecordsManagement}\n";

    // 4. فحص أحدث 3 سجلات
    echo "\nأحدث 3 سجلات مستوردة:\n";
    $latest = DB::table('data')
        ->select('file_id_number', 'data_id_number', 'data_request_status', 'created_at')
        ->whereNotNull('original_file_id_from_excel')
        ->orderBy('created_at', 'desc')
        ->limit(3)
        ->get();

    foreach ($latest as $record) {
        $statusName = DB::table('request_status')->where('id', $record->data_request_status)->value('description') ?? 'غير معروف';
        echo "رقم الملف: {$record->file_id_number} | الحالة: {$record->data_request_status} ({$statusName})\n";
    }

} catch (Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
}

echo "\n=== انتهى الفحص ===\n";
