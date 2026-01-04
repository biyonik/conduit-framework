<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Limiter Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "file", "database"
    |
    */
    'driver' => env('RATELIMITER_DRIVER', 'file'),
    
    /*
    |--------------------------------------------------------------------------
    | Default Rate Limits
    |--------------------------------------------------------------------------
    */
    'default' => [
        'max_attempts' => env('RATELIMIT_MAX_ATTEMPTS', 60),
        'decay_minutes' => env('RATELIMIT_DECAY_MINUTES', 1),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | API Rate Limits
    |--------------------------------------------------------------------------
    */
    'api' => [
        'max_attempts' => env('RATELIMIT_API_MAX_ATTEMPTS', 60),
        'decay_minutes' => env('RATELIMIT_API_DECAY_MINUTES', 1),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Authentication Rate Limits (login, register)
    |--------------------------------------------------------------------------
    */
    'auth' => [
        'max_attempts' => env('RATELIMIT_AUTH_MAX_ATTEMPTS', 5),
        'decay_minutes' => env('RATELIMIT_AUTH_DECAY_MINUTES', 1),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Cleanup Settings
    |--------------------------------------------------------------------------
    */
    'cleanup' => [
        'enabled' => true,
        'probability' => 2, // 2% chance on each request
    ],
];
