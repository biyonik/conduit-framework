<?php

declare(strict_types=1);

namespace Conduit\Queue;

class QueueManager
{
    protected DatabaseQueue $queue;
    
    public function __construct(DatabaseQueue $queue)
    {
        $this->queue = $queue;
    }
    
    public function push(Job $job): int
    {
        return $this->queue->push($job);
    }
    
    public function later(int $delay, Job $job): int
    {
        return $this->queue->later($delay, $job);
    }
    
    public function size(?string $queue = null): int
    {
        return $this->queue->size($queue);
    }
    
    public function getQueue(): DatabaseQueue
    {
        return $this->queue;
    }
}
