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
        'path' => env('RCLONE_PATH', '/usr/bin/rclone'),
        'config' => env('RCLONE_CONFIG', null),
        'remote_name' => env('RCLONE_REMOTE_NAME', 'alhayahorphans'),
        'root_folder' => env('RCLONE_ROOT_FOLDER', 'temp'),
    ],

];
