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
        Schema::table('sponsorships', function (Blueprint $table) {
            // إضافة عمود updated_by لتخزين المستخدمين الذين عدلوا السجل كمصفوفة JSON
            $table->json('updated_by')->nullable()->after('updated_at')
                ->comment('مصفوفة JSON تحتوي على المستخدمين الذين قاموا بتعديل السجل مع التواريخ');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsorships', function (Blueprint $table) {
            $table->dropColumn('updated_by');
        });
    }
};
