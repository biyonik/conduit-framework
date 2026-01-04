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
        
        $sql = 'INSERT INTO ' . $this->table . ' (queue, payload, attempts, available_at, created_at, reserved_at) VALUES (?, ?, ?, ?, ?, ?)';
        $this->db->insert($sql, [
            $job->queue,
            $payload,
            0,
            $availableAt,
            time(),
            null,
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
        
        // Use raw SQL to avoid QueryBuilder bug with GROUP BY
        $sql = 'SELECT * FROM ' . $this->table . ' WHERE queue = ? AND available_at <= ? AND reserved_at IS NULL ORDER BY id ASC LIMIT 1';
        $results = $this->db->select($sql, [$queue, time()]);
        
        if (empty($results)) {
            return null;
        }
        
        $job = $results[0];
        
        $affected = $this->db->update(
            'UPDATE ' . $this->table . ' SET reserved_at = ?, attempts = ? WHERE id = ? AND reserved_at IS NULL',
            [time(), $job['attempts'] + 1, $job['id']]
        );
        
        if ($affected === 0) {
            return $this->pop($queue);
        }
        
        $job['attempts'] = $job['attempts'] + 1;
        
        return $job;
    }
    
    public function delete(int $jobId): void
    {
        $this->db->delete('DELETE FROM ' . $this->table . ' WHERE id = ?', [$jobId]);
    }
    
    public function release(int $jobId, int $delay = 0): void
    {
        $this->db->update(
            'UPDATE ' . $this->table . ' SET reserved_at = ?, available_at = ? WHERE id = ?',
            [null, time() + $delay, $jobId]
        );
    }
    
    public function fail(array $job, \Throwable $exception): void
    {
        $this->db->insert(
            'INSERT INTO ' . $this->failedTable . ' (queue, payload, exception, failed_at) VALUES (?, ?, ?, ?)',
            [
                $job['queue'],
                $job['payload'],
                $exception->getMessage() . "\n" . $exception->getTraceAsString(),
                time(),
            ]
        );
        
        $this->delete($job['id']);
    }
    
    public function size(?string $queue = null): int
    {
        $sql = 'SELECT COUNT(*) as count FROM ' . $this->table;
        $bindings = [];
        
        if ($queue) {
            $sql .= ' WHERE queue = ?';
            $bindings[] = $queue;
        }
        
        $result = $this->db->select($sql, $bindings);
        return (int) $result[0]['count'];
    }
    
    public function pending(?string $queue = null): int
    {
        $sql = 'SELECT COUNT(*) as count FROM ' . $this->table . ' WHERE reserved_at IS NULL AND available_at <= ?';
        $bindings = [time()];
        
        if ($queue) {
            $sql .= ' AND queue = ?';
            $bindings[] = $queue;
        }
        
        $result = $this->db->select($sql, $bindings);
        return (int) $result[0]['count'];
    }
    
    public function failedCount(): int
    {
        $result = $this->db->select('SELECT COUNT(*) as count FROM ' . $this->failedTable);
        return (int) $result[0]['count'];
    }
    
    public function failed(): array
    {
        return $this->db->select('SELECT * FROM ' . $this->failedTable . ' ORDER BY failed_at DESC');
    }
    
    public function retry(int $failedJobId): bool
    {
        $results = $this->db->select('SELECT * FROM ' . $this->failedTable . ' WHERE id = ?', [$failedJobId]);
        
        if (empty($results)) {
            return false;
        }
        
        $failed = $results[0];
        
        $this->db->insert(
            'INSERT INTO ' . $this->table . ' (queue, payload, attempts, available_at, created_at, reserved_at) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $failed['queue'],
                $failed['payload'],
                0,
                time(),
                time(),
                null,
            ]
        );
        
        $this->db->delete('DELETE FROM ' . $this->failedTable . ' WHERE id = ?', [$failedJobId]);
        
        return true;
    }
    
    public function clearFailed(): int
    {
        return $this->db->delete('DELETE FROM ' . $this->failedTable);
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
