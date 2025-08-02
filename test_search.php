<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 Testing Search Functionality\n";
echo "================================\n\n";

try {
    // Test search functionality
    $query = '001550';
    $type = 'images';

    echo "Testing search with query: {$query}\n";
    echo "Type: {$type}\n\n";

    // Direct search test
    $attachmentResults = DB::table('attachments')
        ->where(function($q) use ($query) {
            $q->where('stored_file_name', 'LIKE', "%{$query}%")
              ->orWhere('file_path', 'LIKE', "%{$query}%")
              ->orWhere('person_identity_number', 'LIKE', "%{$query}%")
              ->orWhere('person_identity_number', intval($query))
              ->orWhereRaw('SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, "/", -2), "/", 1) LIKE ?', ["%{$query}%"])
              ->orWhereRaw('SUBSTRING_INDEX(SUBSTRING_INDEX(file_path, "/", -2), "/", 1) = ?', [intval($query)]);
        })
        ->where(function($query) {
            $query->where('file_path', 'LIKE', '%storage/uploads/%')
                  ->orWhere('file_path', 'LIKE', '%uploads/%');
        })
        ->whereNotNull('file_path')
        ->where('file_path', '!=', '')
        ->limit(10)
        ->get();

    echo "📊 Attachment Results: " . $attachmentResults->count() . "\n";
    foreach ($attachmentResults as $result) {
        echo "  - ID: {$result->id}\n";
        echo "    File: {$result->stored_file_name}\n";
        echo "    Path: {$result->file_path}\n";
        echo "    Person: {$result->person_identity_number}\n";
        echo "    ----------------------\n";
    }

    // Test person name lookup
    echo "\n🔍 Testing person name lookup for folder: {$query}\n";
    $personData = DB::table('data')
        ->where('file_id_number', $query)
        ->orWhere('file_id_number', intval($query))
        ->select('data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name', 'file_id_number')
        ->first();

    if ($personData) {
        $fullName = trim(($personData->data_first_name ?? '') . ' ' .
                        ($personData->data_father_name ?? '') . ' ' .
                        ($personData->data_grand_father_name ?? '') . ' ' .
                        ($personData->data_family_name ?? ''));
        echo "✅ Person found: {$fullName} (ID: {$personData->file_id_number})\n";
    } else {
        echo "❌ No person found for folder: {$query}\n";
    }

    // Test enhanced attachments
    if (DB::getSchemaBuilder()->hasTable('enhanced_attachments')) {
        $enhancedResults = DB::table('enhanced_attachments')
            ->where(function($q) use ($query) {
                $q->where('record_number', 'LIKE', "%{$query}%")
                  ->orWhere('record_number', intval($query))
                  ->orWhere('original_file_name', 'LIKE', "%{$query}%");
            })
            ->whereIn('file_type', ['image', 'photo', 'document', 'pdf'])
            ->whereNull('deleted_at')
            ->limit(5)
            ->get();

        echo "\n📂 Enhanced Attachment Results: " . $enhancedResults->count() . "\n";
        foreach ($enhancedResults as $result) {
            echo "  - ID: {$result->id}\n";
            echo "    File: {$result->original_file_name}\n";
            echo "    Record: {$result->record_number}\n";
            echo "    Type: {$result->file_type}\n";
            echo "    ----------------------\n";
        }
    }

    echo "\n✅ Search test completed!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
