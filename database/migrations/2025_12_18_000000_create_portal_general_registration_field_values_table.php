<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // قد تفشل عملية إنشاء الفهارس إذا كان الاسم طويلًا في MySQL؛
        // كذلك قد تكون هناك محاولة إنشاء سابقة جزئية.
        Schema::dropIfExists('portal_general_registration_field_values');

        Schema::create('portal_general_registration_field_values', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('sponsorship_id')->nullable();
            // رقم ملف الشخص (file_id_number في جدول data/re_people) – نخزنه كسلسلة لتفادي اختلاف النوع
            $table->string('file_id_number', 64)->nullable();
            $table->string('identity_number', 64)->nullable();

            $table->string('field_key', 191);
            $table->longText('field_value')->nullable();

            $table->unsignedBigInteger('updated_by_user_id')->nullable();

            $table->timestamps();

            // المطلوب: ربط بناء على رقم الملف + اسم الحقل
            $table->unique(['file_id_number', 'field_key'], 'pgrfv_file_field_uq');

            // فهارس بأسماء قصيرة لتجنب خطأ طول المعرف في MySQL
            $table->index('sponsorship_id', 'pgrfv_sponsorship_idx');
            $table->index('file_id_number', 'pgrfv_file_idx');
            $table->index('identity_number', 'pgrfv_identity_idx');
            $table->index('updated_by_user_id', 'pgrfv_updated_by_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_general_registration_field_values');
    }
};
