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
        Schema::create('guardian_bank_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('guardian_registration')->index();
            $table->unsignedBigInteger('bank_name');
            $table->string('account_number_or_related_phone_number');
            $table->timestamps();

            $table->foreign('guardian_registration')->references('file_id_number')->on('data');
            $table->foreign('bank_name')->references('id')->on('bank_names');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guardian_bank_accounts');
    }
};
