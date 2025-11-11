<?php

declare(strict_types=1);

namespace Conduit\Routing\Contracts;

use Closure;
use Conduit\Http\Contracts\RequestInterface;
use Conduit\Routing\Route;

/**
 * Router Interface
 * 
 * Router'ın implement etmesi gereken temel contract.
 * RESTful routing, route groups, named routes ve middleware desteği sağlar.
 * 
 * @package Conduit\Routing\Contracts
 */
interface RouterInterface
{
    /**
     * GET route tanımla
     * 
     * @param string $uri Route URI pattern (/users/{id})
     * @param mixed $action Controller@method, Closure, veya invokable class
     * @return Route
     */
    public function get(string $uri, mixed $action): Route;
    
    /**
     * POST route tanımla
     * 
     * @param string $uri Route URI pattern
     * @param mixed $action Controller action
     * @return Route
     */
    public function post(string $uri, mixed $action): Route;
    
    /**
     * PUT route tanımla
     * 
     * @param string $uri Route URI pattern
     * @param mixed $action Controller action
     * @return Route
     */
    public function put(string $uri, mixed $action): Route;
    
    /**
     * DELETE route tanımla
     * 
     * @param string $uri Route URI pattern
     * @param mixed $action Controller action
     * @return Route
     */
    public function delete(string $uri, mixed $action): Route;
    
    /**
     * PATCH route tanımla
     * 
     * @param string $uri Route URI pattern
     * @param mixed $action Controller action
     * @return Route
     */
    public function patch(string $uri, mixed $action): Route;
    
    /**
     * OPTIONS route tanımla
     * 
     * @param string $uri Route URI pattern
     * @param mixed $action Controller action
     * @return Route
     */
    public function options(string $uri, mixed $action): Route;
    
    /**
     * Multiple HTTP methods için route tanımla
     * 
     * @param array<string> $methods HTTP methods (['GET', 'POST'])
     * @param string $uri Route URI pattern
     * @param mixed $action Controller action
     * @return Route
     */
    public function match(array $methods, string $uri, mixed $action): Route;
    
    /**
     * Tüm HTTP methods için route tanımla
     * 
     * @param string $uri Route URI pattern
     * @param mixed $action Controller action
     * @return Route
     */
    public function any(string $uri, mixed $action): Route;
    
    /**
     * RESTful resource route'ları otomatik tanımla
     * 
     * Otomatik route'lar:
     * GET    /resource           → index
     * POST   /resource           → store
     * GET    /resource/{id}      → show
     * PUT    /resource/{id}      → update
     * DELETE /resource/{id}      → destroy
     * 
     * @param string $name Resource name (posts)
     * @param string $controller Controller class name (PostController)
     * @param array<string> $options Options (['only' => ['index', 'show']])
     * @return array<Route> Oluşturulan route'ların array'i
     */
    public function resource(string $name, string $controller, array $options = []): array;
    
    /**
     * Route group tanımla
     * 
     * Options:
     * - prefix: URL prefix (/api/v1)
     * - middleware: Group middleware (['auth', 'throttle:60'])
     * - namespace: Controller namespace (Api\V1)
     * - name: Route name prefix (api.)
     * - domain: Domain constraint
     * 
     * @param array<string, mixed> $options Group options
     * @param Closure $callback Group içindeki route'ları tanımlamak için callback
     * @return void
     */
    public function group(array $options, Closure $callback): void;
    
    /**
     * Request'i eşleşen route'a match et
     * 
     * @param RequestInterface $request HTTP request
     * @return Route|null Eşleşen route veya null
     * @throws \Conduit\Routing\Exceptions\MethodNotAllowedException
     */
    public function matchRoute(RequestInterface $request): ?Route;
    
    /**
     * Named route'dan URL generate et
     * 
     * @param string $name Route name
     * @param array<string, mixed> $parameters Route parameters
     * @param bool $absolute Absolute URL generate et mi?
     * @return string Generated URL
     * @throws \Conduit\Routing\Exceptions\RouteNotFoundException
     */
    public function route(string $name, array $parameters = [], bool $absolute = false): string;
    
    /**
     * Route'un exist olup olmadığını kontrol et
     * 
     * @param string $name Route name
     * @return bool
     */
    public function hasRoute(string $name): bool;
    
    /**
     * Global middleware bind et
     * 
     * @param array<string>|string $middleware Middleware
     * @return self
     */
    public function middleware(array|string $middleware): self;
    
    /**
     * Tüm route'ları al
     * 
     * @return array<Route>
     */
    public function getRoutes(): array;
    
    /**
     * Route collection'ı temizle
     * 
     * @return void
     */
    public function clear(): void;
    
    /**
     * Route cache'ini yükle
     * 
     * @param string $cacheFile Cache file path
     * @return bool
     */
    public function loadCache(string $cacheFile): bool;
    
    /**
     * Route'ları cache'e yaz
     * 
     * @param string $cacheFile Cache file path
     * @return bool
     */
    public function cacheRoutes(string $cacheFile): bool;
}