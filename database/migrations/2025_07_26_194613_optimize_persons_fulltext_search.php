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
        // التحقق من وجود الفهرس النصي الكامل وإنشاؤه إذا لم يكن موجود
        $indexExists = DB::select("SHOW INDEX FROM persons WHERE Key_name = 'idx_fulltext_search'");

        if (empty($indexExists)) {
            try {
                DB::statement('ALTER TABLE persons ADD FULLTEXT INDEX idx_fulltext_search (CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB, MOTHER_NAME1)');
                echo "FULLTEXT index created successfully\n";
            } catch (\Exception $e) {
                echo "Failed to create FULLTEXT index: " . $e->getMessage() . "\n";
            }
        } else {
            echo "FULLTEXT index already exists\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE persons DROP INDEX idx_fulltext_search');
        } catch (\Exception $e) {
            // تجاهل الخطأ إذا كان الفهرس غير موجود
        }
    }
};
