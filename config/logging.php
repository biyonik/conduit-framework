<?php

declare(strict_types=1);

return [
    'default' => env('LOG_CHANNEL', 'daily'),

    'channels' => [
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/conduit.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Conduit Log',
            'emoji' => ':boom:',
            'level' => env('LOG_LEVEL', 'critical'),
        ],

        'stderr' => [
            'driver' => 'monolog',
            'handler' => 'StreamHandler',
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],
    ],
];
