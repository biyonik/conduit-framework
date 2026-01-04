<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

use Conduit\Queue\Worker;

/**
 * Queue Work Command
 * 
 * Process jobs from the queue
 */
class QueueWorkCommand extends Command
{
    protected string $name = 'queue:work';
    protected string $description = 'Process jobs from the queue';
    
    /**
     * Execute the command
     * 
     * @return int
     */
    public function handle(): int
    {
        $queue = $this->input->getOption('queue') ?? 'default';
        $limit = (int) ($this->input->getOption('limit') ?? 0);
        $timeout = (int) ($this->input->getOption('timeout') ?? 0);
        
        $this->info("Processing queue: {$queue}");
        
        if ($limit > 0) {
            $this->info("Limit: {$limit} jobs");
        }
        
        if ($timeout > 0) {
            $this->info("Timeout: {$timeout} seconds");
        }
        
        $this->line();
        
        $worker = app(Worker::class);
        
        $processed = $worker->work(
            queue: $queue,
            limit: $limit,
            timeout: $timeout
        );
        
        $this->success("Processed {$processed} jobs");
        
        return 0;
    }
}
