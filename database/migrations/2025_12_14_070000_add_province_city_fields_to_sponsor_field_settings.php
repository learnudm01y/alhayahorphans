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
            // إضافة حقل المحافظة
            if (!Schema::hasColumn('sponsor_field_settings', 'field_data_province')) {
                $table->boolean('field_data_province')->default(0)->comment('محافظة المعيل');
            }

            // إضافة حقل المدينة (إذا لم يكن موجوداً)
            if (!Schema::hasColumn('sponsor_field_settings', 'field_data_city')) {
                $table->boolean('field_data_city')->default(0)->comment('مدينة المعيل');
            }

            // إضافة حقل تاريخ وفاة الأب
            if (!Schema::hasColumn('sponsor_field_settings', 'field_father_death_date')) {
                $table->boolean('field_father_death_date')->default(0)->comment('تاريخ وفاة الأب');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            if (Schema::hasColumn('sponsor_field_settings', 'field_data_province')) {
                $table->dropColumn('field_data_province');
            }
            if (Schema::hasColumn('sponsor_field_settings', 'field_data_city')) {
                $table->dropColumn('field_data_city');
            }
            if (Schema::hasColumn('sponsor_field_settings', 'field_father_death_date')) {
                $table->dropColumn('field_father_death_date');
            }
        });
    }
};
