<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_unanswered_questions', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->text('question');
            $table->timestamps();

            $table->index('phone');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_unanswered_questions');
    }
};
