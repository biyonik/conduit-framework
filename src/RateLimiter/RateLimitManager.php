<?php

declare(strict_types=1);

namespace Conduit\RateLimiter;

use Conduit\RateLimiter\Contracts\RateLimiterInterface;

/**
 * Rate Limit Manager
 * 
 * Manages multiple rate limiters with different configurations
 */
class RateLimitManager
{
    protected RateLimiterInterface $limiter;
    
    /**
     * @var array<string, array>
     */
    protected array $limiters = [];
    
    public function __construct(RateLimiterInterface $limiter)
    {
        $this->limiter = $limiter;
    }
    
    /**
     * Define a rate limiter with specific configuration
     */
    public function for(string $name, int $maxAttempts, int $decayMinutes): self
    {
        $this->limiters[$name] = [
            'max_attempts' => $maxAttempts,
            'decay_minutes' => $decayMinutes,
        ];
        
        return $this;
    }
    
    /**
     * Get configuration for a named limiter
     */
    public function getLimiter(string $name): ?array
    {
        return $this->limiters[$name] ?? null;
    }
    
    /**
     * Get the underlying rate limiter instance
     */
    public function limiter(): RateLimiterInterface
    {
        return $this->limiter;
    }
}
