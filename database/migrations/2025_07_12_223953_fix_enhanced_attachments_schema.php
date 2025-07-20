<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('enhanced_attachments', function (Blueprint $table) {
            // Add missing columns that the controller expects - only if they don't exist
            if (!Schema::hasColumn('enhanced_attachments', 'stored_file_name')) {
                $table->string('stored_file_name')->nullable()->after('original_file_name');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'file_path') && !Schema::hasColumn('enhanced_attachments', 'file_path')) {
                // Note: Some columns might have different names, check both
                $table->string('file_path')->nullable()->after('stored_file_name');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'file_extension')) {
                $table->string('file_extension', 10)->nullable()->after('mime_type');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'file_hash')) {
                $table->string('file_hash')->nullable()->after('file_extension');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'file_last_modified')) {
                $table->timestamp('file_last_modified')->nullable()->after('file_hash');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'source')) {
                $table->string('source', 50)->default('direct_upload')->after('file_last_modified');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'upload_ip_address')) {
                $table->string('upload_ip_address', 45)->nullable()->after('source');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'upload_user_agent')) {
                $table->text('upload_user_agent')->nullable()->after('upload_ip_address');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'uploaded_by_user_id')) {
                $table->unsignedBigInteger('uploaded_by_user_id')->nullable()->after('upload_user_agent');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'compression_status')) {
                $table->string('compression_status', 50)->default('not_required')->after('processing_status');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'requires_approval')) {
                $table->boolean('requires_approval')->default(false)->after('access_level');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'is_encrypted')) {
                $table->boolean('is_encrypted')->default(false)->after('requires_approval');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'version_number')) {
                $table->integer('version_number')->default(1)->after('is_encrypted');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'is_latest_version')) {
                $table->boolean('is_latest_version')->default(true)->after('version_number');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'cloud_sync_status')) {
                $table->string('cloud_sync_status', 50)->default('not_synced')->after('is_latest_version');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'document_status')) {
                $table->enum('document_status', ['active', 'archived', 'deleted', 'pending'])->default('active')->after('cloud_sync_status');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'quality_status')) {
                $table->string('quality_status', 50)->default('good')->after('document_status');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'is_complete')) {
                $table->boolean('is_complete')->default(true)->after('quality_status');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'download_count')) {
                $table->integer('download_count')->default(0)->after('is_complete');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'view_count')) {
                $table->integer('view_count')->default(0)->after('download_count');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('view_count');
            }
            if (!Schema::hasColumn('enhanced_attachments', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enhanced_attachments', function (Blueprint $table) {
            $table->dropColumn([
                'stored_file_name', 'file_path', 'file_extension', 'file_hash',
                'file_last_modified', 'source', 'upload_ip_address', 'upload_user_agent',
                'uploaded_by_user_id', 'compression_status', 'requires_approval',
                'is_encrypted', 'version_number', 'is_latest_version', 'cloud_sync_status',
                'document_status', 'quality_status', 'is_complete', 'download_count',
                'view_count', 'created_by', 'updated_by'
            ]);
        });
    }
};
