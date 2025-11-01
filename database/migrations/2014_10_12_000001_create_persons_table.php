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
        Schema::create('persons', function (Blueprint $table) {
            $table->bigIncrements('ID');
            $table->unsignedBigInteger('CI_ID_NUM',false)->unique();
            $table->string('CI_FIRST_ARB',255);
            $table->string('CI_FATHER_ARB',255);
            $table->string('CI_GRAND_FATHER_ARB',255);
            $table->string('CI_FAMILY_ARB',255);
            $table->unsignedBigInteger('CI_BIRTH_TB_CD',false);
            $table->unsignedBigInteger('CI_BIRTH_CD',false);
            $table->date('CI_BIRTH_DT');
            $table->integer('CI_SEX_CD',false);
            $table->unsignedBigInteger('CI_PERSONAL_CD',false);
            $table->unsignedBigInteger('CI_DEAD_DT',false);
            $table->string('MOTHER_NAME1',255);
            $table->unsignedBigInteger('CITY',false);
            $table->string('STREET',255);
            $table->string('HOUSE_NO',255);
            $table->timestamps();
            $table->foreign('CI_BIRTH_TB_CD')->references('id')->on('ci_birth_tb_cd');
            $table->foreign('CI_BIRTH_CD')->references('id')->on('ci_birth_cd');
            $table->foreign('CI_PERSONAL_CD')->references('id')->on('ci_personal_cd');
            $table->foreign('CITY')->references('id')->on('city');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('civil_registry');
    }
};
