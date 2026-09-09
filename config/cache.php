<?php

use Illuminate\Support\Str;

return [
    'default' => env('CACHE_STORE', env('CACHE_DRIVER', 'file')),

    'stores' => [
        // 'redis' => [
        //     'driver' => 'redis',
        //     'connection' => 'cache',
        //     'lock_connection' => 'default',
        // ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],
    ],
    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_') . '_cache_'),
];
