<?php

declare(strict_types=1);

namespace Conduit\Http\Middleware;

use Conduit\RateLimiter\Middleware\ThrottleMiddleware as BaseThrottleMiddleware;

/**
 * Alias for ThrottleMiddleware for backward compatibility
 */
class ThrottleRequests extends BaseThrottleMiddleware
{
}
