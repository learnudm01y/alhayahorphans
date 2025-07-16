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
        Schema::table('duplicate_files_temp', function (Blueprint $table) {
            $table->string('temp_path')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('duplicate_files_temp', function (Blueprint $table) {
            $table->string('temp_path')->nullable(false)->change();
        });
    }
};
