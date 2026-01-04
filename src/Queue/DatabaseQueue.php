<?php

declare(strict_types=1);

namespace Conduit\Queue;

use Conduit\Database\Connection;
use Conduit\Queue\Contracts\QueueInterface;

/**
 * Database Queue Implementation
 * 
 * Stores jobs in a database table for shared hosting compatibility
 */
class DatabaseQueue implements QueueInterface
{
    protected Connection $db;
    protected string $table = 'jobs';
    protected string $failedTable = 'failed_jobs';
    
    public function __construct(Connection $db)
    {
        $this->db = $db;
    }
    
    /**
     * Push a job onto the queue
     * 
     * @param Job $job
     * @return int Job ID
     */
    public function push(Job $job): int
    {
        $payload = $this->createPayload($job);
        $availableAt = time() + $job->delay;
        
        $this->db->table($this->table)->insert([
            'queue' => $job->queue,
            'payload' => $payload,
            'attempts' => 0,
            'available_at' => $availableAt,
            'created_at' => time(),
            'reserved_at' => null,
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Push a job with delay
     * 
     * @param int $delay
     * @param Job $job
     * @return int Job ID
     */
    public function later(int $delay, Job $job): int
    {
        $job->delay = $delay;
        return $this->push($job);
    }
    
    /**
     * Pop the next job from the queue
     * 
     * @param string|null $queue
     * @return array|null
     */
    public function pop(?string $queue = null): ?array
    {
        $queue = $queue ?? 'default';
        
        // Find available job
        $job = $this->db->table($this->table)
            ->where('queue', $queue)
            ->where('available_at', '<=', time())
            ->whereNull('reserved_at')
            ->orderBy('id', 'asc')
            ->first();
        
        if (!$job) {
            return null;
        }
        
        // Reserve the job (atomic update)
        $affected = $this->db->table($this->table)
            ->where('id', $job['id'])
            ->whereNull('reserved_at')
            ->update([
                'reserved_at' => time(),
                'attempts' => $job['attempts'] + 1,
            ]);
        
        // Someone else grabbed it
        if ($affected === 0) {
            return $this->pop($queue);
        }
        
        $job['attempts'] = $job['attempts'] + 1;
        
        return $job;
    }
    
    /**
     * Delete a job from the queue
     * 
     * @param int $jobId
     * @return void
     */
    public function delete(int $jobId): void
    {
        $this->db->table($this->table)
            ->where('id', $jobId)
            ->delete();
    }
    
    /**
     * Release a job back onto the queue
     * 
     * @param int $jobId
     * @param int $delay
     * @return void
     */
    public function release(int $jobId, int $delay = 0): void
    {
        $this->db->table($this->table)
            ->where('id', $jobId)
            ->update([
                'reserved_at' => null,
                'available_at' => time() + $delay,
            ]);
    }
    
    /**
     * Mark a job as failed
     * 
     * @param array $job
     * @param \Throwable $exception
     * @return void
     */
    public function fail(array $job, \Throwable $exception): void
    {
        // Move to failed_jobs table
        $this->db->table($this->failedTable)->insert([
            'queue' => $job['queue'],
            'payload' => $job['payload'],
            'exception' => $exception->getMessage() . "\n" . $exception->getTraceAsString(),
            'failed_at' => time(),
        ]);
        
        // Delete from jobs table
        $this->delete($job['id']);
    }
    
    /**
     * Get the size of the queue
     * 
     * @param string|null $queue
     * @return int
     */
    public function size(?string $queue = null): int
    {
        $query = $this->db->table($this->table);
        
        if ($queue) {
            $query->where('queue', $queue);
        }
        
        return $query->count();
    }
    
    /**
     * Get pending jobs count
     * 
     * @param string|null $queue
     * @return int
     */
    public function pending(?string $queue = null): int
    {
        $query = $this->db->table($this->table)
            ->whereNull('reserved_at')
            ->where('available_at', '<=', time());
        
        if ($queue) {
            $query->where('queue', $queue);
        }
        
        return $query->count();
    }
    
    /**
     * Get failed jobs count
     * 
     * @return int
     */
    public function failedCount(): int
    {
        return $this->db->table($this->failedTable)->count();
    }
    
    /**
     * Get all failed jobs
     * 
     * @return array
     */
    public function failed(): array
    {
        return $this->db->table($this->failedTable)
            ->orderBy('failed_at', 'desc')
            ->get()
            ->toArray();
    }
    
    /**
     * Retry a failed job
     * 
     * @param int $failedJobId
     * @return bool
     */
    public function retry(int $failedJobId): bool
    {
        $failed = $this->db->table($this->failedTable)
            ->where('id', $failedJobId)
            ->first();
        
        if (!$failed) {
            return false;
        }
        
        // Re-queue the job
        $this->db->table($this->table)->insert([
            'queue' => $failed['queue'],
            'payload' => $failed['payload'],
            'attempts' => 0,
            'available_at' => time(),
            'created_at' => time(),
            'reserved_at' => null,
        ]);
        
        // Remove from failed
        $this->db->table($this->failedTable)
            ->where('id', $failedJobId)
            ->delete();
        
        return true;
    }
    
    /**
     * Clear all failed jobs
     * 
     * @return int Number of jobs cleared
     */
    public function clearFailed(): int
    {
        return $this->db->table($this->failedTable)->delete();
    }
    
    /**
     * Create job payload
     * 
     * @param Job $job
     * @return string
     */
    protected function createPayload(Job $job): string
    {
        return serialize([
            'class' => get_class($job),
            'data' => $job,
            'tries' => $job->tries,
            'retryAfter' => $job->retryAfter,
        ]);
    }
    
    /**
     * Unserialize job from payload
     * 
     * @param string $payload
     * @return Job
     */
    public function unserializeJob(string $payload): Job
    {
        $data = unserialize($payload);
        return $data['data'];
    }
}
