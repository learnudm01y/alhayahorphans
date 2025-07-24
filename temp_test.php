<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Testing folder 000188 with improved search:\n";

// Test improved search
$files = DB::table('attachments')
    ->where(function($query) {
        $folderName = '000188';
        $query->where('file_path', 'LIKE', "%/{$folderName}/%")
              ->orWhere('file_path', 'LIKE', "%uploads/{$folderName}/%")
              ->orWhere('file_path', 'LIKE', "storage/uploads/{$folderName}/%")
              ->orWhere('person_identity_number', $folderName);
    })
    ->whereNotNull('file_path')
    ->where('file_path', '!=', '')
    ->get(['id', 'stored_file_name', 'file_path', 'file_size', 'person_identity_number']);

echo "Found " . $files->count() . " files with improved search:\n";

foreach ($files as $file) {
    echo "ID: {$file->id} | Name: {$file->stored_file_name} | Path: {$file->file_path}\n";
}

echo "\nDone.\n";
