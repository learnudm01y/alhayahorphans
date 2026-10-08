<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_logs', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->string('direction', 10);
            $table->string('msg_id', 100)->nullable();
            $table->string('type', 30)->nullable();
            $table->text('text')->nullable();
            $table->string('media_id', 100)->nullable();
            $table->string('status', 30)->nullable();
            $table->string('error', 500)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index('phone');
            $table->index('msg_id');
            $table->index(['phone', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_logs');
    }
};
