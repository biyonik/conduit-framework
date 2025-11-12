<?php

declare(strict_types=1);

namespace Conduit\Middleware\BuiltIn;

use Closure;
use Conduit\Http\Contracts\RequestInterface;
use Conduit\Http\Contracts\ResponseInterface;
use Conduit\Http\JsonResponse;
use Conduit\Middleware\MiddlewareInterface;

/**
 * CORS Middleware
 * 
 * Cross-Origin Resource Sharing (CORS) desteği sağlar.
 * API endpoint'lerin farklı domain'lerden erişilebilmesini sağlar.
 * 
 * Özellikler:
 * - Preflight request handling (OPTIONS)
 * - Origin validation
 * - Credentials support
 * - Configurable headers, methods, max-age
 * 
 * @package Conduit\Middleware\BuiltIn
 */
class CorsMiddleware implements MiddlewareInterface
{
    /** @var array<string> Allowed origins */
    private array $allowedOrigins = ['*'];
    
    /** @var array<string> Allowed HTTP methods */
    private array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
    
    /** @var array<string> Allowed headers */
    private array $allowedHeaders = [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'Accept',
        'Origin',
        'User-Agent',
        'Cache-Control',
    ];
    
    /** @var array<string> Exposed headers */
    private array $exposedHeaders = [];
    
    /** @var int Max age for preflight cache */
    private int $maxAge = 86400; // 24 hours
    
    /** @var bool Support credentials */
    private bool $supportCredentials = true;
    
    /**
     * Handle CORS middleware
     * 
     * @param RequestInterface $request HTTP request
     * @param Closure $next Next middleware
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, Closure $next): ResponseInterface
    {
        // Preflight request handling (OPTIONS method)
        if ($request->method() === 'OPTIONS') {
            return $this->handlePreflightRequest($request);
        }
        
        // Normal request: Continue to next middleware/controller
        $response = $next($request);
        
        // Add CORS headers to response
        return $this->addCorsHeaders($request, $response);
    }
    
    /**
     * Preflight request'i handle et (OPTIONS)
     * 
     * @param RequestInterface $request HTTP request
     * @return ResponseInterface
     */
    private function handlePreflightRequest(RequestInterface $request): ResponseInterface
    {
        $origin = $request->header('Origin');
        $method = $request->header('Access-Control-Request-Method');
        $headers = $request->header('Access-Control-Request-Headers', '');
        
        // Origin kontrolü
        if (!$this->isOriginAllowed($origin)) {
            return new JsonResponse(['error' => 'Origin not allowed'], 403);
        }
        
        // Method kontrolü
        if ($method && !in_array($method, $this->allowedMethods, true)) {
            return new JsonResponse(['error' => 'Method not allowed'], 405);
        }
        
        // Headers kontrolü
        if ($headers) {
            $requestedHeaders = array_map('trim', explode(',', $headers));
            foreach ($requestedHeaders as $header) {
                if (!$this->isHeaderAllowed($header)) {
                    return new JsonResponse(['error' => 'Header not allowed'], 400);
                }
            }
        }
        
        // Preflight response with CORS headers
        $response = new JsonResponse([], 204); // No Content
        
        return $this->addCorsHeaders($request, $response);
    }
    
    /**
     * Response'a CORS headers ekle
     * 
     * @param RequestInterface $request HTTP request
     * @param ResponseInterface $response HTTP response
     * @return ResponseInterface
     */
    private function addCorsHeaders(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $origin = $request->header('Origin');
        
        // Allow-Origin header
        if ($this->isOriginAllowed($origin)) {
            if (in_array('*', $this->allowedOrigins, true) && !$this->supportCredentials) {
                $response = $response->withHeader('Access-Control-Allow-Origin', '*');
            } else {
                $response = $response->withHeader('Access-Control-Allow-Origin', $origin ?: '*');
            }
        }
        
        // Allow-Methods header
        $response = $response->withHeader(
            'Access-Control-Allow-Methods',
            implode(', ', $this->allowedMethods)
        );
        
        // Allow-Headers header
        $response = $response->withHeader(
            'Access-Control-Allow-Headers',
            implode(', ', $this->allowedHeaders)
        );
        
        // Expose-Headers header
        if (!empty($this->exposedHeaders)) {
            $response = $response->withHeader(
                'Access-Control-Expose-Headers',
                implode(', ', $this->exposedHeaders)
            );
        }
        
        // Max-Age header (preflight cache)
        $response = $response->withHeader('Access-Control-Max-Age', (string) $this->maxAge);
        
        // Allow-Credentials header
        if ($this->supportCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }
        
        return $response;
    }
    
    /**
     * Origin'in allowed olup olmadığını kontrol et
     * 
     * @param string|null $origin Request origin
     * @return bool
     */
    private function isOriginAllowed(?string $origin): bool
    {
        if (!$origin) {
            return true; // Same-origin requests
        }
        
        if (in_array('*', $this->allowedOrigins, true)) {
            return true; // Wildcard allowed
        }
        
        return in_array($origin, $this->allowedOrigins, true);
    }
    
    /**
     * Header'ın allowed olup olmadığını kontrol et
     * 
     * @param string $header Header name
     * @return bool
     */
    private function isHeaderAllowed(string $header): bool
    {
        return in_array(strtolower($header), array_map('strtolower', $this->allowedHeaders), true);
    }
    
    /**
     * Middleware parametrelerini set et (MiddlewareInterface requirement)
     * 
     * @param array<string> $parameters Parameter array
     * @return self
     */
    public function setParameters(array $parameters): self
    {
        // CORS middleware genelde parameterized değildir
        // Ama interface requirement için boş implementation
        return $this;
    }
    
    /**
     * Configuration setter (for testing/customization)
     * 
     * @param array<string, mixed> $config Configuration array
     * @return self
     */
    public function configure(array $config): self
    {
        if (isset($config['allowed_origins'])) {
            $this->allowedOrigins = $config['allowed_origins'];
        }
        
        if (isset($config['allowed_methods'])) {
            $this->allowedMethods = $config['allowed_methods'];
        }
        
        if (isset($config['allowed_headers'])) {
            $this->allowedHeaders = $config['allowed_headers'];
        }
        
        if (isset($config['exposed_headers'])) {
            $this->exposedHeaders = $config['exposed_headers'];
        }
        
        if (isset($config['max_age'])) {
            $this->maxAge = $config['max_age'];
        }
        
        if (isset($config['support_credentials'])) {
            $this->supportCredentials = $config['support_credentials'];
        }
        
        return $this;
    }
}