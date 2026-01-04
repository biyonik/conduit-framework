<?php

declare(strict_types=1);

namespace Conduit\Queue;

use Conduit\Queue\Contracts\JobInterface;
use Conduit\Queue\Contracts\ShouldQueue;

abstract class Job implements JobInterface, ShouldQueue
{
    public string $queue = 'default';
    public int $delay = 0;
    public int $tries = 3;
    public int $retryAfter = 60;
    public int $attempts = 0;
    public ?int $jobId = null;
    
    abstract public function handle(): void;
    
    public function tries(): int
    {
        return $this->tries;
    }
    
    public function retryAfter(): int
    {
        return $this->retryAfter;
    }
    
    public function failed(\Throwable $exception): void
    {
        error_log("Job failed: " . get_class($this) . " - " . $exception->getMessage());
    }
    
    public function onQueue(string $queue): self
    {
        $this->queue = $queue;
        return $this;
    }
    
    public function delay(int $seconds): self
    {
        $this->delay = $seconds;
        return $this;
    }
    
    public static function dispatch(...$args): static
    {
        $job = new static(...$args);
        app(QueueManager::class)->push($job);
        return $job;
    }
    
    public static function dispatchAfter(int $seconds, ...$args): static
    {
        $job = new static(...$args);
        $job->delay($seconds);
        app(QueueManager::class)->push($job);
        return $job;
    }
}
