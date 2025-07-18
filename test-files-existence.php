<?php

require_once 'vendor/autoload.php';

// تحميل Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DuplicateFileTemp;

echo "=== فحص وجود الملفات المكررة ===\n\n";

// جلب أول 5 ملفات
$files = DuplicateFileTemp::take(5)->get(['id', 'temp_path', 'original_name']);

foreach($files as $file) {
    echo "ID: {$file->id}\n";
    echo "Name: {$file->original_name}\n";
    echo "Path: {$file->temp_path}\n";
    echo "Exists: " . (file_exists($file->temp_path) ? 'YES' : 'NO') . "\n";

    if (!file_exists($file->temp_path)) {
        echo "ERROR: File not found!\n";
    } else {
        echo "Size: " . filesize($file->temp_path) . " bytes\n";
    }
    echo "---\n";
}

// إحصائيات عامة
$totalFiles = DuplicateFileTemp::count();
echo "\nTotal files in database: {$totalFiles}\n";

// فحص عدد الملفات الموجودة فعلياً
$existingCount = 0;
$allFiles = DuplicateFileTemp::all(['temp_path']);
foreach($allFiles as $file) {
    if (file_exists($file->temp_path)) {
        $existingCount++;
    }
}

echo "Existing files on disk: {$existingCount}\n";
echo "Missing files: " . ($totalFiles - $existingCount) . "\n";
