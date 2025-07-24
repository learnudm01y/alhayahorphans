<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Excel folders with files:\n";

// Get Excel folders
$excelFolders = DB::table('enhanced_attachments')
    ->select('record_number', DB::raw('COUNT(*) as files_count'))
    ->where('file_type', 'excel')
    ->whereNull('deleted_at')
    ->whereNotNull('record_number')
    ->where('record_number', '!=', '')
    ->groupBy('record_number')
    ->orderBy('files_count', 'desc')
    ->get();

echo "Found " . $excelFolders->count() . " folders with Excel files:\n";

foreach ($excelFolders as $folder) {
    echo "Folder: {$folder->record_number} | Files: {$folder->files_count}\n";
}

// Get sample Excel files from top folder
if ($excelFolders->count() > 0) {
    $topFolder = $excelFolders->first()->record_number;
    echo "\nSample files from folder {$topFolder}:\n";

    $sampleFiles = DB::table('enhanced_attachments')
        ->where('record_number', $topFolder)
        ->where('file_type', 'excel')
        ->whereNull('deleted_at')
        ->take(3)
        ->get(['id', 'original_file_name', 'stored_file_name', 'file_path', 'file_extension']);

    foreach ($sampleFiles as $file) {
        echo "ID: {$file->id} | Name: {$file->stored_file_name} | Path: {$file->file_path}\n";
    }
}

echo "\nDone.\n";
