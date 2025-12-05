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
        // Check if table already exists
        if (!Schema::hasTable('sponsorship_sponsor')) {
            Schema::create('sponsorship_sponsor', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sponsorship_id');
                $table->unsignedBigInteger('sponsor_id');
                $table->timestamps();

                // Foreign keys
                $table->foreign('sponsorship_id')
                    ->references('id')
                    ->on('sponsorships')
                    ->onDelete('cascade');

                $table->foreign('sponsor_id')
                    ->references('id')
                    ->on('sponsors')
                    ->onDelete('cascade');

                // Unique constraint to prevent duplicate entries
                $table->unique(['sponsorship_id', 'sponsor_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sponsorship_sponsor');
    }
};
