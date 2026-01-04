<?php

declare(strict_types=1);

namespace Conduit\RateLimiter;

use Conduit\RateLimiter\Contracts\RateLimiterInterface;
use Conduit\RateLimiter\Contracts\LimiterStorageInterface;

class RateLimiter implements RateLimiterInterface
{
    protected LimiterStorageInterface $storage;
    
    public function __construct(LimiterStorageInterface $storage)
    {
        $this->storage = $storage;
    }
    
    public function attempt(string $key, int $maxAttempts = 60, int $decaySeconds = 60): bool
    {
        if ($this->tooManyAttempts($key, $maxAttempts)) {
            return false;
        }
        
        $this->hit($key, $decaySeconds);
        
        return true;
    }
    
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->attempts($key) >= $maxAttempts;
    }
    
    public function attempts(string $key): int
    {
        $data = $this->storage->get($key);
        return $data['attempts'] ?? 0;
    }
    
    public function remaining(string $key, int $maxAttempts): int
    {
        $attempts = $this->attempts($key);
        return max(0, $maxAttempts - $attempts);
    }
    
    public function availableIn(string $key): int
    {
        $data = $this->storage->get($key);
        
        if (!$data) {
            return 0;
        }
        
        return max(0, $data['expires_at'] - time());
    }
    
    public function clear(string $key): void
    {
        $this->storage->forget($key);
    }
    
    public function hit(string $key, int $decaySeconds = 60): int
    {
        return $this->storage->increment($key, $decaySeconds);
    }
    
    public function retryAfter(string $key): ?int
    {
        $data = $this->storage->get($key);
        return $data['expires_at'] ?? null;
    }
    
    public function cleanup(): void
    {
        $this->storage->cleanup();
    }
}
