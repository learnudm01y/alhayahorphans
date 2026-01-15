<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * إضافة عمود notes لجدول reserved_codes
     */
    public function up(): void
    {
        Schema::table('reserved_codes', function (Blueprint $table) {
            if (!Schema::hasColumn('reserved_codes', 'notes')) {
                $table->string('notes')->nullable()->after('used');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reserved_codes', function (Blueprint $table) {
            if (Schema::hasColumn('reserved_codes', 'notes')) {
                $table->dropColumn('notes');
            }
        });
    }
};
