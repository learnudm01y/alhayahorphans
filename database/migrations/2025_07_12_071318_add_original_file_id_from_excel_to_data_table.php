<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('data', function (Blueprint $table) {
            // إضافة عمود لحفظ رقم الملف الأصلي من Excel
            $table->string('original_file_id_from_excel', 50)->nullable()->after('file_id_number')
                  ->comment('رقم الملف الأصلي من ملف Excel قبل الاستبدال');

            // إضافة فهرس للبحث السريع
            $table->index('original_file_id_from_excel', 'idx_original_file_id_from_excel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data', function (Blueprint $table) {
            // حذف الفهرس أولاً
            $table->dropIndex('idx_original_file_id_from_excel');

            // حذف العمود
            $table->dropColumn('original_file_id_from_excel');
        });
    }
};
