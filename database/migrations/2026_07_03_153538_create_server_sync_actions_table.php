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
        Schema::create('server_sync_actions', function (Blueprint $table) {
            $table->id();
            $table->string('action_type'); // e.g., 'sponsorship_update', 'sponsorship_create'
            $table->unsignedBigInteger('entity_id'); // ID of the Sponsorship or Data
            $table->json('payload')->nullable(); // The exact data modified
            $table->enum('status', ['pending', 'delivered', 'executed'])->default('pending');
            $table->unsignedBigInteger('user_id')->nullable(); // Target user or null for global
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_sync_actions');
    }
};
