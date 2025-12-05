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
            // إضافة العمود فقط إذا لم يكن موجوداً
            if (!Schema::hasColumn('sponsorships', 'guardian_identity_number')) {
                $table->string('guardian_identity_number')->nullable()->after('guardian_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsorships', function (Blueprint $table) {
            if (Schema::hasColumn('sponsorships', 'guardian_identity_number')) {
                $table->dropColumn('guardian_identity_number');
            }
        });
    }
};
