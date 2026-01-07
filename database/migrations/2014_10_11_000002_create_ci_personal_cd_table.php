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
        // التحقق من عدم وجود الجدول مسبقاً لتجنب خطأ التكرار
        if (!Schema::hasTable('ci_personal_cd')) {
            Schema::create('ci_personal_cd', function (Blueprint $table) {
                $table->id();
                $table->string('CI_PERSONAL_CD', 255);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_personal_cd');
    }
};
