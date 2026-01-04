<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

use Conduit\RateLimiter\RateLimiter;

class RateLimitClearCommand extends Command
{
    protected string $name = 'ratelimit:clear';
    protected string $description = 'Clear expired rate limit entries';
    
    public function handle(): int
    {
        $limiter = app(RateLimiter::class);
        $limiter->cleanup();
        
        $this->success('Rate limit cache cleared successfully.');
        return 0;
    }
}
