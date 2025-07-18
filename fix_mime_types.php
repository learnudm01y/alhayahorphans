<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\DuplicateFileTemp;

echo "🔧 إصلاح mime_type للملفات المكررة...\n";

$files = DuplicateFileTemp::whereNull('mime_type')->orWhere('mime_type', '')->get();

echo "📊 عدد الملفات التي تحتاج إصلاح: " . $files->count() . "\n";

$updated = 0;

foreach ($files as $file) {
    $extension = pathinfo($file->original_name, PATHINFO_EXTENSION);
    $mimeType = getMimeTypeFromExtension(strtolower($extension));

    $file->mime_type = $mimeType;
    $file->save();

    echo "✅ تم تحديث: {$file->original_name} -> {$mimeType}\n";
    $updated++;
}

echo "🎉 تم إصلاح {$updated} ملف بنجاح!\n";

function getMimeTypeFromExtension($extension)
{
    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'mp4' => 'video/mp4',
        'avi' => 'video/avi',
        'mov' => 'video/quicktime',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
    ];

    return $mimeTypes[$extension] ?? 'application/octet-stream';
}
