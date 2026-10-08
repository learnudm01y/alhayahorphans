<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'rclone' => [
        'enabled' => env('USE_RCLONE_FOR_UPLOADS', false),
        'local_storage' => env('USE_LOCAL_STORAGE_FOR_UPLOADS', false),
        'path' => env('RCLONE_PATH', '/usr/bin/rclone'),
        'config' => env('RCLONE_CONFIG', null),
        'remote_name' => env('RCLONE_REMOTE_NAME', 'alhayahorphans'),
        'root_folder' => env('RCLONE_ROOT_FOLDER', 'temp'),
    ],

    'google' => [
        'use_rclone' => env('USE_RCLONE_FOR_UPLOADS', false),
        'rclone_remote_name' => env('RCLONE_REMOTE_NAME', 'alhayahorphans'),
        'rclone_root_folder' => env('RCLONE_ROOT_FOLDER', 'temp'),
        'general_registration_parent_id' => env('GOOGLE_DRIVE_GENERAL_REGISTRATION_PARENT_ID'),
        'drive_folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),
    ],

    // android-v4 Phase 7 — Staged Cutover flag (append-only; no existing keys touched)
    'sync_v4' => [
        'legacy_sync_enabled' => env('LEGACY_SYNC_ENABLED', true), // safe default = dual-run
    ],

    'nsms' => [
        'base_url'     => env('NSMS_BASE_URL'),
        'token'        => env('NSMS_TOKEN'),
        'sender'       => env('NSMS_SENDER'),
        'country_code' => env('NSMS_COUNTRY_CODE', '970'),
    ],

    'whatsapp' => [
        'base_url' => env('WHATSAPP_API_URL', 'http://localhost:8080'),
        'api_key'  => env('WHATSAPP_API_KEY'),
        'instance' => env('WHATSAPP_INSTANCE'),
    ],

    'gemini' => [
        'key'   => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

];
