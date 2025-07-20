<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This migration fixes the conflict between multiple enhanced_attachments table creations
     */
    public function up(): void
    {
        // Check if the table already exists
        if (Schema::hasTable('enhanced_attachments')) {
            echo "ℹ️  Table 'enhanced_attachments' already exists. Skipping creation.\n";

            // Add any missing columns that should have been in the original migration
            $this->addMissingColumns();

            return;
        }

        // If table doesn't exist, create it with the full structure
        Schema::create('enhanced_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->string('original_file_name');
            $table->string('stored_file_name');
            $table->text('file_path');
            $table->string('folder_id')->index(); // رقم المجلد الهدف
            $table->string('original_folder_name')->nullable(); // اسم المجلد الأصلي
            $table->bigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('file_type')->nullable(); // image, document, excel, etc.
            $table->string('file_extension')->nullable();
            $table->string('file_hash')->nullable(); // MD5 hash for duplicate detection
            $table->string('upload_session_id')->nullable(); // معرف جلسة الرفع
            $table->timestamp('processed_at')->nullable();
            $table->json('metadata')->nullable(); // معلومات إضافية
            $table->string('thumbnail_path')->nullable(); // مسار الصورة المصغرة
            $table->boolean('is_processed')->default(false);
            $table->string('processing_status')->default('pending'); // pending, processing, completed, failed
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['folder_id', 'file_type']);
            $table->index(['file_hash']);
            $table->index(['upload_session_id']);
            $table->index(['is_processed', 'processing_status']);

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Add any missing columns that should have been in the original migration
     */
    private function addMissingColumns(): void
    {
        Schema::table('enhanced_attachments', function (Blueprint $table) {
            // Check and add missing columns
            if (!Schema::hasColumn('enhanced_attachments', 'file_name')) {
                $table->string('file_name')->after('id');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'folder_id')) {
                $table->string('folder_id')->after('file_path')->index();
            }
            if (!Schema::hasColumn('enhanced_attachments', 'original_folder_name')) {
                $table->string('original_folder_name')->nullable()->after('folder_id');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'upload_session_id')) {
                $table->string('upload_session_id')->nullable()->after('file_hash');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('upload_session_id');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'thumbnail_path')) {
                // Only add after metadata if metadata exists, otherwise after upload_date
                if (Schema::hasColumn('enhanced_attachments', 'metadata')) {
                    $table->string('thumbnail_path')->nullable()->after('metadata');
                } else if (Schema::hasColumn('enhanced_attachments', 'upload_date')) {
                    $table->string('thumbnail_path')->nullable()->after('upload_date');
                } else {
                    $table->string('thumbnail_path')->nullable();
                }
            }
            if (!Schema::hasColumn('enhanced_attachments', 'is_processed')) {
                $table->boolean('is_processed')->default(false)->after('thumbnail_path');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('processing_status');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        // Add missing indexes if they don't exist
        $this->addMissingIndexes();
    }

    /**
     * Add missing indexes
     */
    private function addMissingIndexes(): void
    {
        try {
            // Get existing indexes
            $indexes = DB::select("SHOW INDEX FROM enhanced_attachments");
            $existingIndexes = collect($indexes)->pluck('Key_name')->toArray();

            if (!in_array('enhanced_attachments_folder_id_file_type_index', $existingIndexes)) {
                DB::statement('ALTER TABLE enhanced_attachments ADD INDEX enhanced_attachments_folder_id_file_type_index (folder_id, file_type)');
            }

            if (!in_array('enhanced_attachments_file_hash_index', $existingIndexes)) {
                DB::statement('ALTER TABLE enhanced_attachments ADD INDEX enhanced_attachments_file_hash_index (file_hash)');
            }

            if (!in_array('enhanced_attachments_upload_session_id_index', $existingIndexes)) {
                DB::statement('ALTER TABLE enhanced_attachments ADD INDEX enhanced_attachments_upload_session_id_index (upload_session_id)');
            }

            if (!in_array('enhanced_attachments_is_processed_processing_status_index', $existingIndexes)) {
                DB::statement('ALTER TABLE enhanced_attachments ADD INDEX enhanced_attachments_is_processed_processing_status_index (is_processed, processing_status)');
            }

            // Add foreign key if it doesn't exist
            if (!in_array('enhanced_attachments_user_id_foreign', $existingIndexes)) {
                DB::statement('ALTER TABLE enhanced_attachments ADD CONSTRAINT enhanced_attachments_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
            }
        } catch (\Exception $e) {
            echo "⚠️  Warning: Could not add some indexes: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enhanced_attachments');
    }
};
