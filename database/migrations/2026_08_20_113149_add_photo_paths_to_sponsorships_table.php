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
        if (!Schema::hasColumn('sponsorships', 'orphan_photo_path')) {
            Schema::table('sponsorships', function (Blueprint $table) {
                $table->string('orphan_photo_path')->nullable()->after('notes');
            });
        }
        if (!Schema::hasColumn('sponsorships', 'guardian_photo_path')) {
            Schema::table('sponsorships', function (Blueprint $table) {
                $table->string('guardian_photo_path')->nullable()->after('orphan_photo_path');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsorships', function (Blueprint $table) {
            $table->dropColumn(['orphan_photo_path', 'guardian_photo_path']);
        });
    }
};