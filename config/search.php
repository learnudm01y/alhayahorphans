<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Search Configuration
    |--------------------------------------------------------------------------
    |
    | تكوين إعدادات البحث الشامل في النظام
    |
    */

    // الحد الأقصى لعدد النتائج في الصفحة الواحدة
    'max_per_page' => 100,

    // الحد الأقصى لعدد النتائج في البحث الشامل (جميع الجداول)
    'max_all_results' => 60, // 20 لكل جدول

    // الحد الأدنى لطول النص للبحث السريع
    'min_search_length' => 3,

    // مهلة البحث بالثواني (للاستعلامات المعقدة)
    'search_timeout' => 30,

    // تفعيل التخزين المؤقت للنتائج
    'enable_cache' => true,

    // مدة التخزين المؤقت بالدقائق
    'cache_duration' => 15,

    // الحد الأقصى لعدد عمليات البحث للمستخدم الواحد في الدقيقة
    'rate_limit_per_minute' => 60,

    // الجداول المتاحة للبحث
    'searchable_tables' => [
        'data' => [
            'model' => \App\Models\Data::class,
            'name' => 'السجلات الرئيسية',
            'fields' => [
                'text_search' => [
                    'data_first_name',
                    'data_father_name',
                    'data_grand_father_name',
                    'data_family_name',
                    'data_id_number',
                    'file_id_number',
                    'data_phone_number',
                    'data_alt_phone_number'
                ],
                'exact_match' => [
                    'file_id_number',
                    'data_id_number',
                    'data_phone_number'
                ],
                'date_fields' => [
                    'data_birth_date'
                ],
                'relationship_fields' => [
                    'data_section_id' => 'section',
                    'data_city' => 'city',
                    'data_request_status' => 'requestStatus',
                    'data_relationship' => 'categoryOfRelation',
                    'data_marital_status' => 'maritalStatus',
                    'data_academic_qualification' => 'academicQualification',
                    'data_health_status' => 'healthStatus',
                    'data_employment_status_breadwinner' => 'employmentStatusBreadwinner'
                ]
            ]
        ],

        'family_members' => [
            'model' => \App\Models\RePeople::class,
            'name' => 'أفراد الأسرة',
            'fields' => [
                'text_search' => [
                    'first_name',
                    'second_name',
                    'third_name',
                    'last_name',
                    'person_id',
                    'registration_id'
                ],
                'exact_match' => [
                    'person_id',
                    'registration_id'
                ],
                'date_fields' => [
                    'person_birth_date'
                ],
                'relationship_fields' => [
                    'person_health_status' => 'healthStatus',
                    'sponsorship_status' => 'sponsorshipStatus',
                    'person_type_of_guarantee' => 'guaranteeType'
                ]
            ]
        ],

        'deceased' => [
            'model' => \App\Models\DeadPepole::class,
            'name' => 'المتوفين',
            'fields' => [
                'text_search' => [
                    'father_first_name',
                    'father_second_name',
                    'father_third_name',
                    'father_last_name',
                    'father_id',
                    'mother_first_name',
                    'mother_second_name',
                    'mother_third_name',
                    'mother_last_name',
                    'mother_id',
                    're_file_id'
                ],
                'exact_match' => [
                    'father_id',
                    'mother_id',
                    're_file_id'
                ],
                'date_fields' => [
                    'father_death_date',
                    'mother_death_date'
                ],
                'relationship_fields' => [
                    'father_death_reason' => 'fatherDeathReason',
                    'mother_death_reason' => 'motherDeathReason'
                ]
            ]
        ]
    ],

    // فهارس قاعدة البيانات المطلوبة لتحسين الأداء
    'recommended_indexes' => [
        'data' => [
            'data_first_name',
            'data_id_number',
            'file_id_number',
            'data_phone_number',
            'data_birth_date',
            'data_request_status',
            ['data_first_name', 'data_father_name'], // فهرس مركب
            ['file_id_number', 'data_request_status'] // فهرس مركب
        ],
        're_people' => [
            'first_name',
            'person_id',
            'registration_id',
            'person_birth_date',
            ['registration_id', 'person_id'] // فهرس مركب
        ],
        'dead_people' => [
            'father_id',
            'mother_id',
            're_file_id',
            'father_death_date',
            'mother_death_date'
        ]
    ],

    // إعدادات التصدير
    'export' => [
        'max_records' => 10000,
        'formats' => ['excel', 'csv', 'pdf'],
        'filename_format' => 'search_results_{type}_{date}'
    ],

    // إعدادات البحث المتقدم
    'advanced_search' => [
        'enable_fuzzy_search' => true, // البحث الضبابي
        'enable_phonetic_search' => false, // البحث الصوتي
        'enable_autocomplete' => true, // الإكمال التلقائي
        'autocomplete_min_length' => 2,
        'autocomplete_max_results' => 10
    ],

    // إعدادات الأمان
    'security' => [
        'log_searches' => true,
        'encrypt_sensitive_logs' => true,
        'allowed_roles' => ['admin', 'supervisor', 'data_entry'],
        'restrict_sensitive_fields' => true
    ]
];
