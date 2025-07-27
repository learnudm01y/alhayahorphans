<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        try {
            // إنشاء فهارس للبحث السريع في جدول persons
            DB::connection('civilregistry')->statement('CREATE INDEX idx_ci_id_num ON persons(CI_ID_NUM)');
        } catch (\Exception $e) {
            // الفهرس موجود بالفعل
        }

        try {
            DB::connection('civilregistry')->statement('CREATE INDEX idx_ci_first_arb ON persons(CI_FIRST_ARB)');
        } catch (\Exception $e) {}

        try {
            DB::connection('civilregistry')->statement('CREATE INDEX idx_ci_father_arb ON persons(CI_FATHER_ARB)');
        } catch (\Exception $e) {}

        try {
            DB::connection('civilregistry')->statement('CREATE INDEX idx_ci_family_arb ON persons(CI_FAMILY_ARB)');
        } catch (\Exception $e) {}

        try {
            // فهرس مركب للبحث بالاسم الكامل
            DB::connection('civilregistry')->statement('CREATE INDEX idx_full_name ON persons(CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB)');
        } catch (\Exception $e) {}

        try {
            // فهرس للجنس وتاريخ الميلاد
            DB::connection('civilregistry')->statement('CREATE INDEX idx_sex_birth ON persons(CI_SEX_CD, CI_BIRTH_DT)');
        } catch (\Exception $e) {}

        try {
            // فهرس للوفاة
            DB::connection('civilregistry')->statement('CREATE INDEX idx_dead_dt ON persons(CI_DEAD_DT)');
        } catch (\Exception $e) {}
    }

    public function down()
    {
        DB::connection('civilregistry')->statement('DROP INDEX IF EXISTS idx_ci_id_num');
        DB::connection('civilregistry')->statement('DROP INDEX IF EXISTS idx_ci_first_arb');
        DB::connection('civilregistry')->statement('DROP INDEX IF EXISTS idx_ci_father_arb');
        DB::connection('civilregistry')->statement('DROP INDEX IF EXISTS idx_ci_family_arb');
        DB::connection('civilregistry')->statement('DROP INDEX IF EXISTS idx_full_name');
        DB::connection('civilregistry')->statement('DROP INDEX IF EXISTS idx_sex_birth');
        DB::connection('civilregistry')->statement('DROP INDEX IF EXISTS idx_dead_dt');
    }
};
