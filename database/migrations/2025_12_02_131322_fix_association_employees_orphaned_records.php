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
        // Step 1: حذف أو تحديث السجلات التي لا تملك sponsor_id صحيح
        // نحذف السجلات التي sponsor_id لا يوجد في جدول sponsors
        DB::statement('DELETE FROM association_employees WHERE sponsor_id IS NOT NULL AND sponsor_id NOT IN (SELECT id FROM sponsors)');

        // Step 2: جعل sponsor_id nullable مؤقتاً إذا لم يكن كذلك
        Schema::table('association_employees', function (Blueprint $table) {
            $table->unsignedBigInteger('sponsor_id')->nullable()->change();
        });

        // Step 3: إضافة foreign key constraint
        Schema::table('association_employees', function (Blueprint $table) {
            // حذف constraint القديم إن وجد
            try {
                $table->dropForeign(['sponsor_id']);
            } catch (\Exception $e) {
                // Foreign key لا يوجد، نتجاهل الخطأ
            }

            // إضافة constraint جديد
            $table->foreign('sponsor_id')
                  ->references('id')
                  ->on('sponsors')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('association_employees', function (Blueprint $table) {
            $table->dropForeign(['sponsor_id']);
        });
    }
};
