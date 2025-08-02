<?php
require 'vendor/autoload.php';

// Initialize Laravel app
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 Testing Folder Management System\n";
echo "=================================\n\n";

try {
    // Test 1: Check database connection
    echo "1. Database Connection Test:\n";
    $attachmentCount = DB::table('attachments')->count();
    echo "   ✅ Attachments table: {$attachmentCount} records\n";

    $enhancedCount = 0;
    if (DB::getSchemaBuilder()->hasTable('enhanced_attachments')) {
        $enhancedCount = DB::table('enhanced_attachments')->count();
        echo "   ✅ Enhanced attachments table: {$enhancedCount} records\n";
    } else {
        echo "   ⚠️ Enhanced attachments table not found\n";
    }

    // Test 2: Check physical paths
    echo "\n2. Physical Path Test:\n";
    $storagePath = storage_path('app/public/uploads');
    $publicPath = public_path('storage/uploads');

    echo "   Storage path: {$storagePath}\n";
    echo "   Storage exists: " . (is_dir($storagePath) ? "✅ YES" : "❌ NO") . "\n";

    echo "   Public path: {$publicPath}\n";
    echo "   Public exists: " . (is_dir($publicPath) ? "✅ YES" : "❌ NO") . "\n";

    // Test 3: Count folders in storage
    if (is_dir($storagePath)) {
        $folders = array_filter(glob($storagePath . '/*'), 'is_dir');
        echo "   📁 Folders in storage: " . count($folders) . "\n";

        // Show first 5 folders
        echo "   First 5 folders:\n";
        foreach (array_slice($folders, 0, 5) as $folder) {
            $folderName = basename($folder);
            $fileCount = count(glob($folder . '/*'));
            echo "      - {$folderName} ({$fileCount} items)\n";
        }
    }

    // Test 4: Sample query from attachments
    echo "\n3. Sample Database Query Test:\n";
    $sampleAttachments = DB::table('attachments')
        ->where('file_path', 'LIKE', '%uploads%')
        ->take(3)
        ->get(['id', 'file_path', 'stored_file_name', 'person_identity_number']);

    echo "   Sample attachments with uploads path:\n";
    foreach ($sampleAttachments as $att) {
        echo "      - ID: {$att->id}, Path: {$att->file_path}\n";
        echo "        File: {$att->stored_file_name}, Person: {$att->person_identity_number}\n";
    }

    // Test 5: Test folder extraction logic
    echo "\n4. Folder Extraction Test:\n";
    $folderExtractionQuery = DB::table('attachments')
        ->select(DB::raw("
            CASE
                WHEN file_path LIKE '%storage/uploads/%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, '/', -2), '/', 1)
                WHEN file_path LIKE '%uploads/%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, 'uploads/', -1), '/', 1)
                ELSE person_identity_number
            END as folder_name,
            COUNT(*) as files_count
        "))
        ->where(function($query) {
            $query->where('file_path', 'LIKE', '%storage/uploads/%')
                  ->orWhere('file_path', 'LIKE', '%uploads/%')
                  ->orWhereNotNull('person_identity_number');
        })
        ->whereNotNull('file_path')
        ->where('file_path', '!=', '')
        ->groupBy(DB::raw("
            CASE
                WHEN file_path LIKE '%storage/uploads/%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, '/', -2), '/', 1)
                WHEN file_path LIKE '%uploads/%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, 'uploads/', -1), '/', 1)
                ELSE person_identity_number
            END
        "))
        ->take(5)
        ->get();

    echo "   Extracted folders from database:\n";
    foreach ($folderExtractionQuery as $folder) {
        echo "      - Folder: {$folder->folder_name} ({$folder->files_count} files)\n";
    }

    echo "\n✅ All tests completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
