<?php

declare(strict_types=1);

namespace Conduit\RateLimiter\Contracts;

interface RateLimiterInterface
{
    /**
     * Attempt to hit the rate limiter
     * 
     * @param string $key The unique key for this limiter
     * @param int $maxAttempts Maximum number of attempts
     * @param int $decaySeconds Time window in seconds
     * @return bool True if attempt is allowed, false if rate limited
     */
    public function attempt(string $key, int $maxAttempts = 60, int $decaySeconds = 60): bool;
    
    /**
     * Check if too many attempts have been made
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool;
    
    /**
     * Get the number of attempts for the given key
     */
    public function attempts(string $key): int;
    
    /**
     * Get remaining attempts
     */
    public function remaining(string $key, int $maxAttempts): int;
    
    /**
     * Get the number of seconds until the rate limit resets
     */
    public function availableIn(string $key): int;
    
    /**
     * Reset the attempts for the given key
     */
    public function clear(string $key): void;
    
    /**
     * Increment the attempts for the given key
     */
    public function hit(string $key, int $decaySeconds = 60): int;
    
    /**
     * Get the retry after timestamp
     */
    public function retryAfter(string $key): ?int;
}
