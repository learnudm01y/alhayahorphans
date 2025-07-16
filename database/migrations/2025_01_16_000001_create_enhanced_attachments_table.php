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
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enhanced_attachments');
    }
};
