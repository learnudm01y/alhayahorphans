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
            // Add missing columns that the controller expects
            $table->string('stored_file_name')->nullable()->after('original_file_name');
            $table->string('file_path')->nullable()->after('stored_file_name');
            $table->string('file_extension', 10)->nullable()->after('mime_type');
            $table->string('file_hash')->nullable()->after('file_extension');
            $table->timestamp('file_last_modified')->nullable()->after('file_hash');
            $table->string('source', 50)->default('direct_upload')->after('file_last_modified');
            $table->string('upload_ip_address', 45)->nullable()->after('source');
            $table->text('upload_user_agent')->nullable()->after('upload_ip_address');
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable()->after('upload_user_agent');
            $table->string('compression_status', 50)->default('not_required')->after('processing_status');
            $table->boolean('requires_approval')->default(false)->after('access_level');
            $table->boolean('is_encrypted')->default(false)->after('requires_approval');
            $table->integer('version_number')->default(1)->after('is_encrypted');
            $table->boolean('is_latest_version')->default(true)->after('version_number');
            $table->string('cloud_sync_status', 50)->default('not_synced')->after('is_latest_version');
            $table->enum('document_status', ['active', 'archived', 'deleted', 'pending'])->default('active')->after('cloud_sync_status');
            $table->string('quality_status', 50)->default('good')->after('document_status');
            $table->boolean('is_complete')->default(true)->after('quality_status');
            $table->integer('download_count')->default(0)->after('is_complete');
            $table->integer('view_count')->default(0)->after('download_count');
            $table->unsignedBigInteger('created_by')->nullable()->after('view_count');
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
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
