<?php

declare(strict_types=1);

namespace Conduit\Queue;

use Conduit\Http\Request;
use Conduit\Http\JsonResponse;

/**
 * Queue HTTP Controller
 * 
 * Provides HTTP endpoints for external cron services
 */
class QueueController
{
    protected Worker $worker;
    protected DatabaseQueue $queue;
    
    public function __construct(Worker $worker, DatabaseQueue $queue)
    {
        $this->worker = $worker;
        $this->queue = $queue;
    }
    
    /**
     * Process queue via HTTP
     * 
     * Endpoint: GET/POST /queue/process?token=xxx
     * For use with cron-job.org, easycron.com, etc.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function process(Request $request): JsonResponse
    {
        // Validate token
        if (!$this->validateToken($request->input('token'))) {
            return JsonResponse::error('Invalid token', 401);
        }
        
        $queue = $request->input('queue', 'default');
        $limit = min((int) $request->input('limit', 10), 50); // Max 50
        $timeout = min((int) $request->input('timeout', 25), 55); // Max 55 seconds
        
        $startTime = microtime(true);
        
        $processed = $this->worker->work(
            queue: $queue,
            limit: $limit,
            timeout: $timeout
        );
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        return JsonResponse::success([
            'processed' => $processed,
            'duration_ms' => $duration,
            'queue' => $queue,
            'pending' => $this->queue->pending($queue),
            'failed' => $this->queue->failedCount(),
        ]);
    }
    
    /**
     * Get queue stats
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        if (!$this->validateToken($request->input('token'))) {
            return JsonResponse::error('Invalid token', 401);
        }
        
        $queues = ['default', 'emails', 'notifications', 'images'];
        $stats = [];
        
        foreach ($queues as $queue) {
            $stats[$queue] = [
                'size' => $this->queue->size($queue),
                'pending' => $this->queue->pending($queue),
            ];
        }
        
        return JsonResponse::success([
            'queues' => $stats,
            'failed' => $this->queue->failedCount(),
        ]);
    }
    
    /**
     * Validate the request token
     * 
     * @param string|null $token
     * @return bool
     */
    protected function validateToken(?string $token): bool
    {
        if (!$token) {
            return false;
        }
        
        $expected = config('queue.token');
        
        if (!$expected) {
            return false;
        }
        
        return hash_equals($expected, $token);
    }
}
