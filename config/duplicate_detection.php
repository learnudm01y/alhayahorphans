<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Duplicate File Detection Settings
    |--------------------------------------------------------------------------
    |
    | Configuration options for the duplicate file detection system
    |
    */

    // Storage settings
    'storage' => [
        'temp_path' => storage_path('app/temp'),
        'max_file_size' => 50 * 1024 * 1024, // 50MB
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xlsx', 'xls'],
    ],

    // Session settings
    'session' => [
        'expire_days' => 7,
        'cleanup_interval' => 'daily',
        'max_files_per_session' => 1000,
    ],

    // Detection settings
    'detection' => [
        'similarity_threshold' => 0.95, // 95% similarity for fuzzy matching
        'normalize_names' => true,
        'case_sensitive' => false,
        'ignore_extensions' => false,
    ],

    // Performance settings
    'performance' => [
        'batch_size' => 100,
        'max_concurrent_sessions' => 10,
        'memory_limit' => '512M',
    ],

    // Logging settings
    'logging' => [
        'enabled' => true,
        'level' => 'info',
        'detailed_errors' => true,
    ],

    // Default handling options
    'defaults' => [
        'duplicate_handling' => 'store_temp', // store_temp, skip, replace
        'auto_cleanup' => true,
        'notification_enabled' => true,
    ],

    // File naming patterns
    'naming' => [
        'patterns' => [
            // Common patterns to normalize
            'spaces' => '_',
            'special_chars' => '',
            'multiple_dots' => '.',
            'multiple_underscores' => '_',
        ],
        'replacements' => [
            // Arabic to English numbers
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ],
    ],

    // UI settings
    'ui' => [
        'show_duplicate_modal' => true,
        'auto_show_results' => true,
        'download_zip_enabled' => true,
        'bulk_delete_enabled' => true,
    ],
];
