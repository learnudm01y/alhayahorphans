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
        Schema::create('sponsorships', function (Blueprint $table) {
            $table->id();
            
            // إسم الكافل (من جدول الكفلاء)
            $table->unsignedBigInteger('sponsor_id')->nullable()->comment('معرف الكافل');
            $table->foreign('sponsor_id')->references('id')->on('sponsors')->onDelete('cascade')->onUpdate('cascade');
            
            // إسم المؤسسة الكافلة
            $table->string('sponsoring_organization', 255)->nullable()->comment('إسم المؤسسة الكافلة');
            
            // رقم الملف الداخلي (من جدول data)
            $table->string('internal_file_number', 100)->nullable()->comment('رقم الملف الداخلي');
            
            // رقم الملف الخارجي
            $table->string('external_file_number', 100)->nullable()->comment('رقم الملف الخارجي');
            
            // رقم الهوية (من جدول re_people)
            $table->string('identity_number', 50)->nullable()->comment('رقم الهوية');
            
            // الإسم (من جدول re_people)
            $table->string('orphan_name', 255)->nullable()->comment('إسم اليتيم');
            
            // إسم المعيل (من جدول data)
            $table->string('guardian_name', 255)->nullable()->comment('إسم المعيل');
            
            // مدة الكفالة
            $table->integer('sponsorship_duration_months')->nullable()->comment('مدة الكفالة بالأشهر');
            $table->date('sponsorship_start_date')->nullable()->comment('تاريخ بداية الكفالة');
            $table->date('sponsorship_end_date')->nullable()->comment('تاريخ نهاية الكفالة');
            
            // نوع الكفالة (من جدول type_of_guarantee)
            $table->unsignedBigInteger('sponsorship_type_id')->nullable()->comment('نوع الكفالة');
            $table->foreign('sponsorship_type_id')->references('id')->on('type_of_guarantee')->onDelete('set null')->onUpdate('cascade');
            
            // حالة الكفالة
            $table->unsignedBigInteger('sponsorship_status_id')->nullable()->comment('حالة الكفالة');
            $table->foreign('sponsorship_status_id')->references('id')->on('sponsorship_statuses')->onDelete('set null')->onUpdate('cascade');
            
            // معلومات إضافية
            $table->text('notes')->nullable()->comment('ملاحظات');
            $table->unsignedBigInteger('created_by')->nullable()->comment('المستخدم الذي أنشأ السجل');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null')->onUpdate('cascade');
            
            $table->timestamps();
            
            // Indexes for better performance
            $table->index('sponsor_id');
            $table->index('internal_file_number');
            $table->index('external_file_number');
            $table->index('identity_number');
            $table->index('sponsorship_type_id');
            $table->index('sponsorship_status_id');
            $table->index('sponsorship_start_date');
            $table->index('sponsorship_end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sponsorships');
    }
};
