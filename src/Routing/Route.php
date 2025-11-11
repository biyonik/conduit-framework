<?php

declare(strict_types=1);

namespace Conduit\Routing;

use Closure;
use InvalidArgumentException;

/**
 * Route Sınıfı
 * 
 * Tekil route tanımını temsil eder. Immutable design pattern kullanır.
 * Her değişiklik yeni Route instance döner (PSR-7 yaklaşımı).
 * 
 * Özellikler:
 * - HTTP methods (GET, POST, PUT, DELETE, PATCH, OPTIONS)
 * - URI pattern (/users/{id}, /posts/{slug?})
 * - Controller/Closure action
 * - Middleware stack
 * - Named routes
 * - Parameter constraints
 * - Domain constraints (subdomain routing)
 * 
 * @package Conduit\Routing
 */
class Route
{
    /** @var array<string> Desteklenen HTTP methods */
    public const SUPPORTED_METHODS = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];
    
    /**
     * Route constructor
     * 
     * @param array<string> $methods HTTP methods (['GET', 'POST'])
     * @param string $uri URI pattern (/users/{id})
     * @param mixed $action Controller@method, Closure, veya invokable class
     * @param array<string> $middleware Middleware array (['auth', 'throttle:60'])
     * @param string|null $name Route name (users.show)
     * @param array<string, string> $constraints Parameter constraints (['id' => '[0-9]+'])
     * @param array<string, mixed> $defaults Default parameter values (['page' => 1])
     * @param string|null $domain Domain constraint (api.example.com)
     * @param array<string, mixed> $parameters Extracted parameters (runtime)
     * @param string|null $compiledRegex Compiled regex pattern (cache)
     */
    public function __construct(
        private readonly array $methods,
        private readonly string $uri,
        private readonly mixed $action,
        private readonly array $middleware = [],
        private readonly ?string $name = null,
        private readonly array $constraints = [],
        private readonly array $defaults = [],
        private readonly ?string $domain = null,
        private readonly array $parameters = [],
        private readonly ?string $compiledRegex = null
    ) {
        $this->validateMethods($methods);
        $this->validateUri($uri);
        $this->validateAction($action);
    }
    
    /**
     * HTTP methods getter
     * 
     * @return array<string>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }
    
    /**
     * URI pattern getter
     * 
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }
    
    /**
     * Action getter (Controller@method veya Closure)
     * 
     * @return mixed
     */
    public function getAction(): mixed
    {
        return $this->action;
    }
    
    /**
     * Middleware stack getter
     * 
     * @return array<string>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }
    
    /**
     * Route name getter
     * 
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }
    
    /**
     * Parameter constraints getter
     * 
     * @return array<string, string>
     */
    public function getConstraints(): array
    {
        return $this->constraints;
    }
    
    /**
     * Default values getter
     * 
     * @return array<string, mixed>
     */
    public function getDefaults(): array
    {
        return $this->defaults;
    }
    
    /**
     * Domain constraint getter
     * 
     * @return string|null
     */
    public function getDomain(): ?string
    {
        return $this->domain;
    }
    
    /**
     * Runtime parameters getter (extracted from URI)
     * 
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
    
    /**
     * Compiled regex getter
     * 
     * @return string|null
     */
    public function getCompiledRegex(): ?string
    {
        return $this->compiledRegex;
    }
    
    /**
     * Middleware atama (immutable)
     * 
     * @param array<string>|string $middleware Middleware array veya string
     * @return self Yeni Route instance
     */
    public function middleware(array|string $middleware): self
    {
        $middlewareArray = is_array($middleware) ? $middleware : [$middleware];
        $newMiddleware = array_merge($this->middleware, $middlewareArray);
        
        return new self(
            $this->methods,
            $this->uri,
            $this->action,
            array_unique($newMiddleware), // Duplicate middleware'leri temizle
            $this->name,
            $this->constraints,
            $this->defaults,
            $this->domain,
            $this->parameters,
            $this->compiledRegex
        );
    }
    
    /**
     * Route name atama (immutable)
     * 
     * @param string $name Route name
     * @return self Yeni Route instance
     */
    public function name(string $name): self
    {
        return new self(
            $this->methods,
            $this->uri,
            $this->action,
            $this->middleware,
            $name,
            $this->constraints,
            $this->defaults,
            $this->domain,
            $this->parameters,
            $this->compiledRegex
        );
    }
    
    /**
     * Parameter constraint atama (immutable)
     * 
     * Example: $route->where('id', '[0-9]+')->where('slug', '[a-z0-9-]+')
     * 
     * @param array<string, string>|string $name Parameter name veya constraint array
     * @param string|null $pattern Regex pattern
     * @return self Yeni Route instance
     */
    public function where(array|string $name, ?string $pattern = null): self
    {
        $newConstraints = $this->constraints;
        
        if (is_array($name)) {
            $newConstraints = array_merge($newConstraints, $name);
        } else {
            if ($pattern === null) {
                throw new InvalidArgumentException('Pattern is required when name is string');
            }
            $newConstraints[$name] = $pattern;
        }
        
        return new self(
            $this->methods,
            $this->uri,
            $this->action,
            $this->middleware,
            $this->name,
            $newConstraints,
            $this->defaults,
            $this->domain,
            $this->parameters,
            $this->compiledRegex
        );
    }
    
    /**
     * Default parameter values atama (immutable)
     * 
     * @param array<string, mixed> $defaults Default values
     * @return self Yeni Route instance
     */
    public function defaults(array $defaults): self
    {
        $newDefaults = array_merge($this->defaults, $defaults);
        
        return new self(
            $this->methods,
            $this->uri,
            $this->action,
            $this->middleware,
            $this->name,
            $this->constraints,
            $newDefaults,
            $this->domain,
            $this->parameters,
            $this->compiledRegex
        );
    }
    
    /**
     * Domain constraint atama (immutable)
     * 
     * @param string $domain Domain pattern (api.example.com, {subdomain}.example.com)
     * @return self Yeni Route instance
     */
    public function domain(string $domain): self
    {
        return new self(
            $this->methods,
            $this->uri,
            $this->action,
            $this->middleware,
            $this->name,
            $this->constraints,
            $this->defaults,
            $domain,
            $this->parameters,
            $this->compiledRegex
        );
    }
    
    /**
     * Runtime parameters set et (immutable)
     * 
     * Bu method Router tarafından matching sırasında kullanılır
     * 
     * @param array<string, mixed> $parameters Extracted parameters
     * @return self Yeni Route instance
     */
    public function setParameters(array $parameters): self
    {
        return new self(
            $this->methods,
            $this->uri,
            $this->action,
            $this->middleware,
            $this->name,
            $this->constraints,
            $this->defaults,
            $this->domain,
            $parameters,
            $this->compiledRegex
        );
    }
    
    /**
     * Compiled regex set et (immutable)
     * 
     * Performance için RouteCompiler tarafından set edilir
     * 
     * @param string $regex Compiled regex pattern
     * @return self Yeni Route instance
     */
    public function setCompiledRegex(string $regex): self
    {
        return new self(
            $this->methods,
            $this->uri,
            $this->action,
            $this->middleware,
            $this->name,
            $this->constraints,
            $this->defaults,
            $this->domain,
            $this->parameters,
            $regex
        );
    }
    
    /**
     * Route'un belirtilen method'u support edip etmediğini kontrol et
     * 
     * @param string $method HTTP method
     * @return bool
     */
    public function hasMethod(string $method): bool
    {
        return in_array(strtoupper($method), $this->methods, true);
    }
    
    /**
     * Route'un middleware'e sahip olup olmadığını kontrol et
     * 
     * @param string $middleware Middleware name
     * @return bool
     */
    public function hasMiddleware(string $middleware): bool
    {
        return in_array($middleware, $this->middleware, true);
    }
    
    /**
     * Route'un belirtilen parameter'e sahip olup olmadığını kontrol et
     * 
     * @param string $parameter Parameter name
     * @return bool
     */
    public function hasParameter(string $parameter): bool
    {
        return array_key_exists($parameter, $this->parameters);
    }
    
    /**
     * Parameter value getter
     * 
     * @param string $name Parameter name
     * @param mixed $default Default value
     * @return mixed
     */
    public function parameter(string $name, mixed $default = null): mixed
    {
        return $this->parameters[$name] ?? $this->defaults[$name] ?? $default;
    }
    
    /**
     * Route pattern'ından parameter isimlerini extract et
     * 
     * Example: /users/{id}/posts/{post?} → ['id', 'post']
     * 
     * @return array<string>
     */
    public function getParameterNames(): array
    {
        preg_match_all('/\{([^}?]+)\??}/', $this->uri, $matches);
        return $matches[1] ?? [];
    }
    
    /**
     * Route'un optional parameter'leri olup olmadığını kontrol et
     * 
     * @return bool
     */
    public function hasOptionalParameters(): bool
    {
        return str_contains($this->uri, '?}');
    }
    
    /**
     * HTTP methods validation
     * 
     * @param array<string> $methods
     * @throws InvalidArgumentException
     */
    private function validateMethods(array $methods): void
    {
        if (empty($methods)) {
            throw new InvalidArgumentException('Route must have at least one HTTP method');
        }
        
        foreach ($methods as $method) {
            if (!in_array(strtoupper($method), self::SUPPORTED_METHODS, true)) {
                throw new InvalidArgumentException("Unsupported HTTP method: {$method}");
            }
        }
    }
    
    /**
     * URI validation
     * 
     * @param string $uri
     * @throws InvalidArgumentException
     */
    private function validateUri(string $uri): void
    {
        if (empty($uri)) {
            throw new InvalidArgumentException('Route URI cannot be empty');
        }
        
        // URI must start with /
        if (!str_starts_with($uri, '/')) {
            throw new InvalidArgumentException('Route URI must start with /');
        }
    }
    
    /**
     * Action validation
     * 
     * @param mixed $action
     * @throws InvalidArgumentException
     */
    private function validateAction(mixed $action): void
    {
        if (is_null($action)) {
            throw new InvalidArgumentException('Route action cannot be null');
        }
        
        // String action format: Controller@method
        if (is_string($action) && !str_contains($action, '@') && !class_exists($action)) {
            throw new InvalidArgumentException(
                'String action must be in format Controller@method or be a valid class name'
            );
        }
        
        // Closure action
        if ($action instanceof Closure) {
            return; // Valid
        }
        
        // Class action (invokable)
        if (is_string($action) && class_exists($action)) {
            return; // Valid
        }
        
        // Controller@method action
        if (is_string($action) && str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action, 2);
            if (!class_exists($controller)) {
                throw new InvalidArgumentException("Controller class does not exist: {$controller}");
            }
            return; // Valid (method existence will be checked at runtime)
        }
        
        throw new InvalidArgumentException('Invalid route action type');
    }
}