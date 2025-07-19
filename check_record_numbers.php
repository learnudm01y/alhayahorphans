<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== فحص أرقام السجلات الموجودة ===" . PHP_EOL;

try {
    // جلب أرقام السجلات الفريدة
    $recordNumbers = DB::table('enhanced_attachments')
        ->select('record_number', DB::raw('COUNT(*) as files_count'))
        ->whereNotNull('record_number')
        ->where('record_number', '!=', '')
        ->groupBy('record_number')
        ->orderBy('record_number')
        ->get();

    echo "أرقام السجلات الموجودة (" . count($recordNumbers) . "):" . PHP_EOL;
    foreach ($recordNumbers as $record) {
        echo "📁 " . $record->record_number . " (" . $record->files_count . " ملف)" . PHP_EOL;
    }

    // فحص عينة من البيانات
    echo PHP_EOL . "عينة من البيانات:" . PHP_EOL;
    $sample = DB::table('enhanced_attachments')
        ->select('record_number', 'file_path', 'file_type', 'original_file_name')
        ->whereNotNull('record_number')
        ->where('record_number', '!=', '')
        ->take(10)
        ->get();

    foreach ($sample as $file) {
        echo "  - " . $file->record_number . ": " . $file->original_file_name . " (" . $file->file_type . ")" . PHP_EOL;
    }

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "انتهى الفحص." . PHP_EOL;
