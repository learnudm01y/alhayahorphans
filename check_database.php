<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== فحص قواعد البيانات ===" . PHP_EOL;

// فحص جدول enhanced_attachments
try {
    $enhancedCount = DB::table('enhanced_attachments')->count();
    echo "عدد السجلات في enhanced_attachments: " . $enhancedCount . PHP_EOL;

    if ($enhancedCount > 0) {
        $sample = DB::table('enhanced_attachments')->first();
        echo "مثال على السجل الأول:" . PHP_EOL;
        print_r($sample);

        // فحص الحقول المطلوبة
        $fieldsCheck = DB::table('enhanced_attachments')
            ->select(['file_type', 'original_folder_name', 'file_path'])
            ->whereNotNull('file_type')
            ->take(5)
            ->get();

        echo "الحقول المطلوبة:" . PHP_EOL;
        foreach ($fieldsCheck as $record) {
            echo "File Type: " . ($record->file_type ?? 'NULL') .
                 " | Folder: " . ($record->original_folder_name ?? 'NULL') .
                 " | Path: " . ($record->file_path ?? 'NULL') . PHP_EOL;
        }
    }
} catch (Exception $e) {
    echo "خطأ في enhanced_attachments: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "=== فحص جدول attachments ===" . PHP_EOL;

// فحص جدول attachments العادي
try {
    $attachmentsCount = DB::table('attachments')->count();
    echo "عدد السجلات في attachments: " . $attachmentsCount . PHP_EOL;

    if ($attachmentsCount > 0) {
        $sample = DB::table('attachments')->first();
        echo "مثال على السجل الأول:" . PHP_EOL;
        print_r($sample);
    }
} catch (Exception $e) {
    echo "خطأ في attachments: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "=== فحص الاستعلام المستخدم في Controller ===" . PHP_EOL;

try {
    $query = DB::table('enhanced_attachments')
        ->select(DB::raw('
            COALESCE(original_folder_name, SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, "/", -2), "/", 1)) as folder_name,
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

    echo "SQL Query: " . $query->toSql() . PHP_EOL;
    echo "Bindings: " . json_encode($query->getBindings()) . PHP_EOL;

    $results = $query->get();
    echo "عدد المجلدات الناتجة: " . count($results) . PHP_EOL;

    foreach ($results as $folder) {
        echo "المجلد: " . $folder->folder_name . " | الملفات: " . $folder->files_count . " | الأنواع: " . $folder->file_types . PHP_EOL;
    }

} catch (Exception $e) {
    echo "خطأ في الاستعلام: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "انتهى الفحص." . PHP_EOL;
