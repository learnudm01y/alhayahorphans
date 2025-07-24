<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Testing Excel file access:\n";

$filename = '1752304111_newTest101.xlsx';
echo "Testing Excel file: {$filename}\n\n";

// Test search in enhanced_attachments table
echo "1. Checking enhanced_attachments table:\n";
$enhancedFile = DB::table('enhanced_attachments')
    ->where('stored_file_name', $filename)
    ->orWhere('original_file_name', $filename)
    ->whereNull('deleted_at')
    ->first();

if ($enhancedFile) {
    echo "   ✅ Found in enhanced_attachments: {$enhancedFile->file_path}\n";
    echo "   📁 Record number: {$enhancedFile->record_number}\n";
} else {
    echo "   ❌ Not found in enhanced_attachments\n";
}

// Test physical file search
echo "2. Checking physical file paths:\n";

$searchPaths = [
    storage_path('app/public/documents/excel/' . $filename),
    storage_path('app/public/documents/' . $filename),
    storage_path('app/public/uploads/' . $filename),
];

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
