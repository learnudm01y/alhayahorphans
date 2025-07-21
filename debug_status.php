<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== فحص جدول request_status ===\n";
try {
    $statuses = DB::table('request_status')->orderBy('id')->get();
    if ($statuses->count() > 0) {
        foreach ($statuses as $status) {
            echo "ID: {$status->id} - الوصف: {$status->description}\n";
        }
    } else {
        echo "لا توجد بيانات في جدول request_status\n";
    }
} catch (Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
}

echo "\n=== فحص آخر 5 سجلات من جدول data ===\n";
try {
    $data = DB::table('data')
        ->select('file_id_number', 'data_id_number', 'data_request_status', 'created_at')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();

    if ($data->count() > 0) {
        foreach ($data as $record) {
            echo "رقم الملف: {$record->file_id_number} | رقم الهوية: {$record->data_id_number} | حالة الطلب: {$record->data_request_status} | تاريخ الإنشاء: {$record->created_at}\n";
        }
    } else {
        echo "لا توجد بيانات في جدول data\n";
    }
} catch (Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
}
