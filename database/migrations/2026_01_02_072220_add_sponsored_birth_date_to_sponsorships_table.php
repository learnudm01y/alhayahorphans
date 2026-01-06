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
        Schema::table('sponsorships', function (Blueprint $table) {
            // إضافة عمود تاريخ ميلاد المكفول بعد عمود orphan_name
            if (!Schema::hasColumn('sponsorships', 'sponsored_birth_date')) {
                $table->date('sponsored_birth_date')->nullable()->after('orphan_name')
                    ->comment('تاريخ ميلاد الشخص المكفول');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsorships', function (Blueprint $table) {
            if (Schema::hasColumn('sponsorships', 'sponsored_birth_date')) {
                $table->dropColumn('sponsored_birth_date');
            }
        });
    }
};
