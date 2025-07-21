<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Data;
use App\Models\RequestStatus;

echo "=== تحديث حالة الطلب للسجلات المدخلة من Excel ===\n";

// العثور على السجلات التي حالة الطلب لها ليست "مقبول" (2)
$recordsToUpdate = Data::where('data_request_status', '!=', 2)->get();

echo "عدد السجلات التي تحتاج تحديث: " . $recordsToUpdate->count() . "\n\n";

if ($recordsToUpdate->count() > 0) {
    echo "السجلات التي سيتم تحديثها:\n";
    foreach($recordsToUpdate as $record) {
        $status = RequestStatus::find($record->data_request_status);
        $statusName = $status ? $status->description : 'غير محدد';
        echo "- ID: {$record->id}, رقم الملف: {$record->file_id_number}, الحالة الحالية: {$statusName}\n";
    }

    echo "\nجاري تحديث الحالة إلى 'مقبول'...\n";

    // تحديث جميع السجلات لتكون حالتها "مقبول" (2)
    $updated = Data::where('data_request_status', '!=', 2)->update(['data_request_status' => 2]);

    echo "تم تحديث {$updated} سجل بنجاح!\n\n";

    // التحقق من النتيجة
    echo "=== التحقق من النتيجة ===\n";
    $acceptedCount = Data::whereHas('requestStatus', function($q) {
        $q->where('description', 'مقبول');
    })->count();
    echo "عدد السجلات المقبولة الآن: {$acceptedCount}\n";

} else {
    echo "جميع السجلات لها حالة 'مقبول' بالفعل.\n";
}
