<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait SeedsV4ReferenceData
{
    protected function seedV4ReferenceData(): void
    {
        if (!DB::table('ci_birth_cd')->where('code', 'PS')->exists()) {
            DB::table('ci_birth_cd')->insert([
                'ci_birth_cd' => 'فلسطين',
                'flag' => 'palestine.svg',
                'code' => 'PS',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $descriptive = [
            'sponsorship_statuses' => 'Active',
            'health_statuses' => 'Healthy',
            'type_of_guarantee' => 'Monthly',
            'request_status' => 'Pending',
            'marital_status' => 'Single',
            'academic_degrees' => 'None',
            'employment' => 'Unemployed',
            'housing_status' => 'Owned',
            'type_of_accommodation' => 'House',
            'provinces' => 'Gaza',
            'death_reasons' => 'Natural',
            'displacement_statuses' => 'Not displaced',
        ];

        foreach ($descriptive as $table => $description) {
            if (!Schema::hasTable($table) || DB::table($table)->where('id', 1)->exists()) {
                continue;
            }
            DB::table($table)->insert([
                'id' => 1,
                'description' => $description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('city') && !DB::table('city')->where('id', 1)->exists()) {
            DB::table('city')->insert([
                'id' => 1,
                'city' => 'Gaza',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('category_of_relations') && !DB::table('category_of_relations')->where('id', 1)->exists()) {
            DB::table('category_of_relations')->insert([
                'id' => 1,
                'attribute' => 'Father',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function validDataPayload(array $overrides = []): array
    {
        return array_merge([
            'file_id_number' => 900001,
            'data_section_id' => 1,
            'data_request_status' => 1,
            'data_id_number' => 900000001,
            'data_first_name' => 'سارة',
            'data_father_name' => 'أحمد',
            'data_grand_father_name' => 'محمد',
            'data_family_name' => 'التجربة',
            'data_relationship' => 1,
            'data_birth_date' => '1990-01-01',
            'data_gender' => 1,
            'data_phone_number' => 599999999,
            'data_alt_phone_number' => 599999998,
            'data_number_of_individuals' => 1,
            'data_marital_status' => 1,
            'data_academic_qualification' => 1,
            'data_displacement_status' => 1,
            'data_address_before_displacement' => 'Test Address',
            'data_current_address' => 'Test Address',
            'data_city' => 1,
            'data_province' => 1,
            'data_health_status' => 1,
            'data_description_needs' => 'Test needs',
            'data_number_mail' => 0,
            'data_number_female' => 0,
            'data_number_alt' => 0,
            'data_number_of_individuals_with_chronic_diseases' => 0,
            'data_number_of_people_with_special_needs' => 0,
            'data_employment_status_breadwinner' => 1,
            'data_housing_status' => 1,
            'data_current_housing_type' => 1,
            'data_user_insert_data' => 'test',
        ], $overrides);
    }
}
