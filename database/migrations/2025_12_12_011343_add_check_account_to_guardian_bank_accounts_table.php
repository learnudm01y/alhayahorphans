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
            // إضافة عمود check_account للإشارة إلى الحساب المعتمد
            $table->tinyInteger('check_account')->default(0)->after('iban_shekel')
                ->comment('0 = حساب غير معتمد، 1 = حساب معتمد');

            // إضافة index لتسريع البحث
            $table->index('check_account');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guardian_bank_accounts', function (Blueprint $table) {
            $table->dropIndex(['check_account']);
            $table->dropColumn('check_account');
        });
    }
};
