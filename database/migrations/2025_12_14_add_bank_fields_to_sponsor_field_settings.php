<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            // Bank Account Fields
            $table->boolean('field_bank_account_name')->default(0)->after('field_person_note');
            $table->boolean('field_bank_account_number')->default(0)->after('field_bank_account_name');
            $table->boolean('field_bank_name')->default(0)->after('field_bank_account_number');
            $table->boolean('field_bank_branch')->default(0)->after('field_bank_name');
            $table->boolean('field_bank_iban')->default(0)->after('field_bank_branch');
            $table->boolean('field_account_owner_id')->default(0)->after('field_bank_iban');
        });
    }

    public function down(): void
    {
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            $table->dropColumn([
                'field_bank_account_name',
                'field_bank_account_number',
                'field_bank_name',
                'field_bank_branch',
                'field_bank_iban',
                'field_account_owner_id',
            ]);
        });
    }
};
