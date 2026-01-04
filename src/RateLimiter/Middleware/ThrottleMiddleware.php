<?php

declare(strict_types=1);

namespace Conduit\RateLimiter\Middleware;

use Closure;
use Conduit\Http\Request;
use Conduit\Http\Response;
use Conduit\Http\JsonResponse;
use Conduit\RateLimiter\RateLimiter;
use Conduit\RateLimiter\Exceptions\TooManyRequestsException;
use Conduit\Middleware\MiddlewareInterface;

class ThrottleMiddleware implements MiddlewareInterface
{
    protected RateLimiter $limiter;
    
    /**
     * @var array<string>
     */
    protected array $parameters = [];
    
    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }
    
    /**
     * Handle the request
     * 
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle($request, Closure $next): Response
    {
        // Parse parameters (maxAttempts, decayMinutes, prefix)
        $maxAttempts = isset($this->parameters[0]) ? (int) $this->parameters[0] : 60;
        $decayMinutes = isset($this->parameters[1]) ? (int) $this->parameters[1] : 1;
        $prefix = $this->parameters[2] ?? '';
        
        $key = $this->resolveRequestKey($request, $prefix);
        $decaySeconds = $decayMinutes * 60;
        
        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            return $this->buildTooManyAttemptsResponse($key, $maxAttempts);
        }
        
        $this->limiter->hit($key, $decaySeconds);
        
        $response = $next($request);
        
        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->limiter->remaining($key, $maxAttempts),
            $this->limiter->availableIn($key)
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;
        return $this;
    }
    
    protected function resolveRequestKey(Request $request, string $prefix): string
    {
        // Use authenticated user ID if available, otherwise IP
        $identifier = $request->getAttribute('user')?->id ?? $request->ip();
        
        $routeKey = $request->method() . '|' . $request->path();
        
        return $prefix . sha1($identifier . '|' . $routeKey);
    }
    
    protected function buildTooManyAttemptsResponse(string $key, int $maxAttempts): JsonResponse
    {
        $retryAfter = $this->limiter->availableIn($key);
        
        $response = new JsonResponse([
            'error' => 'Too Many Requests',
            'message' => 'Rate limit exceeded. Please try again later.',
            'retry_after' => $retryAfter,
        ], 429);
        
        return $this->addHeaders($response, $maxAttempts, 0, $retryAfter);
    }
    
    protected function addHeaders(Response $response, int $maxAttempts, int $remaining, int $retryAfter): Response
    {
        $response = $response->withHeader('X-RateLimit-Limit', (string) $maxAttempts);
        $response = $response->withHeader('X-RateLimit-Remaining', (string) max(0, $remaining));
        
        if ($retryAfter > 0) {
            $response = $response->withHeader('X-RateLimit-Reset', (string) (time() + $retryAfter));
            $response = $response->withHeader('Retry-After', (string) $retryAfter);
        }
        
        return $response;
    }
}
