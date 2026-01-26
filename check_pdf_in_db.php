<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== فحص ملفات PDF في قاعدة البيانات ===\n\n";

// البحث عن ملفات PDF في جدول attachments
$pdfFiles = DB::table('attachments')
    ->where('file_type', 'pdf')
    ->where('person_identity_number', '031734')
    ->orderBy('created_at', 'desc')
    ->get();

echo "عدد ملفات PDF المحفوظة: " . $pdfFiles->count() . "\n\n";

if ($pdfFiles->count() > 0) {
    echo "آخر 5 ملفات PDF:\n";
    echo str_repeat("-", 80) . "\n";

    foreach ($pdfFiles->take(5) as $file) {
        echo "ID: {$file->id}\n";
        echo "اسم الملف: {$file->stored_file_name}\n";
        echo "المسار: {$file->file_path}\n";
        echo "الحجم: " . number_format($file->file_size / 1024, 2) . " KB\n";
        echo "تاريخ الإنشاء: {$file->created_at}\n";
        echo str_repeat("-", 80) . "\n";
    }

    echo "\n✅ ملفات PDF محفوظة في قاعدة البيانات بنجاح!\n";
} else {
    echo "❌ لا توجد ملفات PDF في قاعدة البيانات\n";
    echo "\nالبحث عن ملفات PDF في المجلد الفعلي:\n";

    $folderPath = storage_path('app/public/uploads/031734');
    if (is_dir($folderPath)) {
        $files = glob($folderPath . '/*.pdf');
        echo "عدد الملفات في المجلد: " . count($files) . "\n";

        foreach (array_slice($files, -3) as $file) {
            echo "  - " . basename($file) . " (" . number_format(filesize($file) / 1024, 2) . " KB)\n";
        }
    }
}
