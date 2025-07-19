<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== اختبار الاستعلام الجديد للمجلدات بأرقام السجلات ===" . PHP_EOL;

try {
    // اختبار استعلام المجلدات الجديد
    $query = DB::table('enhanced_attachments')
        ->select(DB::raw('
            record_number as folder_name,
            COUNT(*) as files_count,
            SUM(file_size) as total_size,
            MAX(updated_at) as last_modified,
            GROUP_CONCAT(DISTINCT file_type) as file_types,
            GROUP_CONCAT(DISTINCT mime_type) as mime_types
        '))
        ->whereNotNull('record_number')
        ->where('record_number', '!=', '')
        ->whereIn('file_type', ['image', 'photo', 'document', 'pdf', 'excel'])
        ->whereNull('deleted_at')
        ->groupBy('record_number')
        ->orderBy('last_modified', 'desc');

    echo "SQL Query: " . $query->toSql() . PHP_EOL;
    echo "Bindings: " . json_encode($query->getBindings()) . PHP_EOL . PHP_EOL;

    $results = $query->get();
    echo "عدد المجلدات الناتجة: " . count($results) . PHP_EOL . PHP_EOL;

    foreach ($results as $index => $folder) {
        echo "📁 المجلد " . ($index + 1) . ": " . $folder->folder_name . PHP_EOL;
        echo "   عدد الملفات: " . $folder->files_count . PHP_EOL;
        echo "   الحجم الكلي: " . number_format(($folder->total_size ?? 0) / 1024, 2) . " KB" . PHP_EOL;
        echo "   آخر تعديل: " . $folder->last_modified . PHP_EOL;
        echo "   أنواع الملفات: " . $folder->file_types . PHP_EOL;
        echo "   ---" . PHP_EOL;
    }

    echo PHP_EOL . "✅ الآن ستظهر المجلدات بأرقام السجلات كما في الصورة: " . count($results) . " مجلد" . PHP_EOL;

} catch (Exception $e) {
    echo "❌ خطأ في الاستعلام: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "انتهى الاختبار." . PHP_EOL;
