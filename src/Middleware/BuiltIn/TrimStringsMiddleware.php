<?php

declare(strict_types=1);

namespace Conduit\Middleware\BuiltIn;

use Closure;
use Conduit\Http\Contracts\RequestInterface;
use Conduit\Http\Contracts\ResponseInterface;
use Conduit\Middleware\MiddlewareInterface;

/**
 * Trim Strings Middleware
 * 
 * Request input'larındaki string değerleri trim eder.
 * Leading/trailing whitespace'leri temizler.
 * 
 * @package Conduit\Middleware\BuiltIn
 */
class TrimStringsMiddleware implements MiddlewareInterface
{
    /** @var array<string> Trim edilmeyecek field'lar */
    private array $except = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
    ];
    
    /**
     * Handle trim strings middleware
     * 
     * @param RequestInterface $request HTTP request
     * @param Closure $next Next middleware
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, Closure $next): ResponseInterface
    {
        // Request input'larını trim et
        $trimmedData = $this->trimArray($request->all());
        
        // Request'e trimmed data'yı set et
        $request = $request->withParsedBody($trimmedData);
        
        return $next($request);
    }
    
    /**
     * Array'deki string değerleri recursively trim et
     * 
     * @param array<string, mixed> $data Input data
     * @return array<string, mixed>
     */
    private function trimArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $this->except, true)) {
                continue; // Skip exempt fields
            }
            
            if (is_string($value)) {
                $data[$key] = trim($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->trimArray($value); // Recursive
            }
        }
        
        return $data;
    }
    
    /**
     * Middleware parametrelerini set et (MiddlewareInterface requirement)
     * 
     * @param array<string> $parameters Parameter array
     * @return self
     */
    public function setParameters(array $parameters): self
    {
        return $this;
    }
    
    /**
     * Except field'ları set et
     * 
     * @param array<string> $except Exception field names
     * @return self
     */
    public function except(array $except): self
    {
        $this->except = array_merge($this->except, $except);
        return $this;
    }
}