<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // حذف الـ foreign key constraint
        Schema::table('sponsors', function (Blueprint $table) {
            $table->dropForeign(['sponsor_bank_account_currency']);
        });

        // تغيير نوع العمود
        DB::statement('ALTER TABLE sponsors MODIFY sponsor_bank_account_currency VARCHAR(10) NULL');
    }

    public function down(): void
    {
        // إرجاع النوع القديم
        DB::statement('ALTER TABLE sponsors MODIFY sponsor_bank_account_currency BIGINT UNSIGNED NULL');

        // إعادة الـ foreign key
        Schema::table('sponsors', function (Blueprint $table) {
            $table->foreign('sponsor_bank_account_currency')
                  ->references('id')
                  ->on('currency_types')
                  ->onDelete('set null');
        });
    }
};
