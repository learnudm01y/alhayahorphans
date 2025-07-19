<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== فحص هيكل جدول enhanced_attachments ===" . PHP_EOL;

// الحصول على هيكل الجدول
$columns = DB::select("DESCRIBE enhanced_attachments");
echo "الأعمدة الموجودة:" . PHP_EOL;
foreach ($columns as $column) {
    echo "- " . $column->Field . " (" . $column->Type . ")" . PHP_EOL;
}

echo PHP_EOL . "=== تجربة استعلام جديد ===" . PHP_EOL;

try {
    // استعلام جديد بدون original_folder_name
    $query = DB::table('enhanced_attachments')
        ->select(DB::raw('
            SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, "/", -2), "/", 1) as folder_name,
            COUNT(*) as files_count,
            SUM(file_size) as total_size,
            MAX(updated_at) as last_modified,
            GROUP_CONCAT(DISTINCT file_type) as file_types,
            GROUP_CONCAT(DISTINCT mime_type) as mime_types
        '))
        ->where('file_path', 'LIKE', '%storage/%')
        ->whereIn('file_type', ['image', 'photo', 'document', 'pdf'])
        ->whereNull('deleted_at')
        ->groupBy('folder_name')
        ->orderBy('last_modified', 'desc');

    $results = $query->get();
    echo "عدد المجلدات الناتجة: " . count($results) . PHP_EOL;

    foreach ($results as $folder) {
        echo "المجلد: " . $folder->folder_name . " | الملفات: " . $folder->files_count . " | الأنواع: " . $folder->file_types . PHP_EOL;
    }

} catch (Exception $e) {
    echo "خطأ في الاستعلام الجديد: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "=== تحليل مسارات الملفات ===" . PHP_EOL;

// تحليل مسارات الملفات الموجودة
$files = DB::table('enhanced_attachments')
    ->select('file_path', 'file_type')
    ->where('file_type', 'image')
    ->take(10)
    ->get();

foreach ($files as $file) {
    $pathParts = explode('/', $file->file_path);
    echo "المسار: " . $file->file_path . PHP_EOL;
    echo "أجزاء المسار: " . implode(' | ', $pathParts) . PHP_EOL;
    if (count($pathParts) >= 2) {
        echo "المجلد المستخرج: " . $pathParts[count($pathParts) - 2] . PHP_EOL;
    }
    echo "---" . PHP_EOL;
}

echo PHP_EOL . "انتهى التحليل." . PHP_EOL;
