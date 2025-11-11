<?php

declare(strict_types=1);

namespace Conduit\Middleware\BuiltIn;

use Closure;
use Conduit\Http\Contracts\RequestInterface;
use Conduit\Http\Contracts\ResponseInterface;
use Conduit\Http\JsonResponse;
use Conduit\Middleware\MiddlewareInterface;

/**
 * JSON Only Middleware
 * 
 * API endpoint'lerin sadece JSON content kabul etmesini sağlar.
 * Content-Type ve Accept header kontrolü yapar.
 * 
 * @package Conduit\Middleware\BuiltIn
 */
class JsonOnlyMiddleware implements MiddlewareInterface
{
    /** @var bool Accept header kontrolü yap mı? */
    private bool $enforceAccept = false;
    
    /** @var array<string> Allowed content types */
    private array $allowedContentTypes = [
        'application/json',
        'application/vnd.api+json',
        'text/json',
    ];
    
    /**
     * Handle JSON only middleware
     * 
     * @param RequestInterface $request HTTP request
     * @param Closure $next Next middleware
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, Closure $next): ResponseInterface
    {
        // GET requests don't need Content-Type check
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }
        
        // Content-Type kontrolü
        $contentType = $request->header('Content-Type', '');
        
        if (!$this->isValidContentType($contentType)) {
            return new JsonResponse([
                'success' => false,
                'error' => [
                    'message' => 'Content-Type must be application/json',
                    'code' => 'INVALID_CONTENT_TYPE',
                ],
            ], 415); // Unsupported Media Type
        }
        
        // Accept header kontrolü (optional)
        if ($this->enforceAccept) {
            $accept = $request->header('Accept', '');
            
            if (!$this->isValidAcceptHeader($accept)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => [
                        'message' => 'Accept header must include application/json',
                        'code' => 'INVALID_ACCEPT_HEADER',
                    ],
                ], 406); // Not Acceptable
            }
        }
        
        return $next($request);
    }
    
    /**
     * Content-Type'ın valid olup olmadığını kontrol et
     * 
     * @param string $contentType Content-Type header
     * @return bool
     */
    private function isValidContentType(string $contentType): bool
    {
        if (empty($contentType)) {
            return false;
        }
        
        // Parse content type (ignore parameters)
        $type = strtok($contentType, ';');
        
        return in_array(strtolower(trim($type)), $this->allowedContentTypes, true);
    }
    
    /**
     * Accept header'ın valid olup olmadığını kontrol et
     * 
     * @param string $accept Accept header
     * @return bool
     */
    private function isValidAcceptHeader(string $accept): bool
    {
        if (empty($accept)) {
            return false;
        }
        
        // */* veya application/* accept edilir
        if (str_contains($accept, '*/*') || str_contains($accept, 'application/*')) {
            return true;
        }
        
        // JSON MIME type'larından birini içeriyor mu?
        foreach ($this->allowedContentTypes as $allowedType) {
            if (str_contains(strtolower($accept), $allowedType)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Middleware parametrelerini set et (MiddlewareInterface requirement)
     * 
     * @param array<string> $parameters Parameter array
     * @return self
     */
    public function setParameters(array $parameters): self
    {
        // JSON Only middleware genelde parameterized değildir
        return $this;
    }
    
    /**
     * Accept enforcement setter
     * 
     * @param bool $enforce Accept header enforce et mi?
     * @return self
     */
    public function enforceAccept(bool $enforce = true): self
    {
        $this->enforceAccept = $enforce;
        return $this;
    }
}