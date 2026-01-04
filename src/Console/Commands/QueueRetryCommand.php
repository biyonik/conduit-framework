<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

use Conduit\Queue\DatabaseQueue;

/**
 * Queue Retry Command
 * 
 * Retry a failed job or all failed jobs
 */
class QueueRetryCommand extends Command
{
    protected string $name = 'queue:retry';
    protected string $description = 'Retry a failed job or all failed jobs';
    
    /**
     * Execute the command
     * 
     * @return int
     */
    public function handle(): int
    {
        $queue = app(DatabaseQueue::class);
        
        $id = $this->input->getArgument(1);
        
        if ($id === 'all') {
            $failed = $queue->failed();
            $retried = 0;
            
            foreach ($failed as $job) {
                if ($queue->retry($job['id'])) {
                    $retried++;
                }
            }
            
            $this->success("Retried {$retried} failed jobs");
        } elseif ($id) {
            if ($queue->retry((int) $id)) {
                $this->success("Job {$id} has been pushed back onto the queue");
            } else {
                $this->error("Failed job {$id} not found");
                return 1;
            }
        } else {
            $this->error('Please specify a job ID or "all"');
            $this->line('  Usage: php conduit queue:retry 5');
            $this->line('         php conduit queue:retry all');
            return 1;
        }
        
        return 0;
    }
}
