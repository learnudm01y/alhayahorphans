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
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            // حذف الحقول القديمة
            if (Schema::hasColumn('sponsor_field_settings', 'field_bank_account_name')) {
                $table->dropColumn('field_bank_account_name');
            }
            if (Schema::hasColumn('sponsor_field_settings', 'field_bank_account_number')) {
                $table->dropColumn('field_bank_account_number');
            }
            if (Schema::hasColumn('sponsor_field_settings', 'field_bank_name')) {
                $table->dropColumn('field_bank_name');
            }
            if (Schema::hasColumn('sponsor_field_settings', 'field_bank_branch')) {
                $table->dropColumn('field_bank_branch');
            }
            if (Schema::hasColumn('sponsor_field_settings', 'field_bank_iban')) {
                $table->dropColumn('field_bank_iban');
            }
            if (Schema::hasColumn('sponsor_field_settings', 'field_account_owner_id')) {
                $table->dropColumn('field_account_owner_id');
            }
        });

        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            // إضافة الحقول الجديدة بدون تحديد الموقع لتجنب مشاكل الأعمدة المفقودة
            $table->boolean('field_guardian_account_owner_name')->default(false)->comment('اسم صاحب الحساب');
            $table->boolean('field_guardian_bank_name')->default(false)->comment('اسم البنك');
            $table->boolean('field_guardian_id_owner')->default(false)->comment('رقم هوية صاحب الحساب');
            $table->boolean('field_guardian_phone_number')->default(false)->comment('رقم هاتف صاحب الحساب');
            $table->boolean('field_guardian_iban_usd')->default(false)->comment('رقم IBAN بالدولار');
            $table->boolean('field_guardian_iban_shekel')->default(false)->comment('رقم IBAN بالشيكل');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            // حذف الحقول الجديدة
            $table->dropColumn([
                'field_guardian_account_owner_name',
                'field_guardian_bank_name',
                'field_guardian_id_owner',
                'field_guardian_phone_number',
                'field_guardian_iban_usd',
                'field_guardian_iban_shekel',
            ]);
        });

        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            // استرجاع الحقول القديمة
            $table->boolean('field_bank_account_name')->default(false);
            $table->boolean('field_bank_account_number')->default(false);
            $table->boolean('field_bank_name')->default(false);
            $table->boolean('field_bank_branch')->default(false);
            $table->boolean('field_bank_iban')->default(false);
            $table->boolean('field_account_owner_id')->default(false);
        });
    }
};
