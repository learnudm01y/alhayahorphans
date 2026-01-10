<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration: Create sync_progress table
 *
 * Purpose: Track detailed progress of all sync operations with powerful monitoring
 * Features:
 * - Real-time progress tracking
 * - File upload/download monitoring with byte-level progress
 * - Conflict detection and error logging
 * - Retry mechanism tracking
 * - Session-based grouping
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sync_progress', function (Blueprint $table) {
            $table->id();

            // Session tracking
            $table->string('sync_session_id', 100)->index();

            // Operation details
            $table->enum('operation_type', [
                'data_upload',
                'data_download',
                'media_upload',
                'media_download'
            ])->index();

            // Entity information
            $table->string('entity_type', 50); // 'sponsorship', 'data', 're_people', 'attachment', etc.
            $table->string('entity_id', 100)->nullable();
            $table->string('entity_name')->nullable(); // للعرض في واجهة المستخدم

            // Status tracking
            $table->enum('status', [
                'pending',
                'in_progress',
                'success',
                'failed',
                'conflict'
            ])->default('pending')->index();

            // Progress metrics
            $table->decimal('progress_percentage', 5, 2)->default(0); // 0.00 to 100.00
            $table->bigInteger('file_size_bytes')->nullable(); // للملفات
            $table->bigInteger('uploaded_bytes')->default(0); // للملفات

            // Error and conflict tracking
            $table->text('error_message')->nullable();
            $table->text('conflict_reason')->nullable();
            $table->integer('retry_count')->default(0);

            // Timestamps
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['operation_type', 'entity_type']);
            $table->index(['sync_session_id', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sync_progress');
    }
};
