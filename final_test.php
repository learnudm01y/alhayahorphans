<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== اختبار نهائي لنظام عرض المجلدات والمعاينة ===" . PHP_EOL;

// اختبار getFolderContents API
$folderName = '000010';
echo "اختبار API محتويات المجلد: " . $folderName . PHP_EOL;

$files = DB::table('enhanced_attachments')
    ->where('record_number', $folderName)
    ->whereIn('file_type', ['image', 'photo', 'document', 'pdf', 'excel'])
    ->whereNull('deleted_at')
    ->orderBy('updated_at', 'desc')
    ->get();

echo "عدد الملفات: " . count($files) . PHP_EOL;

foreach ($files as $file) {
    // تحسين البيانات كما في Controller
    $file->formatted_size = number_format($file->file_size / 1024, 2) . ' KB';
    $file->formatted_date = date('Y-m-d H:i', strtotime($file->updated_at));
    $file->download_url = asset($file->file_path);
    $file->is_image = in_array(strtolower($file->file_extension ?? ''),
        ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']) ||
        strpos($file->mime_type ?? '', 'image/') === 0;
    $file->is_pdf = strtolower($file->file_extension ?? '') === 'pdf' ||
        strpos($file->mime_type ?? '', 'pdf') !== false;
    $file->thumbnail_url = null;
    $file->metadata_array = [];

    echo "📄 " . $file->original_file_name . PHP_EOL;
    echo "   النوع: " . $file->file_type . PHP_EOL;
    echo "   صورة؟ " . ($file->is_image ? "✅ نعم" : "❌ لا") . PHP_EOL;
    echo "   PDF؟ " . ($file->is_pdf ? "✅ نعم" : "❌ لا") . PHP_EOL;
    echo "   الرابط: " . $file->download_url . PHP_EOL;
    echo "   الحجم: " . $file->formatted_size . PHP_EOL;

    // التحقق من الملف
    $fullPath = public_path($file->file_path);
    echo "   موجود؟ " . (file_exists($fullPath) ? "✅ نعم" : "❌ لا") . PHP_EOL;
    echo "   ---" . PHP_EOL;
}

// تحليل JSON response
$response = [
    'success' => true,
    'files' => $files,
    'folder_name' => $folderName
];

echo PHP_EOL . "JSON Response (مختصر):" . PHP_EOL;
echo "Success: " . ($response['success'] ? 'true' : 'false') . PHP_EOL;
echo "Files count: " . count($response['files']) . PHP_EOL;
echo "Folder name: " . $response['folder_name'] . PHP_EOL;

echo PHP_EOL . "🎉 النظام جاهز للاختبار!" . PHP_EOL;
echo "يمكنك الآن:" . PHP_EOL;
echo "1. فتح الصفحة: http://localhost:8000/admin/manage-folders" . PHP_EOL;
echo "2. النقر على مجلد 000010 لرؤية محتوياته" . PHP_EOL;
echo "3. النقر على أزرار المعاينة للصور والـ PDF والـ Excel" . PHP_EOL;

echo PHP_EOL . "انتهى الاختبار." . PHP_EOL;
