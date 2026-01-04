<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

use Conduit\Queue\DatabaseQueue;

/**
 * Queue Clear Command
 * 
 * Clear all failed jobs
 */
class QueueClearCommand extends Command
{
    protected string $name = 'queue:clear';
    protected string $description = 'Clear all failed jobs';
    
    /**
     * Execute the command
     * 
     * @return int
     */
    public function handle(): int
    {
        $queue = app(DatabaseQueue::class);
        
        $count = $queue->clearFailed();
        
        $this->success("Cleared {$count} failed jobs");
        
        return 0;
    }
}
