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
            $table->unsignedBigInteger('file_id')->index();
            $table->unsignedBigInteger('registration_id');
            $table->unsignedBigInteger('sponsorship_status');
            $table->string('first_name');
            $table->string('second_name');
            $table->string('third_name');
            $table->string('last_name');
            $table->bigInteger('orphan_id');
            $table->date('orphan_birth_date');
            $table->integer('orphan_age');
            $table->integer('orphan_gender');
            $table->unsignedBigInteger('orphan_health_status');
            $table->string('orphan_birth_certificate');
            $table->string('orphan_photo');
            $table->unsignedBigInteger('orphan_type_of_guarantee');
            $table->timestamps();

            $table->foreign('sponsorship_status')->references('id')->on('sponsorship_statuses');
            $table->foreign('orphan_health_status')->references('id')->on('health_statuses');
            $table->foreign('orphan_type_of_guarantee')->references('id')->on('type_of_guarantee');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orphans');
    }
};
