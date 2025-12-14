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
            // From sponsorships table
            if (!Schema::hasColumn('sponsor_field_settings', 'field_sponsoring_organization')) {
                $table->boolean('field_sponsoring_organization')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_external_file_number')) {
                $table->boolean('field_external_file_number')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_guardian_identity_number')) {
                $table->boolean('field_guardian_identity_number')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_sponsorship_duration_months')) {
                $table->boolean('field_sponsorship_duration_months')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_sponsorship_start_date')) {
                $table->boolean('field_sponsorship_start_date')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_sponsorship_end_date')) {
                $table->boolean('field_sponsorship_end_date')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_sponsorship_type_id')) {
                $table->boolean('field_sponsorship_type_id')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_sponsorship_status_id')) {
                $table->boolean('field_sponsorship_status_id')->default(0);
            }

            // From data table - Guardian info
            if (!Schema::hasColumn('sponsor_field_settings', 'field_data_id_number')) {
                $table->boolean('field_data_id_number')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_data_first_name')) {
                $table->boolean('field_data_first_name')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_data_father_name')) {
                $table->boolean('field_data_father_name')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_data_grand_father_name')) {
                $table->boolean('field_data_grand_father_name')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_data_family_name')) {
                $table->boolean('field_data_family_name')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_data_birth_date')) {
                $table->boolean('field_data_birth_date')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_data_phone_number')) {
                $table->boolean('field_data_phone_number')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_data_address')) {
                $table->boolean('field_data_address')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_data_city')) {
                $table->boolean('field_data_city')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_data_neighborhood')) {
                $table->boolean('field_data_neighborhood')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_re_guardian_name')) {
                $table->boolean('field_re_guardian_name')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_re_guardian_phone')) {
                $table->boolean('field_re_guardian_phone')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_re_guardian_id')) {
                $table->boolean('field_re_guardian_id')->default(0);
            }

            // From dead_people table
            if (!Schema::hasColumn('sponsor_field_settings', 'field_father_first_name')) {
                $table->boolean('field_father_first_name')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_father_id')) {
                $table->boolean('field_father_id')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_father_death_reason')) {
                $table->boolean('field_father_death_reason')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_mother_first_name')) {
                $table->boolean('field_mother_first_name')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_mother_id')) {
                $table->boolean('field_mother_id')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_mother_death_date')) {
                $table->boolean('field_mother_death_date')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_mother_death_reason')) {
                $table->boolean('field_mother_death_reason')->default(0);
            }

            // From re_people table
            if (!Schema::hasColumn('sponsor_field_settings', 'field_first_name')) {
                $table->boolean('field_first_name')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_person_id')) {
                $table->boolean('field_person_id')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_person_birth_date')) {
                $table->boolean('field_person_birth_date')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_person_age')) {
                $table->boolean('field_person_age')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_person_gender')) {
                $table->boolean('field_person_gender')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_person_health_status')) {
                $table->boolean('field_person_health_status')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_person_type_of_guarantee')) {
                $table->boolean('field_person_type_of_guarantee')->default(0);
            }
            if (!Schema::hasColumn('sponsor_field_settings', 'field_person_note')) {
                $table->boolean('field_person_note')->default(0);
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
                'field_sponsoring_organization',
                'field_external_file_number',
                'field_guardian_identity_number',
                'field_sponsorship_duration_months',
                'field_sponsorship_start_date',
                'field_sponsorship_end_date',
                'field_sponsorship_type_id',
                'field_sponsorship_status_id',
                'field_data_id_number',
                'field_data_first_name',
                'field_data_father_name',
                'field_data_grand_father_name',
                'field_data_family_name',
                'field_data_birth_date',
                'field_data_phone_number',
                'field_data_address',
                'field_data_city',
                'field_data_neighborhood',
                'field_re_guardian_name',
                'field_re_guardian_phone',
                'field_re_guardian_id',
                'field_father_first_name',
                'field_father_id',
                'field_father_death_reason',
                'field_mother_first_name',
                'field_mother_id',
                'field_mother_death_date',
                'field_mother_death_reason',
                'field_first_name',
                'field_person_id',
                'field_person_birth_date',
                'field_person_age',
                'field_person_gender',
                'field_person_health_status',
                'field_person_type_of_guarantee',
                'field_person_note',
            ]);
        });
    }
};
