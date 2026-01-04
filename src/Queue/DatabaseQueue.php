<?php

declare(strict_types=1);

namespace Conduit\Queue;

use Conduit\Database\Connection;
use Conduit\Database\QueryBuilder;
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
     * Create a query builder for a table
     * 
     * @param string $table
     * @return QueryBuilder
     */
    protected function table(string $table): QueryBuilder
    {
        $driver = $this->db->getDriverName();
        $grammar = match ($driver) {
            'sqlite' => new \Conduit\Database\Grammar\SQLiteGrammar(),
            'pgsql' => new \Conduit\Database\Grammar\PostgreSQLGrammar(),
            default => new \Conduit\Database\Grammar\MySQLGrammar(),
        };
        
        $qb = new QueryBuilder($this->db, $grammar);
        return $qb->from($table);
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
        
        $insertId = $this->table($this->table)->insert([
            'queue' => $job->queue,
            'payload' => $payload,
            'attempts' => 0,
            'available_at' => $availableAt,
            'created_at' => time(),
            'reserved_at' => null,
        ]);
        
        return $insertId;
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
        $time = time();
        
        // Find available job using raw SQL to avoid Grammar bugs
        $results = $this->db->select(
            "SELECT * FROM {$this->table} WHERE queue = ? AND available_at <= ? AND reserved_at IS NULL ORDER BY id ASC LIMIT 1",
            [$queue, $time]
        );
        
        if (empty($results)) {
            return null;
        }
        
        $job = $results[0];
        
        // Reserve the job (atomic update)
        $affected = $this->table($this->table)
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
        $this->table($this->table)
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
        $this->table($this->table)
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
        $this->table($this->failedTable)->insert([
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
        if ($queue) {
            $result = $this->db->select(
                "SELECT COUNT(*) as count FROM {$this->table} WHERE queue = ?",
                [$queue]
            );
        } else {
            $result = $this->db->select(
                "SELECT COUNT(*) as count FROM {$this->table}",
                []
            );
        }
        
        return (int) ($result[0]['count'] ?? 0);
    }
    
    /**
     * Get pending jobs count
     * 
     * @param string|null $queue
     * @return int
     */
    public function pending(?string $queue = null): int
    {
        $time = time();
        
        if ($queue) {
            $result = $this->db->select(
                "SELECT COUNT(*) as count FROM {$this->table} WHERE queue = ? AND reserved_at IS NULL AND available_at <= ?",
                [$queue, $time]
            );
        } else {
            $result = $this->db->select(
                "SELECT COUNT(*) as count FROM {$this->table} WHERE reserved_at IS NULL AND available_at <= ?",
                [$time]
            );
        }
        
        return (int) ($result[0]['count'] ?? 0);
    }
    
    /**
     * Get failed jobs count
     * 
     * @return int
     */
    public function failedCount(): int
    {
        $result = $this->db->select(
            "SELECT COUNT(*) as count FROM {$this->failedTable}",
            []
        );
        
        return (int) ($result[0]['count'] ?? 0);
    }
    
    /**
     * Get all failed jobs
     * 
     * @return array
     */
    public function failed(): array
    {
        $results = $this->db->select(
            "SELECT * FROM {$this->failedTable} ORDER BY failed_at DESC",
            []
        );
        
        return $results;
    }
    
    /**
     * Retry a failed job
     * 
     * @param int $failedJobId
     * @return bool
     */
    public function retry(int $failedJobId): bool
    {
        $failed = $this->table($this->failedTable)
            ->where('id', $failedJobId)
            ->first();
        
        if (!$failed) {
            return false;
        }
        
        // Re-queue the job
        $this->table($this->table)->insert([
            'queue' => $failed['queue'],
            'payload' => $failed['payload'],
            'attempts' => 0,
            'available_at' => time(),
            'created_at' => time(),
            'reserved_at' => null,
        ]);
        
        // Remove from failed
        $this->table($this->failedTable)
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
        return $this->table($this->failedTable)->delete();
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
