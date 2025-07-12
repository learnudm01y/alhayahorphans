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
        Schema::create('duplicate_files_temp', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 100)->index();
            $table->string('original_name', 255);
            $table->string('duplicate_name', 255);
            $table->string('temp_path', 500);
            $table->string('original_folder', 100)->nullable();
            $table->string('target_folder', 100)->nullable();
            $table->string('existing_file_name', 255)->nullable();
            $table->timestamp('created_at');
            $table->timestamp('expires_at')->index();

            // Indexes for performance
            $table->index(['session_id', 'expires_at']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duplicate_files_temp');
    }
};
