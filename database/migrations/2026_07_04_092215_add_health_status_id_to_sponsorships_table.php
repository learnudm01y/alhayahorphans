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
            if (!Schema::hasColumn('sponsorships', 'health_status_id')) {
                $table->unsignedBigInteger('health_status_id')->nullable()->after('person_type')->comment('الحالة الصحية');
                $table->foreign('health_status_id')->references('id')->on('health_statuses')->onDelete('set null')->onUpdate('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsorships', function (Blueprint $table) {
            if (Schema::hasColumn('sponsorships', 'health_status_id')) {
                $table->dropForeign(['health_status_id']);
                $table->dropColumn('health_status_id');
            }
        });
    }
};
