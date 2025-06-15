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
        Schema::create('re_people', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('registration_id')->nullable();
            $table->unsignedBigInteger('sponsorship_status')->nullable()->default(1);
            $table->string('first_name')->nullable();
            $table->string('second_name')->nullable();
            $table->string('third_name')->nullable();
            $table->string('last_name')->nullable();
            $table->bigInteger('person_id')->nullable();
            $table->date('person_birth_date')->nullable();
            $table->integer('person_age')->nullable();
            $table->integer('person_gender')->nullable();
            $table->unsignedBigInteger('person_health_status')->nullable();
            $table->string('person_birth_certificate')->nullable();
            $table->string('person_photo')->nullable();
            $table->unsignedBigInteger('person_type_of_guarantee')->nullable();
            $table->timestamps();

            $table->foreign('sponsorship_status')->references('id')->on('sponsorship_statuses');
            $table->foreign('person_health_status')->references('id')->on('health_statuses');
            $table->foreign('person_type_of_guarantee')->references('id')->on('type_of_guarantee');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('persons');
    }
};
