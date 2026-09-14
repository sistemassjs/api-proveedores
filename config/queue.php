<?php


return [
    'default' => env('QUEUE_CONNECTION', 'redis'),

    'connections' => [
        // 'redis' => [
        //     'driver' => 'redis',
        //     'connection' => 'default',
        //     'queue' => env('REDIS_QUEUE', 'default'),
        //     'retry_after' => 90,
        //     'block_for' => null,
        //     'after_commit' => false,
        // ],

        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            // Importaciones masivas (~50k) pueden superar 30 min; alinear con CSVImportJob::$timeout
            'retry_after' => (int) env('QUEUE_RETRY_AFTER', 1900),
            'after_commit' => false,
        ],
    ],

    'batching' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'job_batches',
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],
];
