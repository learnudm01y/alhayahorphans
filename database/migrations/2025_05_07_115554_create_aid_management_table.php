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
        Schema::create('aid_management', function (Blueprint $table) {
            $table->bigInteger('file_id');
            $table->bigIncrements('id');
            $table->string('first_name');
            $table->string('second_name');
            $table->string('third_name');
            $table->string('last_name');
            $table->integer('aid_id_number');
            $table->integer('aid_category');
            $table->integer('aid_phone_number');
            $table->bigInteger('aid_quantity');
            $table->integer('aid_delivery_status');
            $table->integer('aid_family_individuals_number');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aid_management');
    }
};
