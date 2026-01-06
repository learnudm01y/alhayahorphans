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
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('sponsor_field_settings', 'field_guardian_job_text')) {
                $table->boolean('field_guardian_job_text')->default(1)->comment('الوظيفة (نص)')->after('field_guardian_job');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            if (Schema::hasColumn('sponsor_field_settings', 'field_guardian_job_text')) {
                $table->dropColumn('field_guardian_job_text');
            }
        });
    }
};
