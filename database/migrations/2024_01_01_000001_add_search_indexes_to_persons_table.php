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
            // إضافة فهارس للحقول المستخدمة في البحث
            $table->index('CI_ID_NUM', 'idx_persons_ci_id_num');
            $table->index('CI_FIRST_ARB', 'idx_persons_first_name');
            $table->index('CI_FATHER_ARB', 'idx_persons_father_name');
            $table->index('CI_GRAND_FATHER_ARB', 'idx_persons_grandfather_name');
            $table->index('CI_FAMILY_ARB', 'idx_persons_family_name');
            $table->index('MOTHER_NAME1', 'idx_persons_mother_name');
            $table->index('CI_SEX_CD', 'idx_persons_gender');
            $table->index('CITY', 'idx_persons_city');
            $table->index('CI_BIRTH_DT', 'idx_persons_birth_date');
            $table->index('CI_PERSONAL_CD', 'idx_persons_social_status');

            // فهرس مركب للبحث بالاسم الكامل
            $table->index(['CI_FIRST_ARB', 'CI_FATHER_ARB', 'CI_FAMILY_ARB'], 'idx_persons_full_name');

            // فهرس مركب للبحث بالاسم والمدينة
            $table->index(['CI_FIRST_ARB', 'CITY'], 'idx_persons_name_city');

            // فهرس مركب للبحث بالجنس والمدينة
            $table->index(['CI_SEX_CD', 'CITY'], 'idx_persons_gender_city');
        });

        // إنشاء فهرس نص كامل للبحث السريع (إذا كان MySQL يدعم ذلك)
        try {
            DB::statement('
                ALTER TABLE persons
                ADD FULLTEXT INDEX idx_persons_fulltext_search (
                    CI_ID_NUM, CI_FIRST_ARB, CI_FATHER_ARB,
                    CI_GRAND_FATHER_ARB, CI_FAMILY_ARB, MOTHER_NAME1
                )
            ');
        } catch (\Exception $e) {
            // إذا فشل إنشاء الفهرس النصي، نتجاهل الخطأ
            // لأن بعض إعدادات MySQL قد لا تدعم ذلك
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            // حذف الفهارس
            $table->dropIndex('idx_persons_ci_id_num');
            $table->dropIndex('idx_persons_first_name');
            $table->dropIndex('idx_persons_father_name');
            $table->dropIndex('idx_persons_grandfather_name');
            $table->dropIndex('idx_persons_family_name');
            $table->dropIndex('idx_persons_mother_name');
            $table->dropIndex('idx_persons_gender');
            $table->dropIndex('idx_persons_city');
            $table->dropIndex('idx_persons_birth_date');
            $table->dropIndex('idx_persons_social_status');
            $table->dropIndex('idx_persons_full_name');
            $table->dropIndex('idx_persons_name_city');
            $table->dropIndex('idx_persons_gender_city');
        });

        // حذف فهرس النص الكامل
        try {
            DB::statement('ALTER TABLE persons DROP INDEX idx_persons_fulltext_search');
        } catch (\Exception $e) {
            // تجاهل الخطأ إذا لم يكن الفهرس موجوداً
        }
    }
};
