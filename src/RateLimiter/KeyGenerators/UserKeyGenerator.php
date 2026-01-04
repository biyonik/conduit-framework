<?php

declare(strict_types=1);

namespace Conduit\RateLimiter\KeyGenerators;

use Conduit\Http\Request;
use Conduit\RateLimiter\Contracts\KeyGeneratorInterface;

class UserKeyGenerator implements KeyGeneratorInterface
{
    public function generate(Request $request, string $prefix = ''): string
    {
        // Use authenticated user ID if available, fallback to IP
        $identifier = $request->getAttribute('user')?->id ?? $request->ip();
        $routeKey = $request->method() . '|' . $request->path();
        
        return $prefix . sha1($identifier . '|' . $routeKey);
    }
}
