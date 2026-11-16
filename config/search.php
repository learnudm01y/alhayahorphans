<?php

use App\Models\Data;
use App\Models\DeadPepole;
use App\Models\RePeople;

return [
    'max_per_page' => 100,
    'min_search_length' => 3,
    'enable_cache' => true,
    'cache_duration' => 15,
    'rate_limit_per_minute' => 60,
    'autocomplete_max_results' => 10,
    'guardian_filters' => [
        'section_id' => 'data_section_id',
        'city_id' => 'data_city',
        'request_status_id' => 'data_request_status',
    ],
    'searchable_tables' => [
        'main_records' => [
            'model' => Data::class,
            'table' => 'data',
            'file' => 'file_id_number',
            'identity' => 'data_id_number',
            'names' => ['data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name'],
            'gender' => 'data_gender',
            'birth_date' => 'data_birth_date',
            'filters' => [
                'relationship_id' => 'data_relationship',
                'marital_status_id' => 'data_marital_status',
                'academic_qualification_id' => 'data_academic_qualification',
                'health_status_id' => 'data_health_status',
                'employment_status_id' => 'data_employment_status_breadwinner',
            ],
            'relations' => ['section', 'city', 'requestStatus', 'categoryOfRelation', 'maritalStatus', 'academicQualification', 'healthStatus', 'employmentStatusBreadwinner'],
        ],
        'family_members' => [
            'model' => RePeople::class,
            'table' => 're_people',
            'file' => 'registration_id',
            'identity' => 'person_id',
            'names' => ['first_name', 'second_name', 'third_name', 'last_name'],
            'gender' => 'person_gender',
            'birth_date' => 'person_birth_date',
            'filters' => ['health_status_id' => 'person_health_status'],
            'relations' => ['healthStatus', 'dataRecord.section', 'dataRecord.city', 'dataRecord.requestStatus'],
        ],
        'deceased' => [
            'model' => DeadPepole::class,
            'table' => 'dead_people',
            'file' => 're_file_id',
            'filters' => [],
            'relations' => ['fatherDeathReason', 'motherDeathReason', 'dataRecord.section', 'dataRecord.city', 'dataRecord.requestStatus'],
        ],
    ],
];
