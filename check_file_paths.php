<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== فحص مسارات الملفات ومشكلة عرض الصور ===" . PHP_EOL;

// 1. فحص الملفات في مجلد محدد
$folderName = '000014'; // مجلد للاختبار
echo "فحص مجلد: " . $folderName . PHP_EOL;

$files = DB::table('enhanced_attachments')
    ->where('record_number', $folderName)
    ->whereIn('file_type', ['image', 'photo', 'document', 'pdf', 'excel'])
    ->whereNull('deleted_at')
    ->get();

echo "عدد الملفات في المجلد: " . count($files) . PHP_EOL . PHP_EOL;

foreach ($files as $file) {
    echo "📄 الملف: " . $file->original_file_name . PHP_EOL;
    echo "   النوع: " . $file->file_type . PHP_EOL;
    echo "   المسار: " . $file->file_path . PHP_EOL;
    echo "   الامتداد: " . $file->file_extension . PHP_EOL;
    echo "   MIME: " . $file->mime_type . PHP_EOL;

    // التحقق من وجود الملف فعلياً
    $fullPath = public_path($file->file_path);
    $exists = file_exists($fullPath);
    echo "   هل الملف موجود؟ " . ($exists ? "✅ نعم" : "❌ لا") . PHP_EOL;
    echo "   المسار الكامل: " . $fullPath . PHP_EOL;

    // اختبار رابط الوصول
    $url = asset($file->file_path);
    echo "   رابط الوصول: " . $url . PHP_EOL;
    echo "   ---" . PHP_EOL;
}

// 2. فحص مجلد التخزين العام
echo PHP_EOL . "=== فحص مجلد التخزين ===" . PHP_EOL;
$storagePath = public_path('storage');
echo "مجلد storage موجود؟ " . (is_dir($storagePath) ? "✅ نعم" : "❌ لا") . PHP_EOL;

if (is_dir($storagePath)) {
    $uploadPath = $storagePath . '/uploads';
    echo "مجلد uploads موجود؟ " . (is_dir($uploadPath) ? "✅ نعم" : "❌ لا") . PHP_EOL;

    if (is_dir($uploadPath)) {
        $folders = scandir($uploadPath);
        $folders = array_filter($folders, function($item) use ($uploadPath) {
            return $item !== '.' && $item !== '..' && is_dir($uploadPath . '/' . $item);
        });
        echo "المجلدات الفرعية: " . implode(', ', $folders) . PHP_EOL;
    }
}

echo PHP_EOL . "انتهى الفحص." . PHP_EOL;
