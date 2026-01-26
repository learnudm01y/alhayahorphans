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
        Schema::table('sponsors', function (Blueprint $table) {
            $table->boolean('google_drive_enabled')->default(false)->after('sponsor_name');
            $table->string('google_drive_folder_name')->nullable()->after('google_drive_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            $table->dropColumn(['google_drive_enabled', 'google_drive_folder_name']);
        });
    }
};
