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
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('running')->index(); // running|success|partial|failed
            $table->string('current_step')->nullable();            // المرحلة الحالية أثناء التنفيذ
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            // نسخة قاعدة البيانات
            $table->unsignedBigInteger('database_size')->nullable(); // bytes
            $table->string('database_status')->nullable();           // success|failed|skipped

            // نسخة ملفات Laravel (ZIP)
            $table->unsignedBigInteger('laravel_size')->nullable();  // bytes
            $table->string('laravel_status')->nullable();            // success|failed|skipped

            // أرشفة uploads
            $table->unsignedBigInteger('uploads_files')->nullable();    // إجمالي الملفات في المصدر
            $table->unsignedBigInteger('uploads_size')->nullable();     // bytes
            $table->unsignedBigInteger('uploads_copied')->nullable();
            $table->unsignedBigInteger('uploads_skipped')->nullable();
            $table->unsignedBigInteger('uploads_failed')->nullable();
            $table->string('uploads_status')->nullable();               // success|failed|skipped|disabled

            // أرشفة attachments
            $table->unsignedBigInteger('attachments_files')->nullable();
            $table->unsignedBigInteger('attachments_size')->nullable();
            $table->unsignedBigInteger('attachments_copied')->nullable();
            $table->unsignedBigInteger('attachments_skipped')->nullable();
            $table->unsignedBigInteger('attachments_failed')->nullable();
            $table->string('attachments_status')->nullable();

            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
