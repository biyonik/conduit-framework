<?php

declare(strict_types=1);

namespace Conduit\Middleware\BuiltIn;

use Closure;
use Conduit\Http\Contracts\RequestInterface;
use Conduit\Http\Contracts\ResponseInterface;
use Conduit\Middleware\MiddlewareInterface;

/**
 * Convert Empty Strings To Null Middleware
 * 
 * Boş string değerlerini null'a çevirir.
 * Database'de nullable field'lar için yararlı.
 * 
 * @package Conduit\Middleware\BuiltIn
 */
class ConvertEmptyStringsToNullMiddleware implements MiddlewareInterface
{
    /** @var array<string> Convert edilmeyecek field'lar */
    private array $except = [];
    
    /**
     * Handle convert empty strings middleware
     * 
     * @param RequestInterface $request HTTP request
     * @param Closure $next Next middleware
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, Closure $next): ResponseInterface
    {
        // Request input'larını convert et
        $convertedData = $this->convertArray($request->all());
        
        // Request'e converted data'yı set et
        $request = $request->withParsedBody($convertedData);
        
        return $next($request);
    }
    
    /**
     * Array'deki empty string'leri recursively null'a çevir
     * 
     * @param array<string, mixed> $data Input data
     * @return array<string, mixed>
     */
    private function convertArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $this->except, true)) {
                continue; // Skip exempt fields
            }
            
            if ($value === '') {
                $data[$key] = null;
            } elseif (is_array($value)) {
                $data[$key] = $this->convertArray($value); // Recursive
            }
        }
        
        return $data;
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