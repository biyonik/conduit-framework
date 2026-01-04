<?php

declare(strict_types=1);

namespace Conduit\Queue\Middleware;

use Closure;
use Conduit\Http\Request;
use Conduit\Http\Response;
use Conduit\Queue\Worker;

/**
 * Piggyback Queue Processing Middleware
 * 
 * Processes queue jobs after sending the response.
 * Uses fastcgi_finish_request() to not delay the user.
 */
class ProcessQueueMiddleware
{
    protected Worker $worker;
    
    public function __construct(Worker $worker)
    {
        $this->worker = $worker;
    }
    
    /**
     * Handle the request
     * 
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // Only process on successful responses
        if ($response->getStatusCode() >= 400) {
            return $response;
        }
        
        // Skip if explicitly disabled
        if ($request->header('X-Skip-Queue-Processing')) {
            return $response;
        }
        
        // Send response first, then process queue
        if (function_exists('fastcgi_finish_request')) {
            // FastCGI - send response immediately
            fastcgi_finish_request();
            
            // Now process queue jobs (user won't wait)
            $this->worker->processOnRequest(
                maxJobs: 2,
                maxSeconds: 3
            );
        }
        
        return $response;
    }
}
