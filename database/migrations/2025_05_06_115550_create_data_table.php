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
        Schema::create('data', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('file_id_number')->unique();
            $table->unsignedBigInteger('data_section_id');
            $table->unsignedBigInteger('data_request_status');
            $table->integer('data_id_number');
            $table->string('data_first_name');
            $table->string('data_father_name');
            $table->string('data_grand_father_name');
            $table->string('data_family_name');
            $table->unsignedBigInteger('data_relationship');
            $table->date('data_birth_date');
            $table->integer('data_gender');
            $table->integer('data_phone_number');
            $table->integer('data_alt_phone_number');
            $table->integer('data_number_of_individuals');
            $table->unsignedBigInteger('data_marital_status');
            $table->unsignedBigInteger('data_academic_qualification');
            $table->unsignedBigInteger('data_displacement_status');
            $table->string('data_address_before_displacement');
            $table->string('data_current_address');
            $table->unsignedBigInteger('data_city');
            $table->unsignedBigInteger('data_province');
            $table->unsignedBigInteger('data_health_status');
            $table->text('data_description_health_status');
            $table->integer('data_number_mail');
            $table->integer('data_number_female');
            $table->integer('data_number_alt');
            $table->integer('data_number_of_individuals_with_chronic_diseases');
            $table->integer('data_number_of_people_with_special_needs');
            $table->unsignedBigInteger('data_employment_status_breadwinner');
            $table->unsignedBigInteger('data_housing_status');
            $table->unsignedBigInteger('data_current_housing_type');
            $table->string('data_id_image');// صورة الهوية
            $table->string('data_guardianship_argument_image');// صورة حجة الولاية
            $table->string('data_user_insert_data');// المستخدم الذي قام بإدخال البيانات
            $table->timestamps();

            // الفهارس
            $table->foreign('data_request_status')->references('id')->on('request_status');
            $table->foreign('data_relationship')->references('id')->on('category_of_relations');
            $table->foreign('data_marital_status')->references('id')->on('marital_status');
            $table->foreign('data_academic_qualification')->references('id')->on('academic_degrees');
            // $table->foreign('data_displacement_status')->references('id')->on('general_category');
            $table->foreign('data_city')->references('id')->on('city');
            $table->foreign('data_province')->references('id')->on('provinces');
            $table->foreign('data_health_status')->references('id')->on('health_statuses');
            $table->foreign('data_employment_status_breadwinner')->references('id')->on('employment');
            $table->foreign('data_housing_status')->references('id')->on('housing_status');
            $table->foreign('data_current_housing_type')->references('id')->on('type_of_accommodation');

        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data');
    }
};
