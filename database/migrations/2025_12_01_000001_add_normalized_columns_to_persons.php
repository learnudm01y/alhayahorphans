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
        // التحقق من وجود الأعمدة قبل إضافتها
        $connection = DB::connection('civilregistry');

        try {
            // إضافة أعمدة مطبّعة في جدول persons (السجل المدني)
            $connection->statement("
                ALTER TABLE persons
                ADD COLUMN CI_FIRST_ARB_NORMALIZED VARCHAR(255) GENERATED ALWAYS AS (
                    REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                        CI_FIRST_ARB,
                        'أ', 'ا'),
                        'إ', 'ا'),
                        'آ', 'ا'),
                        'ى', 'ي'),
                        'ة', 'ه')
                ) STORED,
                ADD COLUMN CI_FATHER_ARB_NORMALIZED VARCHAR(255) GENERATED ALWAYS AS (
                    REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                        CI_FATHER_ARB,
                        'أ', 'ا'),
                        'إ', 'ا'),
                        'آ', 'ا'),
                        'ى', 'ي'),
                        'ة', 'ه')
                ) STORED,
                ADD COLUMN CI_GRAND_FATHER_ARB_NORMALIZED VARCHAR(255) GENERATED ALWAYS AS (
                    REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                        CI_GRAND_FATHER_ARB,
                        'أ', 'ا'),
                        'إ', 'ا'),
                        'آ', 'ا'),
                        'ى', 'ي'),
                        'ة', 'ه')
                ) STORED,
                ADD COLUMN CI_FAMILY_ARB_NORMALIZED VARCHAR(255) GENERATED ALWAYS AS (
                    REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                        CI_FAMILY_ARB,
                        'أ', 'ا'),
                        'إ', 'ا'),
                        'آ', 'ا'),
                        'ى', 'ي'),
                        'ة', 'ه')
                ) STORED
            ");
        } catch (\Exception $e) {
            // الأعمدة موجودة بالفعل
            \Log::info('Normalized columns may already exist: ' . $e->getMessage());
        }

        // إضافة indexes على الأعمدة المطبّعة
        try {
            $connection->statement("CREATE INDEX idx_ci_first_normalized ON persons (CI_FIRST_ARB_NORMALIZED)");
        } catch (\Exception $e) {}

        try {
            $connection->statement("CREATE INDEX idx_ci_father_normalized ON persons (CI_FATHER_ARB_NORMALIZED)");
        } catch (\Exception $e) {}

        try {
            $connection->statement("CREATE INDEX idx_ci_grand_father_normalized ON persons (CI_GRAND_FATHER_ARB_NORMALIZED)");
        } catch (\Exception $e) {}

        try {
            $connection->statement("CREATE INDEX idx_ci_family_normalized ON persons (CI_FAMILY_ARB_NORMALIZED)");
        } catch (\Exception $e) {}

        // Composite indexes للبحث السريع
        try {
            $connection->statement("CREATE INDEX idx_normalized_first_family ON persons (CI_FIRST_ARB_NORMALIZED, CI_FAMILY_ARB_NORMALIZED)");
        } catch (\Exception $e) {}

        try {
            $connection->statement("CREATE INDEX idx_normalized_first_father ON persons (CI_FIRST_ARB_NORMALIZED, CI_FATHER_ARB_NORMALIZED)");
        } catch (\Exception $e) {}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection('civilregistry')->statement("
            ALTER TABLE persons
            DROP COLUMN IF EXISTS CI_FIRST_ARB_NORMALIZED,
            DROP COLUMN IF EXISTS CI_FATHER_ARB_NORMALIZED,
            DROP COLUMN IF EXISTS CI_GRAND_FATHER_ARB_NORMALIZED,
            DROP COLUMN IF EXISTS CI_FAMILY_ARB_NORMALIZED
        ");
    }
};
