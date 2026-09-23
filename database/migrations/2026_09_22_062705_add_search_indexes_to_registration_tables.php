<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * إضافة فهارس (Indexes) لتحسين سرعة البحث برقم الهوية في تطبيق الهاتف والموقع
     */
    public function up(): void
    {
        // 1) جدول المعيلين (data)
        if (Schema::hasTable('data') && !$this->IndexExists('data', 'idx_data_id_number')) {
            Schema::table('data', function (Blueprint $table) {
                $table->index('data_id_number', 'idx_data_id_number');
            });
        }

        // 2) جدول أفراد الأسرة (re_people)
        if (Schema::hasTable('re_people') && !$this->IndexExists('re_people', 'idx_person_id')) {
            Schema::table('re_people', function (Blueprint $table) {
                $table->index('person_id', 'idx_person_id');
            });
        }

        // 3) جدول المتوفين (dead_people)
        if (Schema::hasTable('dead_people')) {
            Schema::table('dead_people', function (Blueprint $table) {
                if (!$this->IndexExists('dead_people', 'idx_father_id')) {
                    $table->index('father_id', 'idx_father_id');
                }
                if (!$this->IndexExists('dead_people', 'idx_mother_id')) {
                    $table->index('mother_id', 'idx_mother_id');
                }
            });
        }

        // 4) جدول الكفالات (sponsorships)
        if (Schema::hasTable('sponsorships') && !$this->IndexExists('sponsorships', 'idx_guardian_identity_number')) {
            Schema::table('sponsorships', function (Blueprint $table) {
                $table->index('guardian_identity_number', 'idx_guardian_identity_number');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data', function (Blueprint $table) { $table->dropIndex('idx_data_id_number'); });
        Schema::table('re_people', function (Blueprint $table) { $table->dropIndex('idx_person_id'); });
        Schema::table('dead_people', function (Blueprint $table) {
            $table->dropIndex('idx_father_id');
            $table->dropIndex('idx_mother_id');
        });
        Schema::table('sponsorships', function (Blueprint $table) { $table->dropIndex('idx_guardian_identity_number'); });
    }

    private function IndexExists(string $table, string $index): bool
    {
        $conn = Schema::getConnection();
        $results = $conn->select("SHOW INDEX FROM {$table} WHERE Key_name = '{$index}'");
        return count($results) > 0;
    }
};
