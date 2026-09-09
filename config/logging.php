<?php


return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single', 'audit'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'audit' => [
            'driver' => 'single',
            'path' => storage_path('logs/audit.log'),
            'level' => 'info',
            'replace_placeholders' => true,
        ],

        'security' => [
            'driver' => 'single',
            'path' => storage_path('logs/security.log'),
            'level' => 'warning',
            'replace_placeholders' => true,
        ],

        'performance' => [
            'driver' => 'single',
            'path' => storage_path('logs/performance.log'),
            'level' => 'info',
            'replace_placeholders' => true,
        ],

        'requests' => [
            'driver' => 'single',
            'path' => storage_path('logs/requests.log'),
            'level' => 'info',
            'replace_placeholders' => true,
        ],

        'inter_api' => [
            'driver' => 'single',
            'path' => storage_path('logs/inter_api.log'),
            'level' => 'debug',
            'replace_placeholders' => true,
        ],

        'fcm_unregistered' => [
            'driver' => 'single',
            'path' => storage_path('logs/fcm_unregistered.log'),
            'level' => 'info',
            'replace_placeholders' => true,
        ],

        'migrations' => [
            'driver' => 'single',
            'path' => storage_path('logs/migrations.log'),
            'level' => 'info',
            'replace_placeholders' => true,
        ],
    ],
];
