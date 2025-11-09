<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * إضافة فهارس B-Tree لتسريع البحث في جدول relations
     */
    public function up(): void
    {
        // استخدام اتصال قاعدة بيانات civilregistry
        Schema::connection('civilregistry')->table('relations', function (Blueprint $table) {
            // إضافة فهرس B-Tree على CF_ID_NUM للبحث السريع
            $table->index('CF_ID_NUM', 'idx_cf_id_num');

            // إضافة فهرس B-Tree على CF_ID_RELATIVE للبحث السريع
            $table->index('CF_ID_RELATIVE', 'idx_cf_id_relative');

            // إضافة فهرس B-Tree على CF_RELATIVE_CD لتسريع عملية الربط
            $table->index('CF_RELATIVE_CD', 'idx_cf_relative_cd');

            // إضافة فهرس مركب للبحث الأمثل عن العلاقات
            // هذا الفهرس يسرع الاستعلامات التي تبحث عن رقم الهوية ونوع العلاقة معاً
            $table->index(['CF_ID_NUM', 'CF_RELATIVE_CD'], 'idx_cf_id_num_relative_cd');

            // إضافة فهرس مركب للعلاقة العكسية
            $table->index(['CF_ID_RELATIVE', 'CF_RELATIVE_CD'], 'idx_cf_id_relative_relative_cd');
        });

        // تحليل الجدول لتحديث الإحصائيات وتحسين الأداء
        DB::connection('civilregistry')->statement('ANALYZE TABLE relations');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('civilregistry')->table('relations', function (Blueprint $table) {
            // حذف الفهارس عند التراجع
            $table->dropIndex('idx_cf_id_num');
            $table->dropIndex('idx_cf_id_relative');
            $table->dropIndex('idx_cf_relative_cd');
            $table->dropIndex('idx_cf_id_num_relative_cd');
            $table->dropIndex('idx_cf_id_relative_relative_cd');
        });
    }
};
