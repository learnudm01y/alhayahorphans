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
        // إضافة فهارس لجدول data لتحسين أداء البحث
        Schema::table('data', function (Blueprint $table) {
            // فهارس مفردة للحقول الأكثر استخداماً في البحث
            if (!$this->indexExists('data', 'idx_data_first_name')) {
                $table->index('data_first_name', 'idx_data_first_name');
            }

            if (!$this->indexExists('data', 'idx_data_id_number')) {
                $table->index('data_id_number', 'idx_data_id_number');
            }

            if (!$this->indexExists('data', 'idx_file_id_number')) {
                $table->index('file_id_number', 'idx_file_id_number');
            }

            if (!$this->indexExists('data', 'idx_data_phone_number')) {
                $table->index('data_phone_number', 'idx_data_phone_number');
            }

            if (!$this->indexExists('data', 'idx_data_birth_date')) {
                $table->index('data_birth_date', 'idx_data_birth_date');
            }

            if (!$this->indexExists('data', 'idx_data_request_status')) {
                $table->index('data_request_status', 'idx_data_request_status');
            }

            if (!$this->indexExists('data', 'idx_data_section_id')) {
                $table->index('data_section_id', 'idx_data_section_id');
            }

            if (!$this->indexExists('data', 'idx_data_city')) {
                $table->index('data_city', 'idx_data_city');
            }
        });

        // فهارس مركبة لتحسين استعلامات البحث المعقدة
        if (!$this->indexExists('data', 'idx_data_name_composite')) {
            DB::statement('CREATE INDEX idx_data_name_composite ON data (data_first_name, data_father_name)');
        }

        if (!$this->indexExists('data', 'idx_data_file_status')) {
            DB::statement('CREATE INDEX idx_data_file_status ON data (file_id_number, data_request_status)');
        }

        if (!$this->indexExists('data', 'idx_data_section_city')) {
            DB::statement('CREATE INDEX idx_data_section_city ON data (data_section_id, data_city)');
        }

        // فهارس لجدول re_people
        Schema::table('re_people', function (Blueprint $table) {
            if (!$this->indexExists('re_people', 'idx_re_people_first_name')) {
                $table->index('first_name', 'idx_re_people_first_name');
            }

            if (!$this->indexExists('re_people', 'idx_re_people_person_id')) {
                $table->index('person_id', 'idx_re_people_person_id');
            }

            if (!$this->indexExists('re_people', 'idx_re_people_registration_id')) {
                $table->index('registration_id', 'idx_re_people_registration_id');
            }

            if (!$this->indexExists('re_people', 'idx_re_people_birth_date')) {
                $table->index('person_birth_date', 'idx_re_people_birth_date');
            }

            if (!$this->indexExists('re_people', 'idx_re_people_health_status')) {
                $table->index('person_health_status', 'idx_re_people_health_status');
            }
        });

        // فهرس مركب لجدول re_people
        if (!$this->indexExists('re_people', 'idx_re_people_registration_person')) {
            DB::statement('CREATE INDEX idx_re_people_registration_person ON re_people (registration_id, person_id)');
        }

        // فهارس لجدول dead_people
        Schema::table('dead_people', function (Blueprint $table) {
            if (!$this->indexExists('dead_people', 'idx_dead_people_father_id')) {
                $table->index('father_id', 'idx_dead_people_father_id');
            }

            if (!$this->indexExists('dead_people', 'idx_dead_people_mother_id')) {
                $table->index('mother_id', 'idx_dead_people_mother_id');
            }

            if (!$this->indexExists('dead_people', 'idx_dead_people_re_file_id')) {
                $table->index('re_file_id', 'idx_dead_people_re_file_id');
            }

            if (!$this->indexExists('dead_people', 'idx_dead_people_father_death_date')) {
                $table->index('father_death_date', 'idx_dead_people_father_death_date');
            }

            if (!$this->indexExists('dead_people', 'idx_dead_people_mother_death_date')) {
                $table->index('mother_death_date', 'idx_dead_people_mother_death_date');
            }
        });

        // فهارس النص الكامل (Full-text) للبحث السريع
        if (DB::getDriverName() === 'mysql') {
            // فهرس النص الكامل للأسماء في جدول data
            if (!$this->indexExists('data', 'idx_data_fulltext_names')) {
                DB::statement('CREATE FULLTEXT INDEX idx_data_fulltext_names ON data (data_first_name, data_father_name, data_grand_father_name, data_family_name)');
            }

            // فهرس النص الكامل للأسماء في جدول re_people
            if (!$this->indexExists('re_people', 'idx_re_people_fulltext_names')) {
                DB::statement('CREATE FULLTEXT INDEX idx_re_people_fulltext_names ON re_people (first_name, second_name, third_name, last_name)');
            }

            // فهرس النص الكامل للأسماء في جدول dead_people
            if (!$this->indexExists('dead_people', 'idx_dead_people_fulltext_father')) {
                DB::statement('CREATE FULLTEXT INDEX idx_dead_people_fulltext_father ON dead_people (father_first_name, father_second_name, father_third_name, father_last_name)');
            }

            if (!$this->indexExists('dead_people', 'idx_dead_people_fulltext_mother')) {
                DB::statement('CREATE FULLTEXT INDEX idx_dead_people_fulltext_mother ON dead_people (mother_first_name, mother_second_name, mother_third_name, mother_last_name)');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // حذف الفهارس المضافة - استخدام try-catch لتجنب الأخطاء
        try {
            Schema::table('data', function (Blueprint $table) {
                $table->dropIndex('idx_data_first_name');
                $table->dropIndex('idx_data_id_number');
                $table->dropIndex('idx_file_id_number');
                $table->dropIndex('idx_data_phone_number');
                $table->dropIndex('idx_data_birth_date');
                $table->dropIndex('idx_data_request_status');
                $table->dropIndex('idx_data_section_id');
                $table->dropIndex('idx_data_city');
            });
        } catch (\Exception $e) {
            // Some indexes might not exist
        }

        try {
            Schema::table('re_people', function (Blueprint $table) {
                $table->dropIndex('idx_re_people_first_name');
                $table->dropIndex('idx_re_people_person_id');
                $table->dropIndex('idx_re_people_registration_id');
                $table->dropIndex('idx_re_people_birth_date');
                $table->dropIndex('idx_re_people_health_status');
            });
        } catch (\Exception $e) {
            // Some indexes might not exist
        }

        try {
            Schema::table('dead_people', function (Blueprint $table) {
                $table->dropIndex('idx_dead_people_father_id');
                $table->dropIndex('idx_dead_people_mother_id');
                $table->dropIndex('idx_dead_people_re_file_id');
                $table->dropIndex('idx_dead_people_father_death_date');
                $table->dropIndex('idx_dead_people_mother_death_date');
            });
        } catch (\Exception $e) {
            // Some indexes might not exist
        }

        // حذف الفهارس المركبة والنص الكامل
        try {
            DB::statement('DROP INDEX idx_data_name_composite ON data');
        } catch (\Exception $e) {
            // Index might not exist
        }

        try {
            DB::statement('DROP INDEX idx_data_file_status ON data');
        } catch (\Exception $e) {
            // Index might not exist
        }

        try {
            DB::statement('DROP INDEX idx_data_section_city ON data');
        } catch (\Exception $e) {
            // Index might not exist
        }

        try {
            DB::statement('DROP INDEX idx_re_people_registration_person ON re_people');
        } catch (\Exception $e) {
            // Index might not exist
        }

        if (DB::getDriverName() === 'mysql') {
            try {
                DB::statement('DROP INDEX idx_data_fulltext_names ON data');
            } catch (\Exception $e) {
                // Index might not exist
            }

            try {
                DB::statement('DROP INDEX idx_re_people_fulltext_names ON re_people');
            } catch (\Exception $e) {
                // Index might not exist
            }

            try {
                DB::statement('DROP INDEX idx_dead_people_fulltext_father ON dead_people');
            } catch (\Exception $e) {
                // Index might not exist
            }

            try {
                DB::statement('DROP INDEX idx_dead_people_fulltext_mother ON dead_people');
            } catch (\Exception $e) {
                // Index might not exist
            }
        }
    }

    /**
     * التحقق من وجود فهرس
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM {$table}");
            foreach ($indexes as $idx) {
                if ($idx->Key_name === $index) {
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
};
