<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Checking Excel files in enhanced_attachments table:\n";

// Test Excel files
$excelFiles = DB::table('enhanced_attachments')
    ->where('file_type', 'excel')
    ->whereNull('deleted_at')
    ->take(5)
    ->get(['id', 'original_file_name', 'stored_file_name', 'file_path', 'record_number', 'file_extension']);

echo "Found " . $excelFiles->count() . " Excel files:\n";

foreach ($excelFiles as $file) {
    echo "ID: {$file->id} | Name: {$file->stored_file_name} | Record: {$file->record_number} | Extension: {$file->file_extension}\n";
}

// Test specific folder
$specificFolder = '000188';
$folderExcelFiles = DB::table('enhanced_attachments')
    ->where('record_number', $specificFolder)
    ->where('file_type', 'excel')
    ->whereNull('deleted_at')
    ->get(['id', 'original_file_name', 'stored_file_name', 'file_path', 'record_number', 'file_extension']);

echo "\nExcel files in folder {$specificFolder}: " . $folderExcelFiles->count() . "\n";

foreach ($folderExcelFiles as $file) {
    echo "ID: {$file->id} | Name: {$file->stored_file_name} | Path: {$file->file_path}\n";
}

// Test if table has any Excel files with different file_type values
echo "\nChecking all file types:\n";
$fileTypes = DB::table('enhanced_attachments')
    ->select('file_type', DB::raw('COUNT(*) as count'))
    ->whereNull('deleted_at')
    ->groupBy('file_type')
    ->get();

foreach ($fileTypes as $type) {
    echo "Type: {$type->file_type} | Count: {$type->count}\n";
}

echo "\nDone.\n";
