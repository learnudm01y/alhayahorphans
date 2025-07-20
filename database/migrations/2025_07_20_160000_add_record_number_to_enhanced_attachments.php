<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This migration adds the missing record_number column to enhanced_attachments table
     */
    public function up(): void
    {
        // Check if record_number column exists in enhanced_attachments
        if (!Schema::hasColumn('enhanced_attachments', 'record_number')) {
            Schema::table('enhanced_attachments', function (Blueprint $table) {
                // Add record_number column after person_identity_number
                if (Schema::hasColumn('enhanced_attachments', 'person_identity_number')) {
                    $table->string('record_number')->nullable()->after('person_identity_number');
                } else {
                    // Fallback: add after folder_id
                    $table->string('record_number')->nullable()->after('folder_id');
                }

                // Add index for better performance
                $table->index('record_number');
            });

            echo "✅ Added record_number column to enhanced_attachments table\n";

            // Populate record_number from folder_id if possible
            $this->populateRecordNumbers();

        } else {
            echo "ℹ️  Column 'record_number' already exists in 'enhanced_attachments' table. Skipping.\n";
        }
    }

    /**
     * Populate record_number from existing data
     */
    private function populateRecordNumbers(): void
    {
        try {
            // Get records where record_number is null but folder_id exists
            $recordsToUpdate = DB::table('enhanced_attachments')
                ->whereNull('record_number')
                ->whereNotNull('folder_id')
                ->where('folder_id', '!=', '')
                ->count();

            if ($recordsToUpdate > 0) {
                // Update record_number from folder_id
                DB::table('enhanced_attachments')
                    ->whereNull('record_number')
                    ->whereNotNull('folder_id')
                    ->where('folder_id', '!=', '')
                    ->update(['record_number' => DB::raw('folder_id')]);

                echo "✅ Updated {$recordsToUpdate} records with record_number from folder_id\n";
            }

            // Also try to extract from file names if record_number is still null
            $this->extractRecordNumbersFromFileNames();

        } catch (\Exception $e) {
            echo "⚠️  Warning: Could not populate record_numbers: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Extract record numbers from file names (like 001447_xxx.jpg)
     */
    private function extractRecordNumbersFromFileNames(): void
    {
        try {
            $records = DB::table('enhanced_attachments')
                ->whereNull('record_number')
                ->whereNotNull('original_file_name')
                ->select('id', 'original_file_name')
                ->get();

            $updatedCount = 0;

            foreach ($records as $record) {
                // Extract 6-digit number from filename (like 001447)
                if (preg_match('/(\d{6})/', $record->original_file_name, $matches)) {
                    $recordNumber = $matches[1];

                    DB::table('enhanced_attachments')
                        ->where('id', $record->id)
                        ->update(['record_number' => $recordNumber]);

                    $updatedCount++;
                }
            }

            if ($updatedCount > 0) {
                echo "✅ Extracted record_number from {$updatedCount} file names\n";
            }

        } catch (\Exception $e) {
            echo "⚠️  Warning: Could not extract record numbers from file names: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enhanced_attachments', function (Blueprint $table) {
            if (Schema::hasColumn('enhanced_attachments', 'record_number')) {
                $table->dropIndex(['record_number']);
                $table->dropColumn('record_number');
            }
        });
    }
};
