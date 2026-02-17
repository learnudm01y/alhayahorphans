<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$design = DB::table('sponsor_report_designs')
    ->where('sponsor_id', 1)
    ->first();

if ($design) {
    echo "=== تصميم التقرير للكفيل رقم 1 ===\n";
    echo "ID: {$design->id}\n";
    echo "نوع الخلفية: {$design->background_type}\n";
    echo "صورة واحدة: " . ($design->single_image ?? 'غير موجودة') . "\n";
    echo "صورة الرأس: " . ($design->header_image ?? 'غير موجودة') . "\n";
    echo "الصورة الرئيسية: " . ($design->main_image ?? 'غير موجودة') . "\n";
    echo "صورة التذييل: " . ($design->footer_image ?? 'غير موجودة') . "\n";

    // التحقق من وجود الملفات
    $paths = [
        'single_image' => $design->single_image,
        'header_image' => $design->header_image,
        'main_image' => $design->main_image,
        'footer_image' => $design->footer_image,
    ];

    echo "\n=== حالة الملفات ===\n";
    foreach ($paths as $key => $filename) {
        if ($filename) {
            $fullPath = storage_path("app/public/report_designs/{$filename}");
            $exists = file_exists($fullPath);
            echo "{$key}: " . ($exists ? "✓ موجود" : "✗ غير موجود") . " ({$filename})\n";
        }
    }

    // عرض الملفات الموجودة فعلياً في المجلد
    echo "\n=== الملفات الموجودة في المجلد ===\n";
    $actualFiles = glob(storage_path("app/public/report_designs/*"));
    foreach ($actualFiles as $file) {
        echo basename($file) . "\n";
    }
} else {
    echo "لا يوجد تصميم للكفيل رقم 1\n";
}
