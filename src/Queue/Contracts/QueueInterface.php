<?php

declare(strict_types=1);

namespace Conduit\Queue\Contracts;

use Conduit\Queue\Job;

/**
 * Queue Interface
 * 
 * Defines the contract for queue implementations
 */
interface QueueInterface
{
    /**
     * Push a job onto the queue
     * 
     * @param Job $job The job to queue
     * @return int Job ID
     */
    public function push(Job $job): int;
    
    /**
     * Push a job with delay
     * 
     * @param int $delay Delay in seconds
     * @param Job $job The job to queue
     * @return int Job ID
     */
    public function later(int $delay, Job $job): int;
    
    /**
     * Pop the next job from the queue
     * 
     * @param string|null $queue Queue name
     * @return array|null Job record or null if queue is empty
     */
    public function pop(?string $queue = null): ?array;
    
    /**
     * Delete a job from the queue
     * 
     * @param int $jobId Job ID
     * @return void
     */
    public function delete(int $jobId): void;
    
    /**
     * Release a job back onto the queue
     * 
     * @param int $jobId Job ID
     * @param int $delay Delay in seconds before retry
     * @return void
     */
    public function release(int $jobId, int $delay = 0): void;
    
    /**
     * Mark a job as failed
     * 
     * @param array $job Job record
     * @param \Throwable $exception Exception that caused the failure
     * @return void
     */
    public function fail(array $job, \Throwable $exception): void;
    
    /**
     * Get the size of the queue
     * 
     * @param string|null $queue Queue name
     * @return int Number of jobs
     */
    public function size(?string $queue = null): int;
}
