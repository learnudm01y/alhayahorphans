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
        Schema::create('dead_people', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('re_file_id');//this is the file id related to the orphan file_id
            // والد
            $table->string('father_first_name');
            $table->string('father_second_name');
            $table->string('father_third_name');
            $table->string('father_last_name');
            $table->integer('father_id');
            $table->date('father_death_date');
            $table->unsignedBigInteger('father_death_reason');
            $table->string('father_death_certificate');
            // والدة
            $table->string('mother_first_name');
            $table->string('mother_second_name');
            $table->string('mother_third_name');
            $table->string('mother_last_name');
            $table->integer('mother_id');
            $table->date('mother_death_date');
            $table->unsignedBigInteger('mother_death_reason');
            $table->string('mother_death_certificate');
            $table->timestamps();

            $table->foreign('re_file_id')->references('file_id_number')->on('data');
            $table->foreign('father_death_reason')->references('id')->on('death_reasons');
            $table->foreign('mother_death_reason')->references('id')->on('death_reasons');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dead_pepoles');
    }
};
