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
            // حقول الأم (حية أو متوفية)
            if (!Schema::hasColumn('sponsor_field_settings', 'field_mother_status')) {
                $table->boolean('field_mother_status')->default(1)->after('field_mother_death_reason');
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_living_mother_first_name')) {
                $table->boolean('field_living_mother_first_name')->default(1)->after('field_mother_status');
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_living_mother_second_name')) {
                $table->boolean('field_living_mother_second_name')->default(1)->after('field_living_mother_first_name');
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_living_mother_third_name')) {
                $table->boolean('field_living_mother_third_name')->default(1)->after('field_living_mother_second_name');
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_living_mother_last_name')) {
                $table->boolean('field_living_mother_last_name')->default(1)->after('field_living_mother_third_name');
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_living_mother_id')) {
                $table->boolean('field_living_mother_id')->default(1)->after('field_living_mother_last_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            $table->dropColumn([
                'field_mother_status',
                'field_living_mother_first_name',
                'field_living_mother_second_name',
                'field_living_mother_third_name',
                'field_living_mother_last_name',
                'field_living_mother_id',
            ]);
        });
    }
};
