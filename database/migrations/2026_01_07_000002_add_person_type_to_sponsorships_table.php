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
            // إضافة عمود نوع الشخص (يتيم أو معيل)
            if (!Schema::hasColumn('sponsorships', 'person_type')) {
                $table->string('person_type', 50)->nullable()->after('sponsorship_status_id')->comment('نوع الشخص: orphan أو breadwinner');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsorships', function (Blueprint $table) {
            if (Schema::hasColumn('sponsorships', 'person_type')) {
                $table->dropColumn('person_type');
            }
        });
    }
};
