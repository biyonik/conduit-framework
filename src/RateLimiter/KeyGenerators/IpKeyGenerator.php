<?php

declare(strict_types=1);

namespace Conduit\RateLimiter\KeyGenerators;

use Conduit\Http\Request;
use Conduit\RateLimiter\Contracts\KeyGeneratorInterface;

class IpKeyGenerator implements KeyGeneratorInterface
{
    public function generate(Request $request, string $prefix = ''): string
    {
        $ip = $request->ip();
        $routeKey = $request->method() . '|' . $request->path();
        
        return $prefix . sha1($ip . '|' . $routeKey);
    }
}
