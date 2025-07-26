<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - فهارس سريعة للاستضافة المشتركة
     */
    public function up()
    {
        // تعطيل فحص الـ foreign keys مؤقتاً لتسريع العملية
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            // 1. فهرس الاسم الأول فقط (سريع)
            if (!$this->indexExists('persons', 'idx_first_name_fast')) {
                DB::statement('CREATE INDEX idx_first_name_fast ON persons (CI_FIRST_ARB(10))');
                echo "✅ تم إنشاء فهرس الاسم الأول\n";
            }

            // 2. فهرس رقم الهوية (سريع جداً)
            if (!$this->indexExists('persons', 'idx_id_num_fast')) {
                DB::statement('CREATE INDEX idx_id_num_fast ON persons (CI_ID_NUM)');
                echo "✅ تم إنشاء فهرس رقم الهوية\n";
            }

            // 3. فهرس اسم الأب (سريع)
            if (!$this->indexExists('persons', 'idx_father_name_fast')) {
                DB::statement('CREATE INDEX idx_father_name_fast ON persons (CI_FATHER_ARB(10))');
                echo "✅ تم إنشاء فهرس اسم الأب\n";
            }

        } catch (Exception $e) {
            echo "⚠️ خطأ في إنشاء الفهارس: " . $e->getMessage() . "\n";
        }

        // إعادة تفعيل فحص الـ foreign keys
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::statement('DROP INDEX IF EXISTS idx_first_name_fast ON persons');
        DB::statement('DROP INDEX IF EXISTS idx_id_num_fast ON persons');
        DB::statement('DROP INDEX IF EXISTS idx_father_name_fast ON persons');
    }

    /**
     * فحص وجود الفهرس
     */
    private function indexExists($table, $indexName)
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
};
