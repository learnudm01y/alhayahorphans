<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول قائمة الوفيات — يُنشأ داخل قاعدة بيانات السجل المدني (civilregistry)
 * بنفس اتصال جدول persons حتى يتم الدمج مباشرة برقم الهوية.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('civilregistry')->create('deaths_list', function (Blueprint $table) {
            $table->id();
            $table->string('identity_number', 20)->index();   // رقم الهوية
            $table->string('name', 255);                      // الاسم كما ورد في الملف
            $table->string('status', 20)->default('متوفى');    // الحالة
            $table->boolean('civil_matched')->default(false);  // هل وُجد مطابق في persons
            $table->string('source_file')->nullable();         // اسم الملف المصدر
            $table->unsignedBigInteger('imported_by')->nullable(); // المستخدم الذي استورد
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('civilregistry')->dropIfExists('deaths_list');
    }
};
