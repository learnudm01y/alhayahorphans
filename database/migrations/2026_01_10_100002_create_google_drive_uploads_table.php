<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create google_drive_uploads table
 *
 * Purpose: Track all file uploads to Google Drive to prevent duplicates
 *
 * This table serves as a bridge between the mobile app and Laravel:
 * - Mobile app uploads files directly to Google Drive via Rclone
 * - After successful upload, mobile notifies Laravel
 * - Laravel creates attachment record and links it here
 * - Hash-based duplicate detection prevents re-uploading same files
 *
 * IMPORTANT: Attachments table is NOT synced - this is the link table
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
        Schema::create('google_drive_uploads', function (Blueprint $table) {
            $table->id();

            // ========== Local File Information ==========
            $table->string('local_file_path', 500)->comment('المسار المحلي على جهاز المستخدم');
            $table->string('local_file_hash', 64)->index()->comment('SHA256 hash للتحقق من التكرار');
            $table->string('file_name')->comment('اسم الملف الأصلي');
            $table->bigInteger('file_size_bytes')->unsigned()->comment('حجم الملف بالبايت');
            $table->string('mime_type', 100)->comment('نوع الملف');

            // ========== Google Drive Information ==========
            $table->string('google_drive_file_id', 100)->nullable()->index()->comment('معرف الملف على Google Drive');
            $table->string('google_drive_path', 500)->nullable()->comment('المسار الكامل على Google Drive');

            // ========== Upload Status ==========
            $table->enum('upload_status', [
                'pending',      // في انتظار الرفع
                'uploading',    // جاري الرفع
                'completed',    // تم الرفع بنجاح
                'failed',       // فشل الرفع
                'cancelled'     // تم الإلغاء
            ])->default('pending')->index()->comment('حالة عملية الرفع');
            $table->unsignedTinyInteger('upload_progress')->default(0)->comment('نسبة اكتمال الرفع 0-100');

            // ========== Entity Linking ==========
            $table->enum('entity_type', [
                'sponsorship',
                'orphan',
                'guardian',
                'deceased',
                'bank_account'
            ])->index()->comment('نوع الكيان المرتبط');
            $table->string('entity_id', 50)->index()->comment('معرف الكيان (file_id_number, registration_id, etc.)');

            // ========== Attachment Categorization ==========
            $table->string('attachment_type', 100)->comment('نوع المرفق (personal_photo, birth_certificate, etc.)');
            $table->string('attachment_description', 500)->nullable()->comment('وصف إضافي للمرفق');

            // ========== Device & User Tracking ==========
            $table->string('device_id', 100)->index()->comment('معرف الجهاز');
            $table->unsignedBigInteger('uploaded_by')->index()->comment('المستخدم الذي رفع الملف');

            // ========== Error Handling ==========
            $table->unsignedTinyInteger('retry_count')->default(0)->comment('عدد محاولات إعادة الرفع');
            $table->text('error_message')->nullable()->comment('رسالة الخطأ في حال الفشل');

            // ========== Sync Tracking ==========
            $table->boolean('synced_to_server')->default(false)->index()->comment('هل تم إخطار Laravel؟');
            $table->unsignedBigInteger('server_attachment_id')->nullable()->comment('معرف السجل في جدول attachments');

            // ========== Timestamps ==========
            $table->timestamps();
            $table->timestamp('uploaded_at')->nullable()->comment('وقت اكتمال الرفع');

            // ========== Composite Indexes ==========
            $table->index(['entity_type', 'entity_id'], 'idx_entity');
            $table->index(['upload_status', 'synced_to_server'], 'idx_status_sync');
            $table->index(['uploaded_by', 'created_at'], 'idx_user_time');

            // ========== Foreign Keys ==========
            $table->foreign('uploaded_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            $table->foreign('server_attachment_id')
                  ->references('id')
                  ->on('attachments')
                  ->onDelete('set null');
        });

        // Add unique constraint on hash to prevent duplicates
        Schema::table('google_drive_uploads', function (Blueprint $table) {
            $table->unique(['local_file_hash', 'entity_type', 'entity_id'], 'unique_file_per_entity');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('google_drive_uploads');
    }
};
