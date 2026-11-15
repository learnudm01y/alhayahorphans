<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('attachment_trash')) {
            Schema::create('attachment_trash', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('attachment_id')->index();
                $table->json('snapshot');
                $table->string('original_path');
                $table->string('trashed_path')->nullable();
                $table->unsignedBigInteger('scan_id')->nullable()->index();
                $table->unsignedBigInteger('result_id')->nullable()->index();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->string('reason', 64)->default('face-auto-duplicate');
                $table->string('status', 16)->default('trashed');
                $table->timestamp('trashed_at')->nullable();
                $table->timestamp('restore_deadline_at')->nullable();
                $table->timestamp('restored_at')->nullable();
                $table->timestamp('purged_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('attachment_face_scans') && !Schema::hasColumn('attachment_face_scans', 'trashed_count')) {
            Schema::table('attachment_face_scans', function (Blueprint $table) {
                $table->unsignedInteger('trashed_count')->default(0)->after('review_count');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attachment_face_scans') && Schema::hasColumn('attachment_face_scans', 'trashed_count')) {
            Schema::table('attachment_face_scans', function (Blueprint $table) {
                $table->dropColumn('trashed_count');
            });
        }

        Schema::dropIfExists('attachment_trash');
    }
};
