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
        Schema::create('reports_designs', function (Blueprint $table) {
            $table->id();
            $table->string('de_association_namec');
            $table->string('de_head_1_color');
            $table->string('de_head_2_color');
            $table->string('de_paragraph_1_color');
            $table->string('de_paragraph_2_color');
            $table->string('de_captionColor');
            $table->string('de_background_image');
            $table->date('created_at');
            $table->date('update_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports_designs');
    }
};
