<?php

declare(strict_types=1);

namespace Conduit\RateLimiter\Contracts;

use Conduit\Http\Request;

interface KeyGeneratorInterface
{
    /**
     * Generate a unique key for rate limiting
     * 
     * @param Request $request
     * @param string $prefix Optional prefix for the key
     * @return string
     */
    public function generate(Request $request, string $prefix = ''): string;
}
