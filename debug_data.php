<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Data;
use App\Models\RequestStatus;

echo "=== فحص بيانات جدول Data ===\n";

// فحص عدد السجلات
$dataCount = Data::count();
echo "عدد السجلات في جدول Data: $dataCount\n\n";

// فحص حالات الطلب الموجودة
echo "=== حالات الطلب الموجودة ===\n";
$statuses = RequestStatus::all();
foreach($statuses as $status) {
    echo "- ID: {$status->id}, الوصف: {$status->description}\n";
}
echo "\n";

// فحص توزيع السجلات حسب حالة الطلب
echo "=== توزيع السجلات حسب حالة الطلب ===\n";
$distribution = Data::select('data_request_status', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
    ->groupBy('data_request_status')
    ->get();

foreach($distribution as $item) {
    $status = RequestStatus::find($item->data_request_status);
    $statusName = $status ? $status->description : 'غير محدد';
    echo "- حالة {$item->data_request_status} ({$statusName}): {$item->count} سجل\n";
}
echo "\n";

// فحص السجلات التي تحتوي على "مقبول"
echo "=== السجلات التي حالة الطلب لها 'مقبول' ===\n";
$acceptedRecords = Data::whereHas('requestStatus', function($q) {
    $q->where('description', 'مقبول');
})->count();
echo "عدد السجلات المقبولة: $acceptedRecords\n\n";

// عرض عينة من السجلات الأخيرة
echo "=== آخر 5 سجلات تم إدخالها ===\n";
$recent = Data::latest()->take(5)->get(['id', 'file_id_number', 'data_first_name', 'data_request_status', 'created_at']);
foreach($recent as $record) {
    $status = RequestStatus::find($record->data_request_status);
    $statusName = $status ? $status->description : 'غير محدد';
    echo "- ID: {$record->id}, رقم الملف: {$record->file_id_number}, الاسم: {$record->data_first_name}, الحالة: {$statusName}, التاريخ: {$record->created_at}\n";
}
echo "\n";

// فحص العلاقة بين Data و RequestStatus
echo "=== فحص العلاقة بين Data و RequestStatus ===\n";
$sampleRecord = Data::with('requestStatus')->first();
if ($sampleRecord) {
    echo "سجل تجريبي - ID: {$sampleRecord->id}\n";
    echo "data_request_status: {$sampleRecord->data_request_status}\n";
    echo "requestStatus relation: " . ($sampleRecord->requestStatus ? $sampleRecord->requestStatus->description : 'null') . "\n";
} else {
    echo "لا توجد سجلات في جدول Data\n";
}
