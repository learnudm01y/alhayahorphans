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
        if (Schema::hasTable('city') && !Schema::hasColumn('city', 'province_id')) {
            Schema::table('city', function (Blueprint $table) {
                $table->foreignId('province_id')->nullable()->after('city')->constrained('provinces')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('city') && Schema::hasColumn('city', 'province_id')) {
            Schema::table('city', function (Blueprint $table) {
                $table->dropForeign(['province_id']);
                $table->dropColumn('province_id');
            });
        }
    }
};
