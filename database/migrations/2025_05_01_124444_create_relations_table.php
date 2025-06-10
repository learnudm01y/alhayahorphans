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
        Schema::create('relations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('CF_ID_NUM',false,20);
            $table->unsignedBigInteger('CF_RELATIVE_CD',false,20);
            $table->unsignedBigInteger('CF_ID_RELATIVE',false,20);

            $table->foreign('CF_ID_NUM')->references('CI_ID_NUM')->on('persons');
            $table->foreign('CF_RELATIVE_CD')->references('id')->on('category_of_relations');
            $table->foreign('CF_ID_RELATIVE')->references('CI_ID_NUM')->on('persons');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('relations');
    }
};
