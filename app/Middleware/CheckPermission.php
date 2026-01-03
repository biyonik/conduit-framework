<?php

declare(strict_types=1);

namespace App\Middleware;

use Conduit\Http\Request;
use Conduit\Http\Response;
use Closure;

class CheckPermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Before request processing
        
        $response = $next($request);
        
        // After request processing
        
        return $response;
    }
}
