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
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            // حقول أسماء الأب المتوفى
            $table->boolean('field_father_second_name')->default(false)->after('field_father_first_name');
            $table->boolean('field_father_third_name')->default(false)->after('field_father_second_name');
            $table->boolean('field_father_last_name')->default(false)->after('field_father_third_name');

            // حقول أسماء الأم المتوفاة
            $table->boolean('field_mother_second_name')->default(false)->after('field_mother_first_name');
            $table->boolean('field_mother_third_name')->default(false)->after('field_mother_second_name');
            $table->boolean('field_mother_last_name')->default(false)->after('field_mother_third_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            $table->dropColumn([
                'field_father_second_name',
                'field_father_third_name',
                'field_father_last_name',
                'field_mother_second_name',
                'field_mother_third_name',
                'field_mother_last_name'
            ]);
        });
    }
};
