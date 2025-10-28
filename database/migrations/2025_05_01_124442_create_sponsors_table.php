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
        Schema::create('sponsors', function (Blueprint $table) {
            // المفتاح الأساسي
            $table->id();

            // شنايدر الملف (لا علاقة خارجية معرفة)
            $table->unsignedBigInteger('file_id',false)->unique();

            // بيانات الراعي
            $table->string('sponsor_name',255);
            $table->string('sponsor_short_name');
            $table->string('sponsor_phone_number', 20); // تغيير إلى string لتجنب مشاكل الأصفار البادئة
            $table->string('sponsor_email',255);
            $table->string('sponsor_address',255);

            // مفتاح خارجي لبنك الراعي، قابل لأن يكون NULL حتى يعمل SET NULL
            $table->unsignedBigInteger('sponsor_bank_name_id')->nullable();

            // رقم حساب البنك، كحقل عددي كبير
            $table->string('sponsor_account_bank_number', 50);

            // رمز السويفت ورقم هاتف البنك البديل
            $table->string('sponsor_bank_swift_code',30);
            $table->string('sponsor_bank_related_phone_number', 20);

            // مفتاح خارجي لنوع العملة، قابل لأن يكون NULL حتى يعمل SET NULL
            $table->unsignedBigInteger('sponsor_bank_account_currency')->nullable();

            // **كود الدولة** ضمن جدول ci_birth_cd، قابل لأن يكون NULL
            $table->string('country_code', 5)->nullable();

            // created_at و updated_at تلقائياً
            $table->timestamps();

            // تعريف العلاقات الخارجية
            $table->foreign('sponsor_bank_name_id')
                ->references('id')
                ->on('bank_names')
                ->onDelete('set null');

            $table->foreign('sponsor_bank_account_currency')
                ->references('id')
                ->on('currency_types')
                ->onDelete('set null');

            $table->foreign('country_code')
                ->references('code')
                ->on('CI_BIRTH_CD')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sponsors');
    }
};
