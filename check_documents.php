<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== التحقق من جدول document_types ===" . PHP_EOL . PHP_EOL;

$count = DB::table('document_types')->count();
echo "عدد أنواع الوثائق: " . $count . PHP_EOL . PHP_EOL;

if ($count > 0) {
    $types = DB::table('document_types')->get();
    foreach ($types as $type) {
        echo "ID: " . $type->id . " | الكود: " . ($type->pref ?? 'N/A') . " | الوصف: " . ($type->description ?? 'N/A') . PHP_EOL;
    }
} else {
    echo "⚠️ جدول document_types فارغ!" . PHP_EOL;
}

echo PHP_EOL . "=== التحقق من جدول attachments ===" . PHP_EOL . PHP_EOL;

$attachmentCount = DB::table('attachments')->count();
echo "إجمالي المرفقات: " . $attachmentCount . PHP_EOL;

$photoCount = DB::table('attachments')->where('file_type', '1')->count();
echo "الصور الشخصية (file_type=1): " . $photoCount . PHP_EOL;

$withPath = DB::table('attachments')->whereNotNull('file_path')->count();
echo "المرفقات ذات المسار: " . $withPath . PHP_EOL . PHP_EOL;

// عرض عينة من المرفقات
echo "=== عينة من المرفقات الحديثة ===" . PHP_EOL . PHP_EOL;
$recent = DB::table('attachments')
    ->select('id', 'person_identity_number', 'file_type', 'stored_file_name', 'file_path')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

if ($recent->count() > 0) {
    foreach ($recent as $att) {
        echo "ID: {$att->id} | الهوية: {$att->person_identity_number} | النوع: {$att->file_type} | الاسم: {$att->stored_file_name}" . PHP_EOL;
        echo "   المسار: {$att->file_path}" . PHP_EOL;

        // التحقق من وجود الملف
        $fullPath = '';
        if (str_starts_with($att->file_path, 'storage/')) {
            $fullPath = __DIR__ . '/' . str_replace('storage/', 'storage/app/public/', $att->file_path);
        } else {
            $fullPath = __DIR__ . '/public/' . $att->file_path;
        }

        if (file_exists($fullPath)) {
            echo "   ✅ الملف موجود: " . $fullPath . PHP_EOL;
        } else {
            echo "   ❌ الملف غير موجود: " . $fullPath . PHP_EOL;
        }
        echo PHP_EOL;
    }
} else {
    echo "⚠️ لا توجد مرفقات في قاعدة البيانات!" . PHP_EOL;
}

// اختبار رقم ملف محدد
echo PHP_EOL . "=== اختبار رقم ملف محدد ===" . PHP_EOL . PHP_EOL;
echo "أدخل رقم ملف للاختبار (أو اضغط Enter للتخطي): ";

// للاختبار التلقائي، سنستخدم أول رقم ملف موجود
$testFile = DB::table('data')
    ->whereNotNull('data_file_number')
    ->select('data_file_number')
    ->first();

if ($testFile) {
    $fileNumber = $testFile->data_file_number;
    echo "رقم الملف للاختبار: " . $fileNumber . PHP_EOL . PHP_EOL;

    // جلب المعيل
    $guardian = DB::table('data')
        ->where('data_file_number', $fileNumber)
        ->where('data_relation_type', 1)
        ->first();

    if ($guardian) {
        echo "المعيل: {$guardian->data_first_name} {$guardian->data_family_name} (هوية: {$guardian->data_id_number})" . PHP_EOL;

        $guardianAttachments = DB::table('attachments')
            ->where('person_identity_number', $guardian->data_id_number)
            ->get();

        echo "عدد مرفقات المعيل: " . $guardianAttachments->count() . PHP_EOL;

        if ($guardianAttachments->count() > 0) {
            foreach ($guardianAttachments as $att) {
                echo "  - النوع: {$att->file_type} | {$att->stored_file_name}" . PHP_EOL;
            }
        }
    } else {
        echo "لم يتم العثور على معيل لهذا الملف" . PHP_EOL;
    }

    // جلب الأيتام
    $orphans = DB::table('re_people')
        ->where('file_number', $fileNumber)
        ->get();

    echo PHP_EOL . "عدد الأيتام في الملف: " . $orphans->count() . PHP_EOL;

    if ($orphans->count() > 0) {
        foreach ($orphans as $orphan) {
            $orphanAttachments = DB::table('attachments')
                ->where('person_identity_number', $orphan->person_id)
                ->get();

            echo "  - {$orphan->first_name} {$orphan->family_name} (هوية: {$orphan->person_id}) - المرفقات: {$orphanAttachments->count()}" . PHP_EOL;
        }
    }
}

echo PHP_EOL . "انتهى الاختبار." . PHP_EOL;
