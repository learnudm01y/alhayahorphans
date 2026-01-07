<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * إضافة حقلين جديدين للتحكم في عرض قسم أفراد الأسرة وقسم المرفقات
     */
    public function up(): void
    {
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            // حقل التحكم في قسم أفراد الأسرة
            if (!Schema::hasColumn('sponsor_field_settings', 'field_family_members_section')) {
                $table->boolean('field_family_members_section')->default(1)->comment('عرض قسم أفراد الأسرة');
            }

            // حقل التحكم في قسم المرفقات والوثائق
            if (!Schema::hasColumn('sponsor_field_settings', 'field_attachments_section')) {
                $table->boolean('field_attachments_section')->default(1)->comment('عرض قسم المرفقات والوثائق');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            if (Schema::hasColumn('sponsor_field_settings', 'field_family_members_section')) {
                $table->dropColumn('field_family_members_section');
            }
            if (Schema::hasColumn('sponsor_field_settings', 'field_attachments_section')) {
                $table->dropColumn('field_attachments_section');
            }
        });
    }
};
