<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== اختبار الاستعلام المُصحح ===" . PHP_EOL;

try {
    // اختبار الاستعلام الجديد
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

    echo "SQL Query: " . $query->toSql() . PHP_EOL;
    echo "Bindings: " . json_encode($query->getBindings()) . PHP_EOL . PHP_EOL;

    $results = $query->get();
    echo "عدد المجلدات الناتجة: " . count($results) . PHP_EOL . PHP_EOL;

    foreach ($results as $index => $folder) {
        echo "المجلد " . ($index + 1) . ":" . PHP_EOL;
        echo "  الاسم: " . $folder->folder_name . PHP_EOL;
        echo "  عدد الملفات: " . $folder->files_count . PHP_EOL;
        echo "  الحجم الكلي: " . number_format($folder->total_size / 1024, 2) . " KB" . PHP_EOL;
        echo "  آخر تعديل: " . $folder->last_modified . PHP_EOL;
        echo "  أنواع الملفات: " . $folder->file_types . PHP_EOL;
        echo "  أنواع MIME: " . $folder->mime_types . PHP_EOL;
        echo "  ---" . PHP_EOL;
    }

    if (count($results) > 0) {
        echo PHP_EOL . "✅ الاستعلام يعمل بشكل صحيح والمجلدات موجودة!" . PHP_EOL;
    } else {
        echo PHP_EOL . "❌ لا توجد مجلدات تطابق المعايير." . PHP_EOL;

        // فحص إضافي
        echo PHP_EOL . "=== فحص إضافي ===" . PHP_EOL;
        $allFiles = DB::table('enhanced_attachments')
            ->select('file_path', 'file_type', 'deleted_at')
            ->get();

        $matchingFiles = 0;
        $storageFiles = 0;
        $typeMatching = 0;
        $notDeleted = 0;

        foreach ($allFiles as $file) {
            if (strpos($file->file_path, 'storage/') !== false) {
                $storageFiles++;
            }
            if (in_array($file->file_type, ['image', 'photo', 'document', 'pdf'])) {
                $typeMatching++;
            }
            if (is_null($file->deleted_at)) {
                $notDeleted++;
            }
            if (strpos($file->file_path, 'storage/') !== false &&
                in_array($file->file_type, ['image', 'photo', 'document', 'pdf']) &&
                is_null($file->deleted_at)) {
                $matchingFiles++;
            }
        }

        echo "إجمالي الملفات: " . count($allFiles) . PHP_EOL;
        echo "ملفات في مجلد storage: " . $storageFiles . PHP_EOL;
        echo "ملفات بنوع مطابق: " . $typeMatching . PHP_EOL;
        echo "ملفات غير محذوفة: " . $notDeleted . PHP_EOL;
        echo "ملفات مطابقة للشروط: " . $matchingFiles . PHP_EOL;
    }

} catch (Exception $e) {
    echo "❌ خطأ في الاستعلام: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "انتهى الاختبار." . PHP_EOL;
