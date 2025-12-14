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
            // إضافة حقول أفراد الأسرة
            if (!Schema::hasColumn('sponsor_field_settings', 'field_siblings_names')) {
                $table->boolean('field_siblings_names')->default(0)->comment('اسماء اخوة المكفول');
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_sibling_birthdate')) {
                $table->boolean('field_sibling_birthdate')->default(0)->comment('تاريخ الميلاد (للأخ/الأخت)');
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_sibling_grade')) {
                $table->boolean('field_sibling_grade')->default(0)->comment('الصف (للأخ/الأخت)');
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_sibling_notes')) {
                $table->boolean('field_sibling_notes')->default(0)->comment('ملاحظات (الحالة الصحية والإجتماعية)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            if (Schema::hasColumn('sponsor_field_settings', 'field_siblings_names')) {
                $table->dropColumn('field_siblings_names');
            }
            if (Schema::hasColumn('sponsor_field_settings', 'field_sibling_birthdate')) {
                $table->dropColumn('field_sibling_birthdate');
            }
            if (Schema::hasColumn('sponsor_field_settings', 'field_sibling_grade')) {
                $table->dropColumn('field_sibling_grade');
            }
            if (Schema::hasColumn('sponsor_field_settings', 'field_sibling_notes')) {
                $table->dropColumn('field_sibling_notes');
            }
        });
    }
};
