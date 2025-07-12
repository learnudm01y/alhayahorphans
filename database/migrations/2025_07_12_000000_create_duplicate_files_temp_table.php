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
            $table->string('session_id')->index();
            $table->string('original_name');
            $table->string('duplicate_name');
            $table->string('temp_path');
            $table->string('original_folder');
            $table->string('target_folder');
            $table->string('existing_file_name');
            $table->timestamp('created_at');
            $table->timestamp('expires_at')->index();
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
