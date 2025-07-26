<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            // إنشاء فهارس محسنة للبحث السريع مع تحديد أطوال مناسبة

            // فهارس منفردة للأسماء
            $table->index('CI_FIRST_ARB', 'idx_first_name');
            $table->index('CI_FATHER_ARB', 'idx_father_name');
            $table->index('CI_FAMILY_ARB', 'idx_family_name');

            // فهرس لرقم الهوية للبحث السريع
            $table->index('CI_ID_NUM', 'idx_ci_id_num');

            // فهرس لتاريخ الميلاد
            $table->index('CI_BIRTH_DT', 'idx_birth_date');

            // فهرس للجنس
            $table->index('CI_SEX_CD', 'idx_gender');

            // فهرس للمدينة
            $table->index('CITY', 'idx_city');

            // فهرس للحالة الاجتماعية
            $table->index('CI_PERSONAL_CD', 'idx_social_status');

            // فهرس لاسم الأم
            $table->index('MOTHER_NAME1', 'idx_mother_name');
        });

        // إضافة فهرس نصي كامل للبحث السريع باستخدام MySQL
        try {
            DB::statement('ALTER TABLE persons ADD FULLTEXT INDEX idx_fulltext_search (CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB, MOTHER_NAME1)');
        } catch (\Exception $e) {
            // في حالة فشل إنشاء الفهرس النصي الكامل، تجاهل الخطأ
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            // حذف الفهارس
            $table->dropIndex('idx_first_name');
            $table->dropIndex('idx_father_name');
            $table->dropIndex('idx_family_name');
            $table->dropIndex('idx_ci_id_num');
            $table->dropIndex('idx_birth_date');
            $table->dropIndex('idx_gender');
            $table->dropIndex('idx_city');
            $table->dropIndex('idx_social_status');
            $table->dropIndex('idx_mother_name');
        });

        // حذف الفهرس النصي الكامل
        try {
            DB::statement('ALTER TABLE persons DROP INDEX idx_fulltext_search');
        } catch (\Exception $e) {
            // تجاهل الخطأ إذا كان الفهرس غير موجود
        }
    }
};
