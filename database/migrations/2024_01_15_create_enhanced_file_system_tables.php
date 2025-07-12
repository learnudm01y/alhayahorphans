<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('enhanced_attachments', function (Blueprint $table) {
            $table->id();

            // Basic file information
            $table->string('record_number', 10)->index();
            $table->string('person_identity_number')->nullable()->index();
            $table->string('stored_file_name');
            $table->string('original_file_name');
            $table->text('file_path');

            // File type and categorization
            $table->enum('file_type', [
                'image', 'pdf', 'excel', 'word', 'archive',
                'identity', 'medical', 'legal', 'financial'
            ]);
            $table->string('mime_type');
            $table->string('file_extension', 10);

            // File properties
            $table->unsignedBigInteger('file_size'); // in bytes
            $table->string('file_hash', 64)->unique(); // MD5 or SHA256
            $table->timestamp('file_last_modified')->nullable();

            // Source and origin tracking
            $table->enum('source', [
                'direct_upload', 'imported_google_drive', 'imported_onedrive',
                'migrated', 'generated_system', 'scanned_document'
            ])->default('direct_upload');
            $table->string('upload_ip_address', 45)->nullable();
            $table->text('upload_user_agent')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users');

            // Processing status
            $table->enum('processing_status', [
                'pending', 'processing', 'completed', 'failed', 'skipped'
            ])->default('pending');
            $table->enum('compression_status', [
                'not_required', 'pending', 'completed', 'failed'
            ])->default('not_required');
            $table->decimal('compression_ratio', 5, 2)->nullable(); // e.g., 0.75 for 75% reduction
            $table->unsignedBigInteger('original_size')->nullable();
            $table->unsignedBigInteger('compressed_size')->nullable();

            // Security and access control
            $table->enum('access_level', [
                'public', 'internal', 'restricted', 'confidential', 'classified'
            ])->default('internal');
            $table->json('access_permissions')->nullable(); // JSON array of user IDs or roles
            $table->boolean('requires_approval')->default(false);
            $table->boolean('is_encrypted')->default(false);
            $table->string('encryption_key_id')->nullable();

            // Versioning and history
            $table->unsignedInteger('version_number')->default(1);
            $table->foreignId('parent_file_id')->nullable()->constrained('enhanced_attachments');
            $table->boolean('is_latest_version')->default(true);
            $table->text('version_notes')->nullable();

            // Cloud integration
            $table->string('google_drive_id')->nullable()->index();
            $table->text('google_drive_url')->nullable();
            $table->string('onedrive_id')->nullable()->index();
            $table->text('onedrive_url')->nullable();
            $table->timestamp('cloud_last_sync')->nullable();
            $table->enum('cloud_sync_status', [
                'not_synced', 'pending', 'synced', 'failed', 'conflict'
            ])->default('not_synced');

            // Content analysis and metadata
            $table->json('file_metadata')->nullable(); // JSON object with file-specific metadata
            $table->text('extracted_text')->nullable(); // For searchable content
            $table->json('detected_content')->nullable(); // AI/OCR detected content
            $table->json('tags')->nullable(); // User-defined and auto-generated tags
            $table->decimal('content_confidence', 3, 2)->nullable(); // AI confidence score

            // Workflow and business logic
            $table->enum('document_status', [
                'draft', 'pending_review', 'approved', 'rejected', 'archived', 'expired'
            ])->default('draft');
            $table->date('expiry_date')->nullable();
            $table->boolean('requires_renewal')->default(false);
            $table->json('renewal_schedule')->nullable();
            $table->text('business_notes')->nullable();

            // Quality and validation
            $table->enum('quality_status', [
                'not_checked', 'good', 'acceptable', 'poor', 'unreadable'
            ])->default('not_checked');
            $table->json('validation_results')->nullable(); // Validation checks results
            $table->boolean('is_complete')->default(true);
            $table->json('missing_requirements')->nullable();

            // Analytics and usage
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('last_accessed')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->json('usage_analytics')->nullable();

            // Audit trail
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes(); // Soft delete for audit trail

            // Indexes for performance
            $table->index(['record_number', 'file_type']);
            $table->index(['created_at', 'file_type']);
            $table->index(['processing_status', 'compression_status']);
            $table->index(['access_level', 'document_status']);
            $table->index(['is_latest_version', 'parent_file_id']);
            $table->fullText(['original_file_name', 'extracted_text', 'business_notes'], 'enhanced_attachments_fulltext_search');
        });

        // File processing queue table
        Schema::create('file_processing_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attachment_id')->constrained('enhanced_attachments');
            $table->enum('processing_type', [
                'compression', 'thumbnail', 'ocr', 'virus_scan',
                'cloud_sync', 'content_analysis', 'validation'
            ]);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled']);
            $table->json('processing_options')->nullable();
            $table->json('result_data')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->unsignedInteger('priority')->default(5); // 1-10, 1 being highest
            $table->timestamps();

            $table->index(['status', 'processing_type', 'priority']);
            $table->index(['attachment_id', 'processing_type']);
        });

        // File access log for audit
        Schema::create('file_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attachment_id')->constrained('enhanced_attachments');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->enum('action', [
                'view', 'download', 'edit', 'delete', 'share',
                'copy', 'move', 'rename', 'permission_change'
            ]);
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->json('additional_data')->nullable(); // Context-specific data
            $table->timestamp('created_at');

            $table->index(['attachment_id', 'action', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        // File relationships and dependencies
        Schema::create('file_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_file_id')->constrained('enhanced_attachments');
            $table->foreignId('child_file_id')->constrained('enhanced_attachments');
            $table->enum('relationship_type', [
                'version', 'supplement', 'translation', 'thumbnail',
                'compressed', 'extracted', 'merged', 'split'
            ]);
            $table->json('relationship_metadata')->nullable();
            $table->timestamps();

            $table->unique(['parent_file_id', 'child_file_id', 'relationship_type'], 'file_relationships_unique');
            $table->index(['relationship_type', 'created_at']);
        });

        // File categories and classification
        Schema::create('file_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('color', 7)->nullable(); // Hex color
            $table->json('allowed_file_types')->nullable();
            $table->json('required_fields')->nullable();
            $table->json('validation_rules')->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->unsignedInteger('max_file_size')->nullable(); // in bytes
            $table->enum('access_level', [
                'public', 'internal', 'restricted', 'confidential'
            ])->default('internal');
            $table->foreignId('parent_id')->nullable()->constrained('file_categories');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
            $table->index(['is_active', 'access_level']);
        });

        // Link files to categories
        Schema::create('file_category_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attachment_id')->constrained('enhanced_attachments');
            $table->foreignId('category_id')->constrained('file_categories');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['attachment_id', 'category_id']);
            $table->index(['category_id', 'is_primary']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('file_category_assignments');
        Schema::dropIfExists('file_categories');
        Schema::dropIfExists('file_relationships');
        Schema::dropIfExists('file_access_logs');
        Schema::dropIfExists('file_processing_queue');
        Schema::dropIfExists('enhanced_attachments');
    }
};
