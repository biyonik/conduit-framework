<?php

declare(strict_types=1);

namespace Conduit\Routing;

use Closure;
use Conduit\Http\Contracts\RequestInterface;
use Conduit\Routing\Contracts\RouterInterface;
use Conduit\Routing\Exceptions\RouteNotFoundException;
use Conduit\Routing\Exceptions\MethodNotAllowedException;
use InvalidArgumentException;

/**
 * Router Sınıfı
 * 
 * Framework'ün ana routing sınıfı. RESTful routing, route groups,
 * named routes ve middleware desteği sağlar.
 * 
 * Features:
 * - RESTful routing (GET, POST, PUT, DELETE, PATCH, OPTIONS)
 * - Route parameters (/users/{id}, /posts/{slug?})
 * - Named routes
 * - Route groups (prefix, middleware, namespace)
 * - Resource routing
 * - Route caching
 * - Middleware integration
 * - Method-based route indexing (performance)
 * 
 * @package Conduit\Routing
 */
class Router implements RouterInterface
{
    /** @var array<string, array<Route>> Method-based route collection */
    private array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
        'PATCH' => [],
        'OPTIONS' => [],
        'HEAD' => [],
    ];
    
    /** @var array<string, Route> Named routes lookup */
    private array $namedRoutes = [];
    
    /** @var array<string> Global middleware stack */
    private array $globalMiddleware = [];
    
    /** @var array<string, mixed> Current group attributes */
    private array $groupStack = [];
    
    /** @var array<Route> Compiled routes cache */
    private array $compiledRoutes = [];
    
    /** @var bool Route collection değişti mi? */
    private bool $routesChanged = true;
    
    /** @var array<CompiledRoute>|null Cached compiled routes for fast matching */
    private ?array $cachedCompiledRoutes = null;
    
    /** @var bool Is route cache enabled? */
    private bool $cacheEnabled = false;
    
    /**
     * GET route tanımla
     * 
     * @param string $uri Route URI pattern
     * @param mixed $action Controller action
     * @return Route
     */
    public function get(string $uri, mixed $action): Route
    {
        return $this->addRoute(['GET', 'HEAD'], $uri, $action);
    }
    
    /**
     * POST route tanımla
     * 
     * @param string $uri Route URI pattern
     * @param mixed $action Controller action
     * @return Route
     */
    public function post(string $uri, mixed $action): Route
    {
        return $this->addRoute(['POST'], $uri, $action);
    }
    
    /**
     * PUT route tanımla
     * 
     * @param string $uri Route URI pattern
     * @param mixed $action Controller action
     * @return Route
     */
    public function put(string $uri, mixed $action): Route
    {
        return $this->addRoute(['PUT'], $uri, $action);
    }
    
    /**
     * DELETE route tanımla
     * 
     * @param string $uri Route URI pattern
     * @param mixed $action Controller action
     * @return Route
     */
    public function delete(string $uri, mixed $action): Route
    {
        return $this->addRoute(['DELETE'], $uri, $action);
    }
    
    /**
     * PATCH route tanımla
     * 
     * @param string $uri Route URI pattern
     * @param mixed $action Controller action
     * @return Route
     */
    public function patch(string $uri, mixed $action): Route
    {
        return $this->addRoute(['PATCH'], $uri, $action);
    }
    
    /**
     * OPTIONS route tanımla
     * 
     * @param string $uri Route URI pattern
     * @param mixed $action Controller action
     * @return Route
     */
    public function options(string $uri, mixed $action): Route
    {
        return $this->addRoute(['OPTIONS'], $uri, $action);
    }
    
    /**
     * Multiple HTTP methods için route tanımla
     * 
     * @param array<string> $methods HTTP methods
     * @param string $uri Route URI pattern
     * @param mixed $action Controller action
     * @return Route
     */
    public function match(array $methods, string $uri, mixed $action): Route
    {
        return $this->addRoute(array_map('strtoupper', $methods), $uri, $action);
    }
    
    /**
     * Tüm HTTP methods için route tanımla
     * 
     * @param string $uri Route URI pattern
     * @param mixed $action Controller action
     * @return Route
     */
    public function any(string $uri, mixed $action): Route
    {
        return $this->addRoute(Route::SUPPORTED_METHODS, $uri, $action);
    }
    
    /**
     * RESTful resource routes otomatik tanımla
     * 
     * @param string $name Resource name
     * @param string $controller Controller class name
     * @param array<string, mixed> $options Options
     * @return array<Route>
     */
    public function resource(string $name, string $controller, array $options = []): array
    {
        $resourceRoutes = [
            'index'   => ['GET',    "/{$name}",           "{$controller}@index"],
            'create'  => ['GET',    "/{$name}/create",    "{$controller}@create"],
            'store'   => ['POST',   "/{$name}",           "{$controller}@store"],
            'show'    => ['GET',    "/{$name}/{{$name}}", "{$controller}@show"],
            'edit'    => ['GET',    "/{$name}/{{$name}}/edit", "{$controller}@edit"],
            'update'  => ['PUT',    "/{$name}/{{$name}}", "{$controller}@update"],
            'destroy' => ['DELETE', "/{$name}/{{$name}}", "{$controller}@destroy"],
        ];
        
        // Only/except filtering
        if (isset($options['only'])) {
            $resourceRoutes = array_intersect_key($resourceRoutes, array_flip($options['only']));
        }
        
        if (isset($options['except'])) {
            $resourceRoutes = array_diff_key($resourceRoutes, array_flip($options['except']));
        }
        
        $routes = [];
        
        foreach ($resourceRoutes as $method => [$httpMethod, $uri, $action]) {
            $route = $this->addRoute([$httpMethod], $uri, $action);
            
            // Named route: posts.index, posts.show, etc.
            $routeName = "{$name}.{$method}";
            $route = $route->name($routeName);
            $this->namedRoutes[$routeName] = $route;
            
            $routes[] = $route;
        }
        
        return $routes;
    }
    
    /**
     * Route group tanımla
     * 
     * @param array<string, mixed> $options Group options
     * @param Closure $callback Group callback
     * @return void
     */
    public function group(array $options, Closure $callback): void
    {
        $this->groupStack[] = $options;
        
        $callback($this);
        
        array_pop($this->groupStack);
    }
    
    /**
     * Request'i route'a match et
     * 
     * @param RequestInterface $request HTTP request
     * @return Route|null
     * @throws MethodNotAllowedException
     */
    public function matchRoute(RequestInterface $request): ?Route
    {
        $method = strtoupper($request->method());
        $uri = '/' . trim($request->path(), '/');
        
        // Use compiled cache if available (fast path)
        if ($this->cacheEnabled && $this->cachedCompiledRoutes !== null) {
            return $this->matchCompiledRoute($method, $uri);
        }
        
        // Fallback to dynamic matching (development mode)
        return $this->matchDynamicRoute($method, $uri);
    }
    
    /**
     * Match using compiled routes (fast path - production)
     * 
     * @param string $method HTTP method
     * @param string $uri Request URI
     * @return Route|null
     * @throws MethodNotAllowedException
     */
    protected function matchCompiledRoute(string $method, string $uri): ?Route
    {
        foreach ($this->cachedCompiledRoutes as $compiled) {
            $match = $compiled->matches($method, $uri);
            
            if ($match !== null) {
                // Reconstruct Route object
                $route = new Route($compiled->methods, $compiled->uri, $compiled->action);
                $route = $route->setParameters($match['parameters']);
                
                // Apply middleware
                if (!empty($compiled->middleware)) {
                    $route = $route->middleware($compiled->middleware);
                }
                
                // Apply name
                if ($compiled->name) {
                    $route = $route->name($compiled->name);
                }
                
                return $route;
            }
        }
        
        // Check if URI matches but method is wrong
        $allowedMethods = [];
        foreach ($this->cachedCompiledRoutes as $compiled) {
            if (preg_match($compiled->regex, $uri)) {
                $allowedMethods = array_merge($allowedMethods, $compiled->methods);
            }
        }
        
        if (!empty($allowedMethods)) {
            throw new MethodNotAllowedException(
                "Method {$method} not allowed. Allowed methods: " . implode(', ', array_unique($allowedMethods))
            );
        }
        
        return null;
    }
    
    /**
     * Match using dynamic routes (development mode)
     * 
     * @param string $method HTTP method
     * @param string $uri Request URI
     * @return Route|null
     * @throws MethodNotAllowedException
     */
    protected function matchDynamicRoute(string $method, string $uri): ?Route
    {
        // İlk önce exact match dene (performance)
        $candidateRoutes = $this->routes[$method] ?? [];
        
        foreach ($candidateRoutes as $route) {
            if ($this->matchRoutePattern($route, $uri)) {
                return $route;
            }
        }
        
        // Method not allowed kontrolü
        $allMethods = [];
        foreach ($this->routes as $routeMethod => $routes) {
            foreach ($routes as $route) {
                if ($this->matchRoutePattern($route, $uri)) {
                    $allMethods = array_merge($allMethods, $route->getMethods());
                }
            }
        }
        
        if (!empty($allMethods)) {
            throw new MethodNotAllowedException(
                "Method {$method} not allowed. Allowed methods: " . implode(', ', array_unique($allMethods))
            );
        }
        
        return null;
    }
    
    /**
     * Named route'dan URL generate et
     * 
     * @param string $name Route name
     * @param array<string, mixed> $parameters Route parameters
     * @param bool $absolute Absolute URL generate et mi?
     * @return string
     * @throws RouteNotFoundException
     */
    public function route(string $name, array $parameters = [], bool $absolute = false): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new RouteNotFoundException("Named route not found: {$name}");
        }
        
        $route = $this->namedRoutes[$name];
        $uri = $route->getUri();
        
        // Parameter replacement
        foreach ($parameters as $key => $value) {
            $uri = str_replace("{{$key}}", (string) $value, $uri);
            $uri = str_replace("{{$key}?}", (string) $value, $uri);
        }
        
        // Remove optional parameters that weren't provided
        $uri = preg_replace('/\{[^}]+\?\}/', '', $uri);
        
        // Clean up multiple slashes
        $uri = preg_replace('#/+#', '/', $uri);
        
        if ($absolute) {
            // TODO: Get base URL from config/request
            $baseUrl = 'http://localhost'; // Placeholder
            return $baseUrl . $uri;
        }
        
        return $uri;
    }
    
    /**
     * Route exist kontrolü
     * 
     * @param string $name Route name
     * @return bool
     */
    public function hasRoute(string $name): bool
    {
        return isset($this->namedRoutes[$name]);
    }
    
    /**
     * Global middleware bind et
     * 
     * @param array<string>|string $middleware Middleware
     * @return self
     */
    public function middleware(array|string $middleware): self
    {
        $middlewareArray = is_array($middleware) ? $middleware : [$middleware];
        $this->globalMiddleware = array_merge($this->globalMiddleware, $middlewareArray);
        
        return $this;
    }
    
    /**
     * Tüm route'ları al
     * 
     * @return array<Route>
     */
    public function getRoutes(): array
    {
        $routes = [];
        
        foreach ($this->routes as $methodRoutes) {
            $routes = array_merge($routes, $methodRoutes);
        }
        
        return array_unique($routes, SORT_REGULAR);
    }
    
    /**
     * Route collection temizle
     * 
     * @return void
     */
    public function clear(): void
    {
        $this->routes = array_fill_keys(array_keys($this->routes), []);
        $this->namedRoutes = [];
        $this->globalMiddleware = [];
        $this->groupStack = [];
        $this->compiledRoutes = [];
        $this->routesChanged = true;
    }
    
    /**
     * Load routes from compiled cache
     * 
     * @param string $path Cache file path
     * @return void
     */
    public function loadCompiledCache(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        
        $cached = require $path;
        
        if (!is_array($cached) || !isset($cached['routes'])) {
            return;
        }
        
        $this->cachedCompiledRoutes = array_map(
            fn($data) => CompiledRoute::fromArray($data),
            $cached['routes']
        );
        
        $this->cacheEnabled = true;
    }
    
    /**
     * Check if cache is enabled
     * 
     * @return bool
     */
    public function isCached(): bool
    {
        return $this->cacheEnabled;
    }
    
    /**
     * Route cache'ini yükle (legacy method - kept for backward compatibility)
     * 
     * @param string $cacheFile Cache file path
     * @return bool
     */
    public function loadCache(string $cacheFile): bool
    {
        if (!file_exists($cacheFile)) {
            return false;
        }
        
        $cached = include $cacheFile;
        
        if (!is_array($cached) || !isset($cached['routes'], $cached['namedRoutes'])) {
            return false;
        }
        
        $this->routes = $cached['routes'];
        $this->namedRoutes = $cached['namedRoutes'];
        $this->routesChanged = false;
        
        return true;
    }
    
    /**
     * Route'ları cache'e yaz
     * 
     * @param string $cacheFile Cache file path
     * @return bool
     */
    public function cacheRoutes(string $cacheFile): bool
    {
        $cacheDir = dirname($cacheFile);
        
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $cacheData = [
            'routes' => $this->routes,
            'namedRoutes' => $this->namedRoutes,
            'generated' => time(),
        ];
        
        $content = "<?php\n\nreturn " . var_export($cacheData, true) . ";\n";
        
        return file_put_contents($cacheFile, $content) !== false;
    }
    
    /**
     * Route ekle (internal method)
     * 
     * @param array<string> $methods HTTP methods
     * @param string $uri URI pattern
     * @param mixed $action Controller action
     * @return Route
     */
    private function addRoute(array $methods, string $uri, mixed $action): Route
    {
        // Group attributes uygula
        $uri = $this->applyGroupPrefix($uri);
        $middleware = $this->applyGroupMiddleware();
        $action = $this->applyGroupNamespace($action);
        
        // Route oluştur
        $route = new Route($methods, $uri, $action, $middleware);
        
        // Method-based indexing için route'u ekle
        foreach ($methods as $method) {
            $this->routes[$method][] = $route;
        }
        
        $this->routesChanged = true;
        
        return $route;
    }
    
    /**
     * Route pattern matching
     * 
     * @param Route $route Route object
     * @param string $uri Request URI
     * @return bool
     */
    private function matchRoutePattern(Route $route, string $uri): bool
    {
        $pattern = $route->getUri();
        
        // Exact match kontrolü (performance optimization)
        if ($pattern === $uri) {
            return true;
        }
        
        // Parameter pattern'i regex'e çevir
        $regex = $this->compileRoute($route);
        
        if (preg_match($regex, $uri, $matches)) {
            // Parameter'leri extract et
            $parameters = [];
            $parameterNames = $route->getParameterNames();
            
            for ($i = 1; $i < count($matches); $i++) {
                if (isset($parameterNames[$i - 1])) {
                    $parameters[$parameterNames[$i - 1]] = $matches[$i];
                }
            }
            
            // Route'a parameter'leri set et
            $route = $route->setParameters($parameters);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Route'u regex pattern'e compile et
     * 
     * @param Route $route Route object
     * @return string Compiled regex
     */
    private function compileRoute(Route $route): string
    {
        $compiledRegex = $route->getCompiledRegex();
        
        if ($compiledRegex !== null) {
            return $compiledRegex;
        }
        
        $pattern = $route->getUri();
        $constraints = $route->getConstraints();
        
        // Parameter pattern'lerini regex'e çevir
        $pattern = preg_replace_callback('/\{([^}?]+)(\?)?\}/', function ($matches) use ($constraints) {
            $paramName = $matches[1];
            $isOptional = isset($matches[2]);
            
            // Constraint varsa kullan, yoksa default
            $constraint = $constraints[$paramName] ?? '[^/]+';
            
            if ($isOptional) {
                return "({$constraint})?";
            }
            
            return "({$constraint})";
        }, $pattern);
        
        // Regex delimiters ve anchors ekle
        $regex = '#^' . str_replace('#', '\#', $pattern) . '$#';
        
        // Route'a compiled regex'i cache'le
        $route = $route->setCompiledRegex($regex);
        
        return $regex;
    }
    
    /**
     * Group prefix uygula
     * 
     * @param string $uri Original URI
     * @return string Modified URI
     */
    private function applyGroupPrefix(string $uri): string
    {
        $prefix = '';
        
        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
        }
        
        return '/' . trim($prefix . '/' . trim($uri, '/'), '/');
    }
    
    /**
     * Group middleware uygula
     * 
     * @return array<string> Merged middleware
     */
    private function applyGroupMiddleware(): array
    {
        $middleware = $this->globalMiddleware;
        
        foreach ($this->groupStack as $group) {
            if (isset($group['middleware'])) {
                $groupMiddleware = is_array($group['middleware']) 
                    ? $group['middleware'] 
                    : [$group['middleware']];
                    
                $middleware = array_merge($middleware, $groupMiddleware);
            }
        }
        
        return array_unique($middleware);
    }
    
    /**
     * Group namespace uygula
     * 
     * @param mixed $action Original action
     * @return mixed Modified action
     */
    private function applyGroupNamespace(mixed $action): mixed
    {
        if (!is_string($action) || !str_contains($action, '@')) {
            return $action;
        }
        
        $namespace = '';
        
        foreach ($this->groupStack as $group) {
            if (isset($group['namespace'])) {
                $namespace .= '\\' . trim($group['namespace'], '\\');
            }
        }
        
        if ($namespace) {
            [$controller, $method] = explode('@', $action, 2);
            $controller = ltrim($namespace . '\\' . $controller, '\\');
            $action = $controller . '@' . $method;
        }
        
        return $action;
    }
}