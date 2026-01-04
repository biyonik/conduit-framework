<?php

declare(strict_types=1);

namespace Conduit\Queue\Contracts;

interface JobInterface
{
    public function handle(): void;
    public function tries(): int;
    public function retryAfter(): int;
    public function failed(\Throwable $exception): void;
}
