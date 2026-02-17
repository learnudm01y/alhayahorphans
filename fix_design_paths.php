<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== فحص وإصلاح بيانات تصميم التقرير ===\n\n";

// جلب جميع الملفات الموجودة فعلياً في المجلد
$actualFiles = glob(storage_path("app/public/report_designs/*"));
$actualFileNames = array_map('basename', $actualFiles);

echo "الملفات الموجودة في المجلد:\n";
foreach ($actualFileNames as $file) {
    echo "  - {$file}\n";
}
echo "\n";

// جلب التصميم من قاعدة البيانات
$design = DB::table('sponsor_report_designs')->where('sponsor_id', 1)->first();

if (!$design) {
    echo "لا يوجد تصميم للكفيل رقم 1\n";
    exit;
}

echo "البيانات الحالية في قاعدة البيانات:\n";
echo "  single_image: " . ($design->single_image ?? 'null') . "\n";
echo "  header_image: " . ($design->header_image ?? 'null') . "\n";
echo "  main_image: " . ($design->main_image ?? 'null') . "\n";
echo "  footer_image: " . ($design->footer_image ?? 'null') . "\n";
echo "\n";

// فحص كل صورة في قاعدة البيانات ومقارنتها بالملفات الموجودة
$updates = [];
$errors = [];

$imageFields = ['single_image', 'header_image', 'main_image', 'footer_image'];

foreach ($imageFields as $field) {
    $dbPath = $design->$field;

    if (!$dbPath) {
        continue;
    }

    // الحصول على اسم الملف فقط من المسار المخزن
    $fileName = basename($dbPath);

    // التحقق من وجود الملف
    $fullPath = storage_path("app/public/report_designs/{$fileName}");

    if (!file_exists($fullPath)) {
        $errors[] = "{$field}: الملف غير موجود - {$fileName}";

        echo "  ✗ {$field}: الملف غير موجود - {$fileName}\n";
        echo "    خيار 1: تعيين null (حذف الصورة من قاعدة البيانات)\n";
        echo "    خيار 2: استخدام أحد الملفات الموجودة كبديل\n";

        // إذا كان الحقل single_image وكان هناك ملفات متاحة، نستخدم الأول منها
        if (!empty($actualFileNames)) {
            $newFile = reset($actualFileNames);
            $updates[$field] = "report_designs/{$newFile}";
            echo "    → سيتم استخدام: {$newFile}\n";

            // إزالة الملف من القائمة لتجنب استخدامه في حقل آخر
            $actualFileNames = array_diff($actualFileNames, [$newFile]);
        } else {
            $updates[$field] = null;
            echo "    → سيتم تعيين null (لا توجد ملفات بديلة)\n";
        }
    } else {
        echo "  ✓ {$field}: الملف موجود - {$fileName}\n";
    }
}

if (!empty($errors)) {
    echo "\n=== الأخطاء الموجودة ===\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
}

if (!empty($updates)) {
    echo "\n=== تحديث قاعدة البيانات ===\n";

    foreach ($updates as $field => $newPath) {
        echo "  تحديث {$field} إلى: {$newPath}\n";
    }

    DB::table('sponsor_report_designs')
        ->where('sponsor_id', 1)
        ->update($updates);

    echo "\n✓ تم تحديث قاعدة البيانات بنجاح\n";
} else {
    echo "\n✓ لا توجد تحديثات مطلوبة\n";
}

echo "\n=== التحقق النهائي ===\n";
$updatedDesign = DB::table('sponsor_report_designs')->where('sponsor_id', 1)->first();
echo "البيانات بعد التحديث:\n";
echo "  single_image: " . ($updatedDesign->single_image ?? 'null') . "\n";
echo "  header_image: " . ($updatedDesign->header_image ?? 'null') . "\n";
echo "  main_image: " . ($updatedDesign->main_image ?? 'null') . "\n";
echo "  footer_image: " . ($updatedDesign->footer_image ?? 'null') . "\n";
