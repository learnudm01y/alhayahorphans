<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Checking enhanced_attachments data...\n";

// Check record_number data
$recordsWithRecordNumber = DB::table('enhanced_attachments')
    ->whereNotNull('record_number')
    ->count();

echo "Records with record_number: {$recordsWithRecordNumber}\n";

$recordsWithoutRecordNumber = DB::table('enhanced_attachments')
    ->whereNull('record_number')
    ->count();

echo "Records without record_number: {$recordsWithoutRecordNumber}\n";

// Sample records
$sampleRecords = DB::table('enhanced_attachments')
    ->select('folder_id', 'record_number', 'original_file_name')
    ->take(5)
    ->get();

echo "\nSample records:\n";
foreach($sampleRecords as $record) {
    echo "folder_id: {$record->folder_id}, record_number: {$record->record_number}, file: {$record->original_file_name}\n";
}

// Check if we can populate record_number from folder_id
if ($recordsWithoutRecordNumber > 0) {
    echo "\nAttempting to populate record_number from folder_id...\n";

    $updated = DB::table('enhanced_attachments')
        ->whereNull('record_number')
        ->whereNotNull('folder_id')
        ->where('folder_id', '!=', '')
        ->update(['record_number' => DB::raw('folder_id')]);

    echo "Updated {$updated} records with record_number from folder_id\n";
}

echo "Data check completed.\n";
