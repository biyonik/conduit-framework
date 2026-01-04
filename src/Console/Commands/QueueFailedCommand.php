<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

use Conduit\Queue\DatabaseQueue;

class QueueFailedCommand extends Command
{
    protected string $name = 'queue:failed';
    protected string $description = 'List all failed queue jobs';
    
    public function handle(): int
    {
        $queue = app(DatabaseQueue::class);
        $failed = $queue->failed();
        
        if (empty($failed)) {
            $this->info('No failed jobs found');
            return 0;
        }
        
        $headers = ['ID', 'Queue', 'Exception', 'Failed At'];
        $rows = [];
        
        foreach ($failed as $job) {
            $exception = strlen($job['exception']) > 50 
                ? substr($job['exception'], 0, 50) . '...' 
                : $job['exception'];
            
            $rows[] = [
                $job['id'],
                $job['queue'],
                $exception,
                date('Y-m-d H:i:s', $job['failed_at']),
            ];
        }
        
        $this->table($headers, $rows);
        $this->line();
        $this->info('Total: ' . count($failed) . ' failed jobs');
        
        return 0;
    }
}
