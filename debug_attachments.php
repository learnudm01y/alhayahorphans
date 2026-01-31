<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Data;
use App\Models\RePeople;
use Illuminate\Support\Facades\DB;

// استخدم ID من قاعدة البيانات - غيره حسب الحاجة
$guardianId = 1; // أول معيل في قاعدة البيانات

echo "=== تحليل كامل للمعيل ===" . PHP_EOL . PHP_EOL;

// جلب المعيل مع العلاقات
$guardian = Data::with(['attachments', 'rePeople.attachments'])->first();

if (!$guardian) {
    echo "لم يتم العثور على أي معيل في قاعدة البيانات!" . PHP_EOL;
    exit;
}

echo "المعيل: {$guardian->data_first_name} {$guardian->data_family_name}" . PHP_EOL;
echo "رقم الهوية: {$guardian->data_id_number}" . PHP_EOL;
echo "ID: {$guardian->id}" . PHP_EOL;
echo "عدد مرفقات المعيل: " . $guardian->attachments->count() . PHP_EOL . PHP_EOL;

if ($guardian->attachments->count() > 0) {
    echo "مرفقات المعيل:" . PHP_EOL;
    foreach ($guardian->attachments as $att) {
        $docType = DB::table('document_types')->where('pref', $att->file_type)->first();
        $typeName = $docType ? $docType->description : 'غير معروف';
        echo "  - النوع {$att->file_type} ({$typeName}): {$att->stored_file_name}" . PHP_EOL;
        echo "    المسار: {$att->file_path}" . PHP_EOL;

        // التحقق من وجود الملف
        $fullPath = base_path(str_replace('storage/', 'storage/app/public/', $att->file_path));
        if (file_exists($fullPath)) {
            echo "    ✅ الملف موجود" . PHP_EOL;
        } else {
            echo "    ❌ الملف غير موجود: {$fullPath}" . PHP_EOL;
        }
    }
}

echo PHP_EOL . "عدد الأيتام: " . $guardian->rePeople->count() . PHP_EOL . PHP_EOL;

foreach ($guardian->rePeople as $orphan) {
    echo "اليتيم: {$orphan->first_name} {$orphan->family_name}" . PHP_EOL;
    echo "رقم الهوية: {$orphan->person_id}" . PHP_EOL;
    echo "عدد المرفقات: " . $orphan->attachments->count() . PHP_EOL;

    if ($orphan->attachments->count() > 0) {
        foreach ($orphan->attachments as $att) {
            $docType = DB::table('document_types')->where('pref', $att->file_type)->first();
            $typeName = $docType ? $docType->description : 'غير معروف';
            echo "  - النوع {$att->file_type} ({$typeName}): {$att->stored_file_name}" . PHP_EOL;

            // التحقق من وجود الملف
            $fullPath = base_path(str_replace('storage/', 'storage/app/public/', $att->file_path));
            if (file_exists($fullPath)) {
                echo "    ✅ موجود" . PHP_EOL;
            } else {
                echo "    ❌ غير موجود" . PHP_EOL;
            }
        }
    }
    echo PHP_EOL;
}

// إحصائيات أنواع الوثائق
echo PHP_EOL . "=== أنواع الوثائق الأكثر استخداماً ===" . PHP_EOL;
$typeStats = DB::table('attachments')
    ->select('file_type', DB::raw('count(*) as count'))
    ->groupBy('file_type')
    ->orderBy('count', 'desc')
    ->limit(15)
    ->get();

foreach ($typeStats as $stat) {
    $docType = DB::table('document_types')->where('pref', $stat->file_type)->first();
    $desc = $docType ? $docType->description : 'غير معروف';
    echo "النوع {$stat->file_type} ({$desc}): {$stat->count} مرفق" . PHP_EOL;
}

