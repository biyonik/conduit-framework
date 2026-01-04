<?php

declare(strict_types=1);

namespace Conduit\RateLimiter\Contracts;

interface LimiterStorageInterface
{
    /**
     * Get the current hit count for a key
     */
    public function get(string $key): ?array;
    
    /**
     * Increment the hit count
     * Returns the new count
     */
    public function increment(string $key, int $decaySeconds): int;
    
    /**
     * Reset/delete the key
     */
    public function forget(string $key): void;
    
    /**
     * Clean up expired entries
     */
    public function cleanup(): void;
}
