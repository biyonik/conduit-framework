<?php

declare(strict_types=1);

namespace Conduit\Queue;

use Conduit\Queue\Contracts\QueueInterface;

/**
 * Queue Manager
 * 
 * Manages queue connections and provides a simple API for dispatching jobs
 */
class QueueManager
{
    protected QueueInterface $queue;
    
    public function __construct(QueueInterface $queue)
    {
        $this->queue = $queue;
    }
    
    /**
     * Push a job onto the queue
     * 
     * @param Job $job
     * @return int Job ID
     */
    public function push(Job $job): int
    {
        return $this->queue->push($job);
    }
    
    /**
     * Push a job with delay
     * 
     * @param int $delay
     * @param Job $job
     * @return int Job ID
     */
    public function later(int $delay, Job $job): int
    {
        return $this->queue->later($delay, $job);
    }
    
    /**
     * Get the queue instance
     * 
     * @return QueueInterface
     */
    public function getQueue(): QueueInterface
    {
        return $this->queue;
    }
}
