<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('attachment_face_results')) {
            return;
        }

        Schema::table('attachment_face_results', function (Blueprint $table) {
            if (!Schema::hasColumn('attachment_face_results', 'review_status')) {
                $table->string('review_status', 20)->nullable()->after('distance');
            }

            if (!Schema::hasColumn('attachment_face_results', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('review_status');
            }

            if (!Schema::hasColumn('attachment_face_results', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('attachment_face_results')) {
            return;
        }

        foreach (['reviewed_at', 'reviewed_by', 'review_status'] as $column) {
            if (Schema::hasColumn('attachment_face_results', $column)) {
                Schema::table('attachment_face_results', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
