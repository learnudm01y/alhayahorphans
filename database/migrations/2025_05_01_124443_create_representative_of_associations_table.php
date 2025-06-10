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
        Schema::create('representative_of_associations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('file_id',false);
            $table->unsignedBigInteger('re_file_id',false);
            $table->string('re_name', 255);
            $table->string('re_phone_number',20);
            $table->string('re_phone_alt_number',20)->nullable();
            $table->string('re_email', 255);
            $table->string('re_the_attribute', 255);
            $table->timestamps();

            $table->foreign('re_file_id')->references('file_id')->on('sponsors');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('representative_of_associations');
    }
};
