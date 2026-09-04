<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Folder Duplicate Detection Settings
    |--------------------------------------------------------------------------
    |
    | Configuration options for the folder-based duplicate file detection system
    |
    */

    // Storage settings
    'storage' => [
        'temp_path' => storage_path('app/public/temp/duplicates'),
        'max_file_size' => 50 * 1024 * 1024, // 50MB
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'heic', 'heif', 'pdf', 'doc', 'docx', 'xlsx', 'xls', 'csv'],
    ],

    // Session settings
    'session' => [
        'expire_days' => 7,
        'cleanup_interval' => 'daily',
        'max_files_per_session' => 1000,
        'auto_cleanup' => true,
    ],

    // Detection settings
    'detection' => [
        'similarity_threshold' => 0.95, // 95% similarity for fuzzy matching
        'normalize_names' => true,
        'case_sensitive' => false,
        'ignore_extensions' => false,
        'check_file_hash' => true, // Enable MD5 hash checking
        'check_file_size' => true, // Enable file size checking
    ],

    // Folder validation settings
    'folder_validation' => [
        'identity_pattern' => '/^\d{8,10}$/', // Pattern for identity folder names
        'validate_against_database' => true,
        'database_tables' => ['data'], // Tables to check for folder validation
        'database_columns' => ['data_id_number', 'file_id_number'], // Columns to check
    ],

    // Performance settings
    'performance' => [
        'batch_size' => 100,
        'max_concurrent_sessions' => 10,
        'memory_limit' => '512M',
        'max_execution_time' => 3600, // 1 hour
    ],

    // Logging settings
    'logging' => [
        'enabled' => true,
        'level' => 'info',
        'detailed_errors' => true,
        'log_duplicate_files' => true,
        'log_folder_analysis' => true,
    ],

    // Default handling options
    'defaults' => [
        'duplicate_handling' => 'store_temp', // store_temp, skip, replace
        'auto_cleanup' => true,
        'notification_enabled' => true,
        'create_backup' => false, // Create backup before replacing duplicates
    ],

    // File naming patterns for normalization
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

            // Common replacements
            ' ' => '_',
            '-' => '_',
            '(' => '',
            ')' => '',
            '[' => '',
            ']' => '',
            '{' => '',
            '}' => '',
        ],
    ],

    // UI settings
    'ui' => [
        'show_progress' => true,
        'show_statistics' => true,
        'show_folder_analysis' => true,
        'auto_download_duplicates' => false,
        'confirm_before_delete' => true,
    ],

    // Integration settings
    'integration' => [
        'enhanced_attachments_table' => true, // Use enhanced_attachments table
        'fallback_to_attachments' => true, // Fallback to regular attachments table
        'cloud_sync_duplicates' => false, // Sync duplicate detection with cloud
        'webhook_notifications' => false, // Send webhook notifications for duplicates
    ],
];
