<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Testing secure file access after fix:\n";

$filename = 'TE102_000045_121.png';
echo "Testing file: {$filename}\n\n";

// Test search in attachments table
echo "1. Checking attachments table:\n";
$attachmentFile = DB::table('attachments')
    ->where('stored_file_name', $filename)
    ->first();

if ($attachmentFile) {
    echo "   ✅ Found in attachments: {$attachmentFile->file_path}\n";
} else {
    echo "   ❌ Not found in attachments\n";
}

// Test search in enhanced_attachments table
echo "2. Checking enhanced_attachments table:\n";
$enhancedFile = DB::table('enhanced_attachments')
    ->where('stored_file_name', $filename)
    ->orWhere('original_file_name', $filename)
    ->whereNull('deleted_at')
    ->first();

if ($enhancedFile) {
    echo "   ✅ Found in enhanced_attachments: {$enhancedFile->file_path}\n";
} else {
    echo "   ❌ Not found in enhanced_attachments\n";
}

// Test physical file search
echo "3. Checking physical file paths:\n";

// Extract folder from filename if possible
$folderNumber = null;
if (preg_match('/^(TES-11|TE102)_(\d{6})_/', $filename, $matches)) {
    $folderNumber = $matches[2];
    echo "   📁 Detected folder: {$folderNumber}\n";
}

$searchPaths = [
    storage_path('app/public/uploads/' . $filename),
    storage_path('app/public/uploads/000045/' . $filename),
    storage_path('app/public/uploads/000188/' . $filename),
    storage_path('app/public/uploads/001541/' . $filename),
];

if ($folderNumber) {
    $searchPaths[] = storage_path("app/public/uploads/{$folderNumber}/{$filename}");
}

foreach ($searchPaths as $path) {
    if (file_exists($path)) {
        echo "   ✅ Found at: {$path}\n";
        echo "   📏 Size: " . filesize($path) . " bytes\n";
        break;
    } else {
        echo "   ❌ Not found: {$path}\n";
    }
}

echo "\nDone.\n";
