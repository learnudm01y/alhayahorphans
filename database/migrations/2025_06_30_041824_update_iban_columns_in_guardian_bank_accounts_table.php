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
        Schema::table('guardian_bank_accounts', function (Blueprint $table) {
            // إضافة عمود IBAN الشيكل
            $table->string('iban_shekel', 50)->nullable();

            // تعديل اسم العمود الحالي إلى IBAN الدولار
            $table->renameColumn('account_number_or_related_phone_number', 'iban_usd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guardian_bank_accounts', function (Blueprint $table) {
            // حذف عمود IBAN الشيكل
            $table->dropColumn('iban_shekel');

            // إعادة اسم العمود إلى حالته الأصلية
            $table->renameColumn('iban_usd', 'account_number_or_related_phone_number');
        });
    }
};
