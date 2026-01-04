<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection
    |--------------------------------------------------------------------------
    |
    | This option controls the default queue connection that gets used when
    | writing to queues.
    |
    */
    'default' => env('QUEUE_CONNECTION', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Queue Token
    |--------------------------------------------------------------------------
    |
    | This token is used to authenticate requests to the queue HTTP endpoints.
    | Used for external cron services like cron-job.org
    |
    */
    'token' => env('QUEUE_TOKEN', null),

    /*
    |--------------------------------------------------------------------------
    | Piggyback Processing
    |--------------------------------------------------------------------------
    |
    | When enabled, queue jobs are processed automatically after each HTTP
    | request. This is useful for shared hosting without supervisord.
    |
    */
    'piggyback' => [
        'enabled' => env('QUEUE_PIGGYBACK', true),
        'max_jobs' => 2,
        'max_seconds' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Default retry configuration for failed jobs.
    |
    */
    'retry' => [
        'times' => 3,
        'delay' => 60,
    ],
];
