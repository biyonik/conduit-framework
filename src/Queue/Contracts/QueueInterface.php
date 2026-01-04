<?php

declare(strict_types=1);

namespace Conduit\Queue\Contracts;

use Conduit\Queue\Job;

interface QueueInterface
{
    public function push(Job $job): int;
    public function later(int $delay, Job $job): int;
    public function pop(?string $queue = null): ?array;
    public function delete(int $jobId): void;
    public function release(int $jobId, int $delay = 0): void;
    public function fail(array $job, \Throwable $exception): void;
    public function size(?string $queue = null): int;
}
