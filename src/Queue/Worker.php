<?php

declare(strict_types=1);

namespace Conduit\Queue;

use Conduit\Queue\Exceptions\MaxAttemptsExceededException;

/**
 * Queue Worker
 * 
 * Processes jobs from the queue
 */
class Worker
{
    protected DatabaseQueue $queue;
    protected bool $shouldQuit = false;
    
    public function __construct(DatabaseQueue $queue)
    {
        $this->queue = $queue;
    }
    
    /**
     * Process jobs from the queue
     * 
     * @param string $queue Queue name
     * @param int $limit Max jobs to process (0 = unlimited)
     * @param int $timeout Max seconds to run (0 = unlimited)
     * @return int Number of jobs processed
     */
    public function work(
        string $queue = 'default',
        int $limit = 0,
        int $timeout = 0
    ): int {
        $processed = 0;
        $startTime = time();
        
        while (!$this->shouldQuit) {
            // Check timeout
            if ($timeout > 0 && (time() - $startTime) >= $timeout) {
                break;
            }
            
            // Check limit
            if ($limit > 0 && $processed >= $limit) {
                break;
            }
            
            // Get next job
            $jobRecord = $this->queue->pop($queue);
            
            if ($jobRecord === null) {
                // No jobs available
                if ($limit > 0) {
                    break; // Batch mode - exit when empty
                }
                
                // Daemon mode - sleep and retry
                usleep(500000); // 0.5 seconds
                continue;
            }
            
            $this->process($jobRecord);
            $processed++;
        }
        
        return $processed;
    }
    
    /**
     * Process a single job
     * 
     * @param array $jobRecord
     * @return void
     */
    protected function process(array $jobRecord): void
    {
        try {
            $job = $this->queue->unserializeJob($jobRecord['payload']);
            $job->attempts = $jobRecord['attempts'];
            $job->jobId = $jobRecord['id'];
            
            // Check max attempts
            if ($job->attempts >= $job->tries()) {
                throw new MaxAttemptsExceededException(
                    "Job exceeded max attempts: " . get_class($job)
                );
            }
            
            // Execute the job
            $job->handle();
            
            // Success - delete from queue
            $this->queue->delete($jobRecord['id']);
            
        } catch (MaxAttemptsExceededException $e) {
            // Max attempts reached - move to failed
            $job = $this->queue->unserializeJob($jobRecord['payload']);
            $job->failed($e);
            $this->queue->fail($jobRecord, $e);
            
        } catch (\Throwable $e) {
            // Job failed - release back to queue with delay
            $job = $this->queue->unserializeJob($jobRecord['payload']);
            
            if ($jobRecord['attempts'] >= $job->tries()) {
                // Max attempts reached
                $job->failed($e);
                $this->queue->fail($jobRecord, $e);
            } else {
                // Retry later
                $this->queue->release($jobRecord['id'], $job->retryAfter());
            }
        }
    }
    
    /**
     * Process jobs on request (piggyback processing)
     * 
     * Lightweight processing for use in middleware
     * 
     * @param int $maxJobs
     * @param int $maxSeconds
     * @param string|null $queue
     * @return int Number of jobs processed
     */
    public function processOnRequest(
        int $maxJobs = 1,
        int $maxSeconds = 2,
        ?string $queue = null
    ): int {
        return $this->work(
            queue: $queue ?? 'default',
            limit: $maxJobs,
            timeout: $maxSeconds
        );
    }
    
    /**
     * Stop the worker
     * 
     * @return void
     */
    public function stop(): void
    {
        $this->shouldQuit = true;
    }
}
