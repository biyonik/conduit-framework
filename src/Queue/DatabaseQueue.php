<?php

declare(strict_types=1);

namespace Conduit\Queue;

use Conduit\Database\Connection;
use Conduit\Queue\Contracts\QueueInterface;

class DatabaseQueue implements QueueInterface
{
    protected Connection $db;
    protected string $table = 'jobs';
    protected string $failedTable = 'failed_jobs';
    
    public function __construct(Connection $db)
    {
        $this->db = $db;
    }
    
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
    
    public function later(int $delay, Job $job): int
    {
        $job->delay = $delay;
        return $this->push($job);
    }
    
    public function pop(?string $queue = null): ?array
    {
        $queue = $queue ?? 'default';
        
        $job = $this->db->table($this->table)
            ->where('queue', $queue)
            ->where('available_at', '<=', time())
            ->whereNull('reserved_at')
            ->orderBy('id', 'asc')
            ->first();
        
        if (!$job) {
            return null;
        }
        
        $affected = $this->db->table($this->table)
            ->where('id', $job['id'])
            ->whereNull('reserved_at')
            ->update([
                'reserved_at' => time(),
                'attempts' => $job['attempts'] + 1,
            ]);
        
        if ($affected === 0) {
            return $this->pop($queue);
        }
        
        $job['attempts'] = $job['attempts'] + 1;
        
        return $job;
    }
    
    public function delete(int $jobId): void
    {
        $this->db->table($this->table)
            ->where('id', $jobId)
            ->delete();
    }
    
    public function release(int $jobId, int $delay = 0): void
    {
        $this->db->table($this->table)
            ->where('id', $jobId)
            ->update([
                'reserved_at' => null,
                'available_at' => time() + $delay,
            ]);
    }
    
    public function fail(array $job, \Throwable $exception): void
    {
        $this->db->table($this->failedTable)->insert([
            'queue' => $job['queue'],
            'payload' => $job['payload'],
            'exception' => $exception->getMessage() . "\n" . $exception->getTraceAsString(),
            'failed_at' => time(),
        ]);
        
        $this->delete($job['id']);
    }
    
    public function size(?string $queue = null): int
    {
        $query = $this->db->table($this->table);
        
        if ($queue) {
            $query->where('queue', $queue);
        }
        
        return $query->count();
    }
    
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
    
    public function failedCount(): int
    {
        return $this->db->table($this->failedTable)->count();
    }
    
    public function failed(): array
    {
        return $this->db->table($this->failedTable)
            ->orderBy('failed_at', 'desc')
            ->get()
            ->toArray();
    }
    
    public function retry(int $failedJobId): bool
    {
        $failed = $this->db->table($this->failedTable)
            ->where('id', $failedJobId)
            ->first();
        
        if (!$failed) {
            return false;
        }
        
        $this->db->table($this->table)->insert([
            'queue' => $failed['queue'],
            'payload' => $failed['payload'],
            'attempts' => 0,
            'available_at' => time(),
            'created_at' => time(),
            'reserved_at' => null,
        ]);
        
        $this->db->table($this->failedTable)
            ->where('id', $failedJobId)
            ->delete();
        
        return true;
    }
    
    public function clearFailed(): int
    {
        return $this->db->table($this->failedTable)->delete();
    }
    
    protected function createPayload(Job $job): string
    {
        return serialize([
            'class' => get_class($job),
            'data' => $job,
            'tries' => $job->tries,
            'retryAfter' => $job->retryAfter,
        ]);
    }
    
    public function unserializeJob(string $payload): Job
    {
        $data = unserialize($payload);
        return $data['data'];
    }
}
