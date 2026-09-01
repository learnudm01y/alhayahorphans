<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * إضافة حقل ضغط وتصغير حجم الصور لكل جمعية بشكل مستقل
     */
    public function up(): void
    {
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('sponsor_field_settings', 'compress_attachments_images')) {
                $table->boolean('compress_attachments_images')->default(1)->comment('ضغط وتصغير حجم الصور قبل الرفع');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            if (Schema::hasColumn('sponsor_field_settings', 'compress_attachments_images')) {
                $table->dropColumn('compress_attachments_images');
            }
        });
    }
};
