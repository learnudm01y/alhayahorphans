<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== فحص شامل للبيانات والمجلدات ===" . PHP_EOL;

// 1. فحص إجمالي البيانات
$totalFiles = DB::table('enhanced_attachments')->count();
echo "إجمالي الملفات في النظام: " . $totalFiles . PHP_EOL;

// 2. فحص أنواع الملفات الموجودة
$fileTypes = DB::table('enhanced_attachments')
    ->select('file_type', DB::raw('COUNT(*) as count'))
    ->groupBy('file_type')
    ->get();

echo PHP_EOL . "أنواع الملفات الموجودة:" . PHP_EOL;
foreach ($fileTypes as $type) {
    echo "- " . $type->file_type . ": " . $type->count . " ملف" . PHP_EOL;
}

// 3. فحص المسارات
echo PHP_EOL . "عينة من مسارات الملفات:" . PHP_EOL;
$samplePaths = DB::table('enhanced_attachments')
    ->select('file_path', 'file_type')
    ->take(10)
    ->get();

foreach ($samplePaths as $file) {
    echo "- " . $file->file_path . " (" . $file->file_type . ")" . PHP_EOL;
}

// 4. اختبار الشروط المختلفة
echo PHP_EOL . "=== اختبار الشروط ===" . PHP_EOL;

// الشرط الحالي
$currentCondition = DB::table('enhanced_attachments')
    ->where('file_path', 'LIKE', '%storage/%')
    ->whereIn('file_type', ['image', 'photo', 'document', 'pdf'])
    ->whereNull('deleted_at')
    ->count();
echo "الملفات المطابقة للشرط الحالي: " . $currentCondition . PHP_EOL;

// شرط أوسع - جميع أنواع الملفات
$allTypes = DB::table('enhanced_attachments')
    ->where('file_path', 'LIKE', '%storage/%')
    ->whereNull('deleted_at')
    ->count();
echo "الملفات في مجلد storage (جميع الأنواع): " . $allTypes . PHP_EOL;

// شرط بدون تحديد نوع الملف
$noTypeFilter = DB::table('enhanced_attachments')
    ->whereNull('deleted_at')
    ->count();
echo "الملفات غير المحذوفة (بدون فلتر النوع): " . $noTypeFilter . PHP_EOL;

// 5. اختبار استخراج أسماء المجلدات بطرق مختلفة
echo PHP_EOL . "=== اختبار استخراج أسماء المجلدات ===" . PHP_EOL;

// الطريقة الحالية
try {
    $currentMethod = DB::table('enhanced_attachments')
        ->select(DB::raw('
            SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, "/", -2), "/", 1) as folder_name,
            COUNT(*) as files_count
        '))
        ->where('file_path', 'LIKE', '%storage/%')
        ->whereIn('file_type', ['image', 'photo', 'document', 'pdf'])
        ->whereNull('deleted_at')
        ->groupBy('folder_name')
        ->get();

    echo "الطريقة الحالية - عدد المجلدات: " . count($currentMethod) . PHP_EOL;
    foreach ($currentMethod as $folder) {
        echo "  - " . $folder->folder_name . ": " . $folder->files_count . " ملف" . PHP_EOL;
    }
} catch (Exception $e) {
    echo "خطأ في الطريقة الحالية: " . $e->getMessage() . PHP_EOL;
}

// طريقة بديلة - جميع الأنواع
try {
    $allTypesMethod = DB::table('enhanced_attachments')
        ->select(DB::raw('
            SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, "/", -2), "/", 1) as folder_name,
            COUNT(*) as files_count,
            GROUP_CONCAT(DISTINCT file_type) as file_types
        '))
        ->where('file_path', 'LIKE', '%storage/%')
        ->whereNull('deleted_at')
        ->groupBy('folder_name')
        ->get();

    echo PHP_EOL . "طريقة جميع الأنواع - عدد المجلدات: " . count($allTypesMethod) . PHP_EOL;
    foreach ($allTypesMethod as $folder) {
        echo "  - " . $folder->folder_name . ": " . $folder->files_count . " ملف (" . $folder->file_types . ")" . PHP_EOL;
    }
} catch (Exception $e) {
    echo "خطأ في طريقة جميع الأنواع: " . $e->getMessage() . PHP_EOL;
}

// 6. فحص مسارات فريدة
echo PHP_EOL . "=== المسارات الفريدة ===" . PHP_EOL;
$uniquePaths = DB::table('enhanced_attachments')
    ->select(DB::raw('DISTINCT SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, "/", -2), "/", 1) as folder_name'))
    ->where('file_path', 'LIKE', '%storage/%')
    ->whereNull('deleted_at')
    ->get();

echo "المجلدات الفريدة الموجودة (" . count($uniquePaths) . "):" . PHP_EOL;
foreach ($uniquePaths as $path) {
    echo "- " . $path->folder_name . PHP_EOL;
}

echo PHP_EOL . "انتهى الفحص الشامل." . PHP_EOL;
