<?php

declare(strict_types=1);

namespace Conduit\Queue\Middleware;

use Closure;
use Conduit\Http\Request;
use Conduit\Http\Response;
use Conduit\Queue\Worker;

class ProcessQueueMiddleware
{
    protected Worker $worker;
    
    public function __construct(Worker $worker)
    {
        $this->worker = $worker;
    }
    
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        if ($response->getStatusCode() >= 400) {
            return $response;
        }
        
        if ($request->header('X-Skip-Queue-Processing')) {
            return $response;
        }
        
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            $this->worker->processOnRequest(maxJobs: 2, maxSeconds: 3);
        }
        
        return $response;
    }
}
