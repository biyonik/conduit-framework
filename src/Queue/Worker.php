<?php

declare(strict_types=1);

namespace Conduit\Queue;

use Conduit\Queue\Exceptions\MaxAttemptsExceededException;

class Worker
{
    protected DatabaseQueue $queue;
    protected bool $shouldQuit = false;
    
    public function __construct(DatabaseQueue $queue)
    {
        $this->queue = $queue;
    }
    
    public function work(string $queue = 'default', int $limit = 0, int $timeout = 0): int
    {
        $processed = 0;
        $startTime = time();
        
        while (!$this->shouldQuit) {
            if ($timeout > 0 && (time() - $startTime) >= $timeout) {
                break;
            }
            
            if ($limit > 0 && $processed >= $limit) {
                break;
            }
            
            $jobRecord = $this->queue->pop($queue);
            
            if ($jobRecord === null) {
                if ($limit > 0) {
                    break;
                }
                usleep(500000);
                continue;
            }
            
            $this->process($jobRecord);
            $processed++;
        }
        
        return $processed;
    }
    
    protected function process(array $jobRecord): void
    {
        try {
            $job = $this->queue->unserializeJob($jobRecord['payload']);
            $job->attempts = $jobRecord['attempts'];
            $job->jobId = $jobRecord['id'];
            
            if ($job->attempts > $job->tries()) {
                throw new MaxAttemptsExceededException(
                    "Job exceeded max attempts: " . get_class($job)
                );
            }
            
            $job->handle();
            $this->queue->delete($jobRecord['id']);
            
        } catch (MaxAttemptsExceededException $e) {
            $job = $this->queue->unserializeJob($jobRecord['payload']);
            $job->failed($e);
            $this->queue->fail($jobRecord, $e);
            
        } catch (\Throwable $e) {
            $job = $this->queue->unserializeJob($jobRecord['payload']);
            
            if ($jobRecord['attempts'] >= $job->tries()) {
                $job->failed($e);
                $this->queue->fail($jobRecord, $e);
            } else {
                $this->queue->release($jobRecord['id'], $job->retryAfter());
            }
        }
    }
    
    public function processOnRequest(int $maxJobs = 1, int $maxSeconds = 2, ?string $queue = null): int
    {
        return $this->work(
            queue: $queue ?? 'default',
            limit: $maxJobs,
            timeout: $maxSeconds
        );
    }
    
    public function stop(): void
    {
        $this->shouldQuit = true;
    }
}
