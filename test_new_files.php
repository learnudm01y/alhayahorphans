<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== اختبار الملفات الجديدة في مجلد 000010 ===" . PHP_EOL;

$files = DB::table('enhanced_attachments')
    ->where('record_number', '000010')
    ->whereIn('file_type', ['image', 'pdf', 'excel'])
    ->whereNull('deleted_at')
    ->orderBy('created_at', 'desc')
    ->get();

echo "عدد الملفات في المجلد 000010: " . count($files) . PHP_EOL . PHP_EOL;

foreach ($files as $file) {
    echo "📄 الملف: " . $file->original_file_name . PHP_EOL;
    echo "   النوع: " . $file->file_type . PHP_EOL;
    echo "   المسار: " . $file->file_path . PHP_EOL;

    // التحقق من وجود الملف فعلياً
    $fullPath = public_path($file->file_path);
    $exists = file_exists($fullPath);
    echo "   هل الملف موجود؟ " . ($exists ? "✅ نعم" : "❌ لا") . PHP_EOL;

    if ($exists) {
        echo "   حجم الملف: " . number_format(filesize($fullPath) / 1024, 2) . " KB" . PHP_EOL;
        echo "   رابط الوصول: " . asset($file->file_path) . PHP_EOL;
    }

    echo "   ---" . PHP_EOL;
}

// اختبار الوصول المباشر للصورة
$imagePath = public_path('storage/uploads/000010/images/000010_sample.jpg');
if (file_exists($imagePath)) {
    echo PHP_EOL . "✅ الصورة موجودة ويمكن الوصول إليها!" . PHP_EOL;
    echo "رابط الصورة: http://localhost:8000/storage/uploads/000010/images/000010_sample.jpg" . PHP_EOL;
} else {
    echo PHP_EOL . "❌ الصورة غير موجودة!" . PHP_EOL;
}

echo PHP_EOL . "انتهى الاختبار." . PHP_EOL;
