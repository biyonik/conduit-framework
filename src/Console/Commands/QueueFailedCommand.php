<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

use Conduit\Queue\DatabaseQueue;

/**
 * Queue Failed Command
 * 
 * List all failed jobs
 */
class QueueFailedCommand extends Command
{
    protected string $name = 'queue:failed';
    protected string $description = 'List all failed jobs';
    
    /**
     * Execute the command
     * 
     * @return int
     */
    public function handle(): int
    {
        $queue = app(DatabaseQueue::class);
        $failed = $queue->failed();
        
        if (empty($failed)) {
            $this->info('No failed jobs.');
            return 0;
        }
        
        $headers = ['ID', 'Queue', 'Job', 'Failed At', 'Error'];
        $rows = [];
        
        foreach ($failed as $job) {
            $payload = unserialize($job['payload']);
            $className = class_basename($payload['class']);
            $failedAt = date('Y-m-d H:i:s', $job['failed_at']);
            $error = substr($job['exception'], 0, 50) . '...';
            
            $rows[] = [$job['id'], $job['queue'], $className, $failedAt, $error];
        }
        
        $this->table($headers, $rows);
        $this->line();
        $this->info('Total failed jobs: ' . count($failed));
        
        return 0;
    }
}
