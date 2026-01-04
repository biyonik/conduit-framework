<?php

declare(strict_types=1);

namespace Conduit\Queue;

use Conduit\Http\Request;
use Conduit\Http\JsonResponse;

class QueueController
{
    protected Worker $worker;
    protected DatabaseQueue $queue;
    
    public function __construct(Worker $worker, DatabaseQueue $queue)
    {
        $this->worker = $worker;
        $this->queue = $queue;
    }
    
    public function process(Request $request): JsonResponse
    {
        if (!$this->validateToken($request->input('token'))) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }
        
        $queue = $request->input('queue', 'default');
        $limit = min((int) $request->input('limit', 10), 50);
        $timeout = min((int) $request->input('timeout', 25), 55);
        
        $startTime = microtime(true);
        
        $processed = $this->worker->work(
            queue: $queue,
            limit: $limit,
            timeout: $timeout
        );
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        return new JsonResponse([
            'success' => true,
            'processed' => $processed,
            'duration_ms' => $duration,
            'queue' => $queue,
            'pending' => $this->queue->pending($queue),
            'failed' => $this->queue->failedCount(),
        ]);
    }
    
    public function stats(Request $request): JsonResponse
    {
        if (!$this->validateToken($request->input('token'))) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }
        
        $queues = ['default', 'emails', 'notifications'];
        $stats = [];
        
        foreach ($queues as $queue) {
            $stats[$queue] = [
                'size' => $this->queue->size($queue),
                'pending' => $this->queue->pending($queue),
            ];
        }
        
        return new JsonResponse([
            'success' => true,
            'queues' => $stats,
            'failed' => $this->queue->failedCount(),
        ]);
    }
    
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
