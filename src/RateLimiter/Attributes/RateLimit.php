<?php

declare(strict_types=1);

namespace Conduit\RateLimiter\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class RateLimit
{
    public function __construct(
        public int $maxAttempts = 60,
        public int $decayMinutes = 1,
        public string $prefix = '',
        public ?string $key = null
    ) {}
}
