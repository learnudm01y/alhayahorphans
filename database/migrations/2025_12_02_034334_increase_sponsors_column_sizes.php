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
        Schema::table('sponsors', function (Blueprint $table) {
            $table->string('sponsor_phone_number', 50)->nullable()->change();
            $table->string('sponsor_bank_related_phone_number', 50)->nullable()->change();
            $table->string('sponsor_account_bank_number', 100)->nullable()->change();
            $table->string('sponsor_bank_swift_code', 50)->nullable()->change();
            $table->string('sponsor_address', 500)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            $table->string('sponsor_phone_number', 20)->nullable()->change();
            $table->string('sponsor_bank_related_phone_number', 20)->nullable()->change();
            $table->string('sponsor_account_bank_number', 50)->nullable()->change();
            $table->string('sponsor_bank_swift_code', 30)->nullable()->change();
            $table->string('sponsor_address', 255)->nullable()->change();
        });
    }
};
