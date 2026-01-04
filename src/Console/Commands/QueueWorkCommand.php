<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

use Conduit\Queue\Worker;

class QueueWorkCommand extends Command
{
    protected string $name = 'queue:work';
    protected string $description = 'Process jobs from the queue';
    
    public function handle(): int
    {
        $queue = $this->input->getOption('queue') ?? 'default';
        $limit = (int) ($this->input->getOption('limit') ?? 0);
        $timeout = (int) ($this->input->getOption('timeout') ?? 0);
        
        $this->info("Processing queue: {$queue}");
        
        $worker = app(Worker::class);
        $processed = $worker->work($queue, $limit, $timeout);
        
        $this->success("Processed {$processed} jobs");
        return 0;
    }
}
