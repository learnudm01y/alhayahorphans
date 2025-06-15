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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();

            // رقم الهوية المرتبط بالوثيقة (مطلوب)
            $table->string('person_identity_number');

            // اسم الملف الفعلي المخزن (يتضمن رقم الهوية + نوع الوثيقة)
            $table->string('stored_file_name');

            // مسار تخزين الملف
            $table->string('file_path');

            // التواريخ
            $table->timestamps();

            // فهرسة رقم الهوية لتحسين الاستعلامات
            $table->index('person_identity_number');
             $table->string('file_type')->default(0);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
