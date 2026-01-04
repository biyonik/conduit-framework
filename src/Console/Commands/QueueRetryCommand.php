<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

use Conduit\Queue\DatabaseQueue;

class QueueRetryCommand extends Command
{
    protected string $name = 'queue:retry';
    protected string $description = 'Retry failed queue jobs';
    
    public function handle(): int
    {
        $queue = app(DatabaseQueue::class);
        $id = $this->input->getArgument('id');
        
        if ($id === 'all') {
            $failed = $queue->failed();
            $count = 0;
            
            foreach ($failed as $job) {
                if ($queue->retry($job['id'])) {
                    $count++;
                }
            }
            
            $this->success("Retried {$count} failed jobs");
            return 0;
        }
        
        if (!is_numeric($id)) {
            $this->error('Invalid job ID. Use a number or "all"');
            return 1;
        }
        
        $jobId = (int) $id;
        
        if ($queue->retry($jobId)) {
            $this->success("Job {$jobId} has been pushed back to the queue");
            return 0;
        }
        
        $this->error("Failed job {$jobId} not found");
        return 1;
    }
}
