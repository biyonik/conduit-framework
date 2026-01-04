<?php

declare(strict_types=1);

namespace Conduit\Queue;

use Conduit\Queue\Contracts\JobInterface;
use Conduit\Queue\Contracts\ShouldQueue;

/**
 * Base Job Class
 * 
 * Abstract base class for all queueable jobs.
 * Provides default implementation and helper methods.
 */
abstract class Job implements JobInterface, ShouldQueue
{
    /**
     * The queue name
     */
    public string $queue = 'default';
    
    /**
     * Delay in seconds before processing
     */
    public int $delay = 0;
    
    /**
     * Number of times to attempt the job
     */
    public int $tries = 3;
    
    /**
     * Seconds to wait before retrying
     */
    public int $retryAfter = 60;
    
    /**
     * Current attempt number
     */
    public int $attempts = 0;
    
    /**
     * Job ID (set when queued)
     */
    public ?int $jobId = null;
    
    /**
     * Execute the job
     * 
     * @return void
     */
    abstract public function handle(): void;
    
    /**
     * Get the number of tries
     * 
     * @return int
     */
    public function tries(): int
    {
        return $this->tries;
    }
    
    /**
     * Get retry delay
     * 
     * @return int
     */
    public function retryAfter(): int
    {
        return $this->retryAfter;
    }
    
    /**
     * Handle failure (override in child class)
     * 
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        // Log failure by default
        error_log("Job failed: " . get_class($this) . " - " . $exception->getMessage());
    }
    
    /**
     * Set the queue name
     * 
     * @param string $queue Queue name
     * @return self
     */
    public function onQueue(string $queue): self
    {
        $this->queue = $queue;
        return $this;
    }
    
    /**
     * Set the delay
     * 
     * @param int $seconds Delay in seconds
     * @return self
     */
    public function delay(int $seconds): self
    {
        $this->delay = $seconds;
        return $this;
    }
    
    /**
     * Dispatch the job to the queue
     * 
     * @param mixed ...$args Constructor arguments
     * @return static
     */
    public static function dispatch(...$args): static
    {
        $job = new static(...$args);
        app(QueueManager::class)->push($job);
        return $job;
    }
    
    /**
     * Dispatch with delay
     * 
     * @param int $seconds Delay in seconds
     * @param mixed ...$args Constructor arguments
     * @return static
     */
    public static function dispatchAfter(int $seconds, ...$args): static
    {
        $job = new static(...$args);
        $job->delay($seconds);
        app(QueueManager::class)->push($job);
        return $job;
    }
}
