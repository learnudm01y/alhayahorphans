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
        Schema::table('backup_runs', function (Blueprint $table) {
            // رفع ملفات النسخ الاحتياطي (.backups) إلى OneDrive
            $table->unsignedBigInteger('backups_files')->nullable()->after('attachments_status');
            $table->unsignedBigInteger('backups_size')->nullable()->after('backups_files');
            $table->unsignedBigInteger('backups_copied')->nullable()->after('backups_size');
            $table->unsignedBigInteger('backups_skipped')->nullable()->after('backups_copied');
            $table->unsignedBigInteger('backups_failed')->nullable()->after('backups_skipped');
            $table->string('backups_status')->nullable()->after('backups_failed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backup_runs', function (Blueprint $table) {
            $table->dropColumn([
                'backups_files',
                'backups_size',
                'backups_copied',
                'backups_skipped',
                'backups_failed',
                'backups_status',
            ]);
        });
    }
};
