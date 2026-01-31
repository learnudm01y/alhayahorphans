<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Data;
use Illuminate\Support\Facades\DB;

// البحث عن معيل لديه مرفقات
$guardian = Data::has('attachments')->with(['attachments', 'rePeople.attachments'])->first();

if (!$guardian) {
    echo "لا يوجد معيل لديه مرفقات!" . PHP_EOL;

    // البحث عن يتيم لديه مرفقات
    $orphanWithAttachments = DB::table('re_people')
        ->join('attachments', 're_people.person_id', '=', 'attachments.person_identity_number')
        ->select('re_people.*', DB::raw('COUNT(attachments.id) as att_count'))
        ->groupBy('re_people.person_id')
        ->having('att_count', '>', 0)
        ->first();

    if ($orphanWithAttachments) {
        echo "يتيم لديه مرفقات:" . PHP_EOL;
        echo "الاسم: {$orphanWithAttachments->first_name} {$orphanWithAttachments->family_name}" . PHP_EOL;
        echo "رقم الهوية: {$orphanWithAttachments->person_id}" . PHP_EOL;
        echo "رقم الملف: {$orphanWithAttachments->file_number}" . PHP_EOL;
        echo "عدد المرفقات: {$orphanWithAttachments->att_count}" . PHP_EOL;

        // جلب المعيل لهذا اليتيم
        $guardianData = Data::whereHas('rePeople', function($q) use ($orphanWithAttachments) {
            $q->where('person_id', $orphanWithAttachments->person_id);
        })->with(['attachments', 'rePeople.attachments'])->first();

        if ($guardianData) {
            echo PHP_EOL . "معلومات المعيل:" . PHP_EOL;
            echo "ID: {$guardianData->id}" . PHP_EOL;
            echo "الاسم: {$guardianData->data_first_name} {$guardianData->data_family_name}" . PHP_EOL;
            echo "رقم الهوية: {$guardianData->data_id_number}" . PHP_EOL;
            echo "عدد مرفقات المعيل: " . $guardianData->attachments->count() . PHP_EOL;

            // عرض مرفقات اليتيم
            echo PHP_EOL . "مرفقات اليتيم:" . PHP_EOL;
            $orphanModel = $guardianData->rePeople->firstWhere('person_id', $orphanWithAttachments->person_id);
            if ($orphanModel && $orphanModel->attachments) {
                foreach ($orphanModel->attachments as $att) {
                    $docType = DB::table('document_types')->where('pref', $att->file_type)->first();
                    $typeName = $docType ? $docType->description : 'غير معروف';
                    echo "  النوع {$att->file_type} ({$typeName}): {$att->stored_file_name}" . PHP_EOL;
                }
            }
        }
    }
    exit;
}

echo "معيل لديه مرفقات:" . PHP_EOL;
echo "ID: {$guardian->id}" . PHP_EOL;
echo "الاسم: {$guardian->data_first_name} {$guardian->data_family_name}" . PHP_EOL;
echo "رقم الهوية: {$guardian->data_id_number}" . PHP_EOL;
echo "عدد المرفقات: " . $guardian->attachments->count() . PHP_EOL . PHP_EOL;

foreach ($guardian->attachments as $att) {
    $docType = DB::table('document_types')->where('pref', $att->file_type)->first();
    $typeName = $docType ? $docType->description : 'غير معروف';
    echo "  النوع {$att->file_type} ({$typeName}): {$att->stored_file_name}" . PHP_EOL;
}
