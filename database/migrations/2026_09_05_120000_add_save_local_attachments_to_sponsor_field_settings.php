<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * إضافة حقل حفظ محلي للمرفقات لكل جمعية بشكل مستقل
     */
    public function up(): void
    {
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('sponsor_field_settings', 'save_local_attachments')) {
                $table->json('save_local_attachments')->nullable()->comment('قائمة بمعرفات أنواع الوثائق المفعّل فيها الحفظ المحلي');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            if (Schema::hasColumn('sponsor_field_settings', 'save_local_attachments')) {
                $table->dropColumn('save_local_attachments');
            }
        });
    }
};
