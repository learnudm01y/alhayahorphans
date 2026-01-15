<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * إضافة عمود mother_death_date لجدول dead_people
     * هذا العمود كان مفقوداً في الـ migration الأصلي
     */
    public function up(): void
    {
        Schema::table('dead_people', function (Blueprint $table) {
            // إضافة تاريخ وفاة الأم (كان مفقوداً في الإنشاء الأصلي)
            if (!Schema::hasColumn('dead_people', 'mother_death_date')) {
                $table->date('mother_death_date')->nullable()->after('mother_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dead_people', function (Blueprint $table) {
            if (Schema::hasColumn('dead_people', 'mother_death_date')) {
                $table->dropColumn('mother_death_date');
            }
        });
    }
};
