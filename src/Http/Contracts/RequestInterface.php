<?php

declare(strict_types=1);

namespace Conduit\Http\Contracts;

use Psr\Http\Message\ServerRequestInterface;

/**
 * HTTP Request Interface
 * 
 * PSR-7 ServerRequestInterface'i extend eder ve framework'e özgü
 * helper metodlar ekler. Immutable pattern kullanır.
 * 
 * @package Conduit\Http\Contracts
 */
interface RequestInterface extends ServerRequestInterface
{
    /**
     * HTTP method getter
     * 
     * @return string
     */
    public function method(): string;
    
    /**
     * Request path getter (without query string)
     * 
     * @return string
     */
    public function path(): string;
    
    /**
     * Full URL getter
     * 
     * @return string
     */
    public function url(): string;
    
    /**
     * Full URL with query string getter
     * 
     * @return string
     */
    public function fullUrl(): string;
    
    /**
     * Check if request is HTTPS
     * 
     * @return bool
     */
    public function isSecure(): bool;
    
    /**
     * Get client IP address
     * 
     * @param bool $checkProxy Check proxy headers?
     * @return string
     */
    public function ip(bool $checkProxy = true): string;
    
    /**
     * Get user agent
     * 
     * @return string|null
     */
    public function userAgent(): ?string;
    
    /**
     * Get header value (MISSING METHOD - NOW ADDED!)
     * 
     * @param string $name Header name
     * @param string|null $default Default value
     * @return string|null
     */
    public function header(string $name, ?string $default = null): ?string;
    
    /**
     * Check if header exists
     * 
     * @param string $name Header name
     * @return bool
     */
    public function hasHeader(string $name): bool;
    
    /**
     * Get bearer token from Authorization header
     * 
     * @return string|null
     */
    public function bearerToken(): ?string;
    
    /**
     * Get input value from any source (query, body, route params)
     * 
     * @param string|null $key Input key (null = all)
     * @param mixed $default Default value
     * @return mixed
     */
    public function input(?string $key = null, mixed $default = null): mixed;
    
    /**
     * Get all input data
     * 
     * @return array<string, mixed>
     */
    public function all(): array;
    
    /**
     * Get only specified input keys
     * 
     * @param array<string> $keys Keys to get
     * @return array<string, mixed>
     */
    public function only(array $keys): array;
    
    /**
     * Get all input except specified keys
     * 
     * @param array<string> $keys Keys to exclude
     * @return array<string, mixed>
     */
    public function except(array $keys): array;
    
    /**
     * Check if input key exists
     * 
     * @param string $key Input key
     * @return bool
     */
    public function has(string $key): bool;
    
    /**
     * Check if input key exists and is not empty
     * 
     * @param string $key Input key
     * @return bool
     */
    public function filled(string $key): bool;
    
    /**
     * Get query parameter
     * 
     * @param string $key Parameter key
     * @param mixed $default Default value
     * @return mixed
     */
    public function query(string $key, mixed $default = null): mixed;
    
    /**
     * Get POST/body parameter
     * 
     * @param string $key Parameter key
     * @param mixed $default Default value
     * @return mixed
     */
    public function post(string $key, mixed $default = null): mixed;
    
    /**
     * Get route parameter
     * 
     * @param string $key Parameter key
     * @param mixed $default Default value
     * @return mixed
     */
    public function route(string $key, mixed $default = null): mixed;
    
    /**
     * Get cookie value
     * 
     * @param string $key Cookie key
     * @param mixed $default Default value
     * @return mixed
     */
    public function cookie(string $key, mixed $default = null): mixed;
    
    /**
     * Get file from upload
     * 
     * @param string $key File key
     * @return \Psr\Http\Message\UploadedFileInterface|null
     */
    public function file(string $key): ?\Psr\Http\Message\UploadedFileInterface;
    
    /**
     * Check if request has file upload
     * 
     * @param string $key File key
     * @return bool
     */
    public function hasFile(string $key): bool;
    
    /**
     * Check if request is JSON
     * 
     * @return bool
     */
    public function isJson(): bool;
    
    /**
     * Check if request wants JSON response
     * 
     * @return bool
     */
    public function wantsJson(): bool;
    
    /**
     * Check if request expects JSON response
     * 
     * @return bool
     */
    public function expectsJson(): bool;
    
    /**
     * Check if request is AJAX
     * 
     * @return bool
     */
    public function isAjax(): bool;
    
    /**
     * Get request format (json, html, xml)
     * 
     * @return string
     */
    public function format(): string;
}