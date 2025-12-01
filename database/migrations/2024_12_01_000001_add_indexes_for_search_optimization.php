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
        // فهارس لجدول data (المعيلين)
        Schema::connection('mysql')->table('data', function (Blueprint $table) {
            // فهرس على رقم الهوية (أهم حقل للبحث)
            if (!$this->indexExists('data', 'idx_data_id_number')) {
                $table->index('data_id_number', 'idx_data_id_number');
            }

            // فهرس على رقم الملف
            if (!$this->indexExists('data', 'idx_file_id_number')) {
                $table->index('file_id_number', 'idx_file_id_number');
            }

            // فهرس مركب على الأسماء للبحث السريع
            if (!$this->indexExists('data', 'idx_data_names')) {
                $table->index(['data_first_name', 'data_father_name'], 'idx_data_names');
            }
        });

        // فهارس لجدول re_people (الأيتام)
        Schema::connection('mysql')->table('re_people', function (Blueprint $table) {
            if (!$this->indexExists('re_people', 'idx_person_id')) {
                $table->index('person_id', 'idx_person_id');
            }

            if (!$this->indexExists('re_people', 'idx_names')) {
                $table->index(['first_name', 'second_name'], 'idx_names');
            }
        });

        // فهارس لجدول dead_people (المتوفين)
        Schema::connection('mysql')->table('dead_people', function (Blueprint $table) {
            if (!$this->indexExists('dead_people', 'idx_father_id')) {
                $table->index('father_id', 'idx_father_id');
            }

            if (!$this->indexExists('dead_people', 'idx_mother_id')) {
                $table->index('mother_id', 'idx_mother_id');
            }
        });

        // فهارس لجدول persons (السجل المدني) - الأهم
        Schema::connection('civilregistry')->table('persons', function (Blueprint $table) {
            // فهرس على رقم الهوية (الأهم للبحث)
            if (!$this->indexExists('persons', 'idx_ci_id_num', 'civilregistry')) {
                $table->index('CI_ID_NUM', 'idx_ci_id_num');
            }

            // فهرس مركب على الأسماء العربية
            if (!$this->indexExists('persons', 'idx_arabic_names', 'civilregistry')) {
                $table->index(['CI_FIRST_ARB', 'CI_FATHER_ARB'], 'idx_arabic_names');
            }

            // فهرس على الجنس (للفلترة)
            if (!$this->indexExists('persons', 'idx_sex', 'civilregistry')) {
                $table->index('CI_SEX_CD', 'idx_sex');
            }

            // فهرس على تاريخ الميلاد
            if (!$this->indexExists('persons', 'idx_birth_date', 'civilregistry')) {
                $table->index('CI_BIRTH_DT', 'idx_birth_date');
            }
        });

        // إنشاء FULLTEXT INDEX للبحث الأسرع في السجل المدني
        try {
            DB::connection('civilregistry')->statement('
                ALTER TABLE persons
                ADD FULLTEXT INDEX ft_names (CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB)
            ');
            echo "✅ تم إضافة FULLTEXT INDEX بنجاح!\n";
        } catch (\Exception $e) {
            echo "⚠️ تحذير: لم يتم إضافة FULLTEXT INDEX (قد تكون المساحة غير كافية): " . $e->getMessage() . "\n";
            echo "✅ الفهارس العادية تمت إضافتها بنجاح!\n";
        }

        echo "✅ تم إضافة جميع الفهارس الأساسية بنجاح!\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // حذف الفهارس من جدول data
        Schema::connection('mysql')->table('data', function (Blueprint $table) {
            $table->dropIndex('idx_data_id_number');
            $table->dropIndex('idx_file_id_number');
            $table->dropIndex('idx_data_names');
        });

        // حذف الفهارس من جدول re_people
        Schema::connection('mysql')->table('re_people', function (Blueprint $table) {
            $table->dropIndex('idx_person_id');
            $table->dropIndex('idx_names');
        });

        // حذف الفهارس من جدول dead_people
        Schema::connection('mysql')->table('dead_people', function (Blueprint $table) {
            $table->dropIndex('idx_father_id');
            $table->dropIndex('idx_mother_id');
        });

        // حذف الفهارس من جدول persons
        Schema::connection('civilregistry')->table('persons', function (Blueprint $table) {
            $table->dropIndex('idx_ci_id_num');
            $table->dropIndex('idx_arabic_names');
            $table->dropIndex('idx_sex');
            $table->dropIndex('idx_birth_date');
        });

        // حذف FULLTEXT INDEX
        try {
            DB::connection('civilregistry')->statement('
                ALTER TABLE persons DROP INDEX ft_names
            ');
        } catch (\Exception $e) {
            // Index may not exist
        }
    }

    /**
     * التحقق من وجود الفهرس
     */
    private function indexExists(string $table, string $indexName, string $connection = 'mysql'): bool
    {
        $indexes = DB::connection($connection)
            ->select("SHOW INDEX FROM {$table} WHERE Key_name = '{$indexName}'");

        return count($indexes) > 0;
    }
};
