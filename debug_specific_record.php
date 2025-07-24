<?php

require_once __DIR__ . '/vendor/autoload.php';

// إعداد البيئة
$_ENV['APP_ENV'] = 'local';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Attachment;
use App\Models\Data;

echo "=== فحص سجل محدد لديه مرفقات ===\n\n";

// البحث عن سجل لديه مرفقات
$personWithAttachments = '407035781'; // من النتائج السابقة

echo "فحص رقم الهوية: {$personWithAttachments}\n";
echo "=====================================\n";

// فحص المرفقات في جدول attachments
$attachments = Attachment::where('person_identity_number', $personWithAttachments)->get();
echo "المرفقات في جدول attachments: " . $attachments->count() . "\n";

foreach ($attachments as $att) {
    echo "  - ID: {$att->id}, File: {$att->stored_file_name}, Path: {$att->file_path}\n";
}

// البحث عن السجل في جدول data
$dataRecord = Data::where('data_id_number', $personWithAttachments)->first();

if ($dataRecord) {
    echo "\nوُجد السجل في جدول data: ID {$dataRecord->id}\n";

    // تحميل المرفقات باستخدام العلاقة
    $attachmentsThroughRelation = $dataRecord->attachments;
    echo "المرفقات عبر العلاقة: " . $attachmentsThroughRelation->count() . "\n";

    if ($attachmentsThroughRelation->count() === 0) {
        echo "⚠️ المشكلة: العلاقة لا تعيد أي مرفقات رغم وجودها في قاعدة البيانات!\n";

        // فحص نوع البيانات
        echo "\nفحص أنواع البيانات:\n";
        echo "data_id_number في جدول data: " . gettype($dataRecord->data_id_number) . " - القيمة: '{$dataRecord->data_id_number}'\n";
        echo "person_identity_number في جدول attachments: " . gettype($attachments->first()->person_identity_number) . " - القيمة: '{$attachments->first()->person_identity_number}'\n";

        // مقارنة القيم
        if ($dataRecord->data_id_number == $attachments->first()->person_identity_number) {
            echo "✅ القيم متطابقة عند المقارنة العادية\n";
        } else {
            echo "❌ القيم غير متطابقة عند المقارنة العادية\n";
        }

        if ($dataRecord->data_id_number === $attachments->first()->person_identity_number) {
            echo "✅ القيم متطابقة عند المقارنة الصارمة\n";
        } else {
            echo "❌ القيم غير متطابقة عند المقارنة الصارمة\n";
        }
    }
} else {
    echo "\n❌ لم يُوجد السجل في جدول data\n";
}

echo "\n=== فحص إضافي للعلاقات ===\n";

// اختبار استعلام مباشر
echo "اختبار استعلام مباشر:\n";
$directQuery = Attachment::where('person_identity_number', '407035781')->get();
echo "النتيجة المباشرة: " . $directQuery->count() . " مرفقات\n";

// البحث عن جميع السجلات التي لها مرفقات
echo "\nفحص جميع السجلات التي لها مرفقات:\n";
$dataWithAttachments = Data::whereHas('attachments')->with('attachments')->get();
echo "عدد السجلات التي لها مرفقات: " . $dataWithAttachments->count() . "\n";

foreach ($dataWithAttachments as $record) {
    echo "  - رقم الهوية: {$record->data_id_number}, عدد المرفقات: " . $record->attachments->count() . "\n";
}

echo "\n=== انتهاء الفحص ===\n";
