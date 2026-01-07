<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * جدول المتوفين الإضافيين - لتخزين أي متوفي إضافي غير الأب والأم
     */
    public function up(): void
    {
        // التحقق من عدم وجود الجدول مسبقاً
        if (!Schema::hasTable('additional_deceased')) {
            Schema::create('additional_deceased', function (Blueprint $table) {
                $table->bigIncrements('id');

                // رقم الملف المرتبط (نفس file_id_number في جدول data - نوعه string)
                $table->string('re_file_id', 255);

                // رقم هوية المتوفي
                $table->string('person_id', 20)->nullable();

                // بيانات الاسم
                $table->string('first_name', 50)->nullable();
                $table->string('second_name', 50)->nullable();
                $table->string('third_name', 50)->nullable();
                $table->string('last_name', 50)->nullable();

                // صلة القرابة (father, mother, brother, sister, grandfather, grandmother, uncle, aunt, other)
                $table->string('relationship', 30);

                // تاريخ الوفاة
                $table->date('death_date')->nullable();

                // سبب الوفاة (مرتبط بجدول death_reasons)
                $table->unsignedBigInteger('death_reason')->nullable();

                $table->timestamps();

                // الفهارس
                $table->index('re_file_id', 'idx_additional_deceased_file_id');
                $table->index('person_id', 'idx_additional_deceased_person_id');
                $table->index('relationship', 'idx_additional_deceased_relationship');

                // العلاقات
                $table->foreign('re_file_id')
                      ->references('file_id_number')
                      ->on('data')
                      ->onDelete('cascade');

                $table->foreign('death_reason')
                      ->references('id')
                      ->on('death_reasons')
                      ->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('additional_deceased');
    }
};
