<?php

declare(strict_types=1);

namespace Conduit\RateLimiter\Exceptions;

use Exception;

class TooManyRequestsException extends Exception
{
    protected int $retryAfter;
    protected int $maxAttempts;
    
    public function __construct(
        int $retryAfter, 
        int $maxAttempts = 60, 
        string $message = 'Too Many Requests'
    ) {
        parent::__construct($message, 429);
        $this->retryAfter = $retryAfter;
        $this->maxAttempts = $maxAttempts;
    }
    
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
    
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }
    
    public function getHeaders(): array
    {
        return [
            'X-RateLimit-Limit' => $this->maxAttempts,
            'X-RateLimit-Remaining' => 0,
            'Retry-After' => $this->retryAfter,
            'X-RateLimit-Reset' => time() + $this->retryAfter,
        ];
    }
}
