<?php

declare(strict_types=1);

/**
 * Queue Configuration
 * 
 * Configuration for the database queue system
 */
return [
    /**
     * Default queue connection
     */
    'default' => env('QUEUE_CONNECTION', 'database'),
    
    /**
     * Queue processing token (for HTTP endpoint security)
     * 
     * Generate a secure token: openssl rand -hex 32
     */
    'token' => env('QUEUE_TOKEN', null),
    
    /**
     * Piggyback processing settings
     * 
     * Process queue jobs after each HTTP request
     */
    'piggyback' => [
        'enabled' => env('QUEUE_PIGGYBACK', true),
        'max_jobs' => 2,
        'max_seconds' => 3,
    ],
    
    /**
     * Retry settings
     */
    'retry' => [
        'times' => 3,
        'delay' => 60, // seconds
    ],
];
