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
        Schema::table('data', function (Blueprint $table) {
            // إضافة أعمدة الأسماء المطبّعة إذا لم تكن موجودة
            if (!Schema::hasColumn('data', 'data_first_name_normalized')) {
                $table->string('data_first_name_normalized')->nullable()->after('data_first_name');
            }
            if (!Schema::hasColumn('data', 'data_father_name_normalized')) {
                $table->string('data_father_name_normalized')->nullable()->after('data_father_name');
            }
            if (!Schema::hasColumn('data', 'data_grand_father_name_normalized')) {
                $table->string('data_grand_father_name_normalized')->nullable()->after('data_grand_father_name');
            }
            if (!Schema::hasColumn('data', 'data_family_name_normalized')) {
                $table->string('data_family_name_normalized')->nullable()->after('data_family_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data', function (Blueprint $table) {
            $table->dropColumn([
                'data_first_name_normalized',
                'data_father_name_normalized',
                'data_grand_father_name_normalized',
                'data_family_name_normalized'
            ]);
        });
    }
};
