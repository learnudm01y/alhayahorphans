<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== تحديث حالة السجلات المستوردة من Excel ===\n";

// البحث عن السجلات التي لها data_request_status != 4
$recordsToUpdate = DB::table('data')
    ->where('data_request_status', '!=', 4)
    ->whereNotNull('original_file_id_from_excel') // السجلات المستوردة من Excel
    ->get();

echo "عدد السجلات التي تحتاج تحديث: " . $recordsToUpdate->count() . "\n";

if ($recordsToUpdate->count() > 0) {
    echo "\nتفاصيل السجلات قبل التحديث:\n";
    foreach ($recordsToUpdate->take(5) as $record) {
        echo "رقم الملف: {$record->file_id_number} | الحالة الحالية: {$record->data_request_status}\n";
    }

    // تحديث السجلات
    $updatedCount = DB::table('data')
        ->where('data_request_status', '!=', 4)
        ->whereNotNull('original_file_id_from_excel')
        ->update([
            'data_request_status' => 4,
            'updated_at' => now()
        ]);

    echo "\nتم تحديث {$updatedCount} سجل بنجاح.\n";

    // التحقق من النتيجة
    $verificationCount = DB::table('data')
        ->where('data_request_status', 4)
        ->whereNotNull('original_file_id_from_excel')
        ->count();

    echo "عدد السجلات المستوردة من Excel التي لها حالة 'مقبول' الآن: {$verificationCount}\n";
} else {
    echo "جميع السجلات المستوردة من Excel لها حالة صحيحة بالفعل.\n";
}

echo "\n=== انتهى التحديث ===\n";
