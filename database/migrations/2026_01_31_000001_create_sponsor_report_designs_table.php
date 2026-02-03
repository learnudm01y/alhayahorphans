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
        Schema::create('sponsor_report_designs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sponsor_id')->unique();
            $table->foreign('sponsor_id')->references('id')->on('sponsors')->onDelete('cascade');

            // نوع الخلفية: 'single' أو 'triple'
            $table->enum('background_type', ['single', 'triple'])->default('single');

            // صورة واحدة كاملة (A4)
            $table->string('single_image')->nullable();

            // الصور الثلاثية
            $table->string('header_image')->nullable();
            $table->string('main_image')->nullable();
            $table->string('footer_image')->nullable();

            // ألوان التصميم (JSON)
            $table->json('theme_colors')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sponsor_report_designs');
    }
};
