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
        // الطريقة الآمنة: تحديث البيانات الخاطئة إلى NULL بدلاً من حذفها
        DB::statement('
            UPDATE association_employees
            SET sponsor_id = NULL
            WHERE sponsor_id IS NOT NULL
            AND sponsor_id NOT IN (SELECT id FROM sponsors)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // لا حاجة للتراجع
    }
};
