<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_transfer_requests', function (Blueprint $table) {
            $table->id();

            // ملف المعيل المتقدم بالطلب = الملف الذي سينتقل إليه الفرد بعد موافقة الإدارة
            $table->unsignedBigInteger('data_id')->nullable();
            $table->unsignedBigInteger('file_id_number');
            // الملف الذي يحتوي الفرد وقت الطلب (قد يتغير قبل الموافقة)
            $table->unsignedBigInteger('source_file_id_number')->nullable();
            $table->unsignedBigInteger('identity_number');

            // بيانات الفرد كما أدخلها المعيل، لا تُكتب في re_people قبل الموافقة
            $table->json('member_snapshot')->nullable();

            $table->string('status', 16)->default('pending');
            $table->string('reason', 191)->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // بلا مفاتيح أجنبية: حذف سجل المعيل لا يجب أن يتعطل بسبب طلب معلّق،
            // والمخطط في الإنتاج قد يسبق الـ migrations فيُرفض القيد عند التناقض.
            $table->index(['file_id_number', 'status'], 'mtr_file_status_idx');
            $table->index(['identity_number', 'status'], 'mtr_identity_status_idx');
            $table->index('source_file_id_number', 'mtr_source_file_idx');
            $table->index('data_id', 'mtr_data_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_transfer_requests');
    }
};
