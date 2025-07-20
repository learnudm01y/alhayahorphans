<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This migration safely adds file_size to attachments table
     */
    public function up(): void
    {
        // First, let's check what columns exist in the attachments table
        $columns = Schema::getColumnListing('attachments');

        echo "📋 Current columns in 'attachments' table: " . implode(', ', $columns) . "\n";

        // Only add file_size if it doesn't exist
        if (!Schema::hasColumn('attachments', 'file_size')) {
            Schema::table('attachments', function (Blueprint $table) use ($columns) {
                // Add file_size after the most appropriate existing column
                if (in_array('file_type', $columns)) {
                    $table->unsignedBigInteger('file_size')->nullable()->after('file_type');
                    echo "✅ Added file_size after file_type\n";
                } elseif (in_array('file_path', $columns)) {
                    $table->unsignedBigInteger('file_size')->nullable()->after('file_path');
                    echo "✅ Added file_size after file_path\n";
                } else {
                    // Fallback: add at the end
                    $table->unsignedBigInteger('file_size')->nullable();
                    echo "✅ Added file_size at the end of table\n";
                }
            });
        } else {
            echo "ℹ️  Column 'file_size' already exists in 'attachments' table. Skipping.\n";
        }

        // Check if we need to add any other missing columns that the application expects
        $this->addOtherMissingColumns($columns);
    }

    /**
     * Add other columns that might be missing
     */
    private function addOtherMissingColumns(array $existingColumns): void
    {
        Schema::table('attachments', function (Blueprint $table) use ($existingColumns) {
            // Add original_file_name if missing
            if (!in_array('original_file_name', $existingColumns)) {
                $table->string('original_file_name')->nullable()->after('stored_file_name');
                echo "✅ Added original_file_name column\n";
            }

            // Add mime_type if missing
            if (!in_array('mime_type', $existingColumns)) {
                $table->string('mime_type')->nullable()->after('file_type');
                echo "✅ Added mime_type column\n";
            }

            // Add record_number if missing (for linking to records)
            if (!in_array('record_number', $existingColumns)) {
                $table->string('record_number')->nullable()->after('person_identity_number');
                echo "✅ Added record_number column\n";
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            if (Schema::hasColumn('attachments', 'file_size')) {
                $table->dropColumn('file_size');
            }
            if (Schema::hasColumn('attachments', 'original_file_name')) {
                $table->dropColumn('original_file_name');
            }
            if (Schema::hasColumn('attachments', 'mime_type')) {
                $table->dropColumn('mime_type');
            }
            if (Schema::hasColumn('attachments', 'record_number')) {
                $table->dropColumn('record_number');
            }
        });
    }
};
