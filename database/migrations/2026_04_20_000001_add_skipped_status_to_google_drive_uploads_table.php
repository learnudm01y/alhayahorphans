<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migration: Add 'skipped' to upload_status enum in google_drive_uploads
 *
 * Fix for: حلقة لا نهائية — خطأ 1062 لا يُحدِّث حالة الملف إلى skipped
 *
 * When a duplicate-entry (1062) error occurs during file upload, the record
 * must be marked as 'skipped' so the sync job stops retrying it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE google_drive_uploads
            MODIFY COLUMN upload_status
            ENUM('pending','uploading','completed','failed','cancelled','skipped')
            NOT NULL DEFAULT 'pending'
            COMMENT 'حالة عملية الرفع'
        ");
    }

    public function down(): void
    {
        // First set any 'skipped' records back to 'failed' so the column shrink won't fail
        DB::statement("UPDATE google_drive_uploads SET upload_status = 'failed' WHERE upload_status = 'skipped'");

        DB::statement("
            ALTER TABLE google_drive_uploads
            MODIFY COLUMN upload_status
            ENUM('pending','uploading','completed','failed','cancelled')
            NOT NULL DEFAULT 'pending'
            COMMENT 'حالة عملية الرفع'
        ");
    }
};
