<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Speedtest CLI Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for Ookla Speedtest CLI integration
    |
    */

    // Timeout for speedtest execution (seconds)
    'timeout' => env('SPEEDTEST_TIMEOUT', 120),

    // Maximum number of attempts before falling back
    'max_attempts' => env('SPEEDTEST_MAX_ATTEMPTS', 3),

    // Enable result caching
    'cache_results' => env('SPEEDTEST_CACHE_ENABLED', true),

    // Cache duration in seconds (5 minutes)
    'cache_duration' => env('SPEEDTEST_CACHE_DURATION', 300),

    // Speedtest CLI path (leave empty for auto-detection)
    'cli_path' => env('SPEEDTEST_CLI_PATH', null),

    // Speedtest server selection
    'server_id' => env('SPEEDTEST_SERVER_ID', null), // Leave null for auto-selection

    // Network interface to use (leave null for default)
    'interface' => env('SPEEDTEST_INTERFACE', null),

    // Enable verbose logging
    'verbose_logging' => env('SPEEDTEST_VERBOSE_LOGGING', false),

    // Minimum values for fallback simulation
    'fallback' => [
        'download_min' => env('SPEEDTEST_FALLBACK_DOWNLOAD_MIN', 10),
        'download_max' => env('SPEEDTEST_FALLBACK_DOWNLOAD_MAX', 100),
        'upload_min' => env('SPEEDTEST_FALLBACK_UPLOAD_MIN', 5),
        'upload_max' => env('SPEEDTEST_FALLBACK_UPLOAD_MAX', 50),
        'ping_min' => env('SPEEDTEST_FALLBACK_PING_MIN', 10),
        'ping_max' => env('SPEEDTEST_FALLBACK_PING_MAX', 100),
    ],

    // Rate limiting
    'rate_limit' => [
        'enabled' => env('SPEEDTEST_RATE_LIMIT_ENABLED', true),
        'max_requests' => env('SPEEDTEST_RATE_LIMIT_MAX_REQUESTS', 5),
        'per_minutes' => env('SPEEDTEST_RATE_LIMIT_PER_MINUTES', 60),
    ],

    // Security settings
    'security' => [
        'allowed_ips' => env('SPEEDTEST_ALLOWED_IPS', null), // Comma-separated IPs
        'require_auth' => env('SPEEDTEST_REQUIRE_AUTH', false),
        'max_concurrent_tests' => env('SPEEDTEST_MAX_CONCURRENT_TESTS', 3),
    ],

    // Results storage
    'store_results' => env('SPEEDTEST_STORE_RESULTS', false),
    'results_table' => 'speedtest_results',
    'cleanup_after_days' => env('SPEEDTEST_CLEANUP_AFTER_DAYS', 30),
];
