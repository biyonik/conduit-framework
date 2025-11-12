<?php

declare(strict_types=1);

namespace Conduit\Middleware;

use Closure;
use Conduit\Core\Contracts\ContainerInterface;
use Conduit\Http\Contracts\RequestInterface;
use Conduit\Http\Contracts\ResponseInterface;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

/**
 * Middleware Pipeline
 * 
 * Onion pattern implementation. Request'i middleware stack'inden geçirir.
 * 
 * Flow:
 * Request → MW1 → MW2 → MW3 → Destination (Controller)
 *                                    ↓
 * Response ← MW1 ← MW2 ← MW3 ← Return
 * 
 * Her middleware:
 * - Request'i BEFORE phase'de işler
 * - Next'i çağırır (chain devam eder)
 * - Response'u AFTER phase'de işler
 * 
 * @package Conduit\Middleware
 */
class MiddlewarePipeline
{
    /** @var RequestInterface Request object */
    private ?RequestInterface $request = null;
    
    /** @var array<string> Middleware stack */
    private array $middleware = [];
    
    /** @var Closure|null Final destination (controller) */
    private ?Closure $destination = null;
    
    /** @var array<MiddlewareInterface> Resolved middleware instances */
    private array $resolvedMiddleware = [];
    
    /**
     * Constructor
     * 
     * @param ContainerInterface $container DI Container
     */
    public function __construct(
        private readonly ContainerInterface $container
    ) {}
    
    /**
     * Request'i pipeline'a gönder
     * 
     * @param RequestInterface $request HTTP request
     * @return self
     */
    public function send(RequestInterface $request): self
    {
        $this->request = $request;
        return $this;
    }
    
    /**
     * Middleware stack set et
     * 
     * @param array<string> $middleware Middleware array
     * @return self
     */
    public function through(array $middleware): self
    {
        $this->middleware = $middleware;
        $this->resolvedMiddleware = []; // Reset cache
        return $this;
    }
    
    /**
     * Final destination set et (controller closure)
     * 
     * @param Closure $destination Final destination closure
     * @return self
     */
    public function then(Closure $destination): self
    {
        $this->destination = $destination;
        return $this;
    }
    
    /**
     * Pipeline'ı çalıştır ve response döndür
     * 
     * @return ResponseInterface
     * @throws InvalidArgumentException
     */
    public function process(): ResponseInterface
    {
        if ($this->request === null) {
            throw new InvalidArgumentException('Request not set. Call send() first.');
        }
        
        if ($this->destination === null) {
            throw new InvalidArgumentException('Destination not set. Call then() first.');
        }
        
        // Middleware'leri resolve et
        $this->resolveMiddleware();
        
        // Onion pattern: Her middleware'i bir closure'a wrap et
        $pipeline = array_reduce(
            array_reverse($this->resolvedMiddleware),
            $this->createLayer(),
            $this->destination
        );
        
        // Pipeline'ı çalıştır
        return $pipeline($this->request);
    }
    
    /**
     * Middleware layer factory
     * 
     * Bu method her middleware için bir closure döner.
     * Closure, middleware'i handle eder ve next'i çağırır.
     * 
     * @return Closure
     */
    private function createLayer(): Closure
    {
        return function (Closure $next) {
            return function (MiddlewareInterface $middleware) use ($next) {
                return function (RequestInterface $request) use ($middleware, $next) {
                    return $middleware->handle($request, $next);
                };
            };
        };
    }
    
    /**
     * Middleware string'lerini instance'lara resolve et
     * 
     * @return void
     */
    private function resolveMiddleware(): void
    {
        if (!empty($this->resolvedMiddleware)) {
            return; // Already resolved
        }
        
        foreach ($this->middleware as $middleware) {
            $this->resolvedMiddleware[] = $this->resolveMiddlewareString($middleware);
        }
    }
    
    /**
     * Middleware string'ini instance'a resolve et
     * 
     * Format örnekleri:
     * - 'auth' → AuthMiddleware::class
     * - 'throttle:60,1' → ThrottleMiddleware::class with parameters
     * - 'App\Middleware\CustomMiddleware' → Direct class reference
     * - 'custom:param1,param2' → Custom middleware with parameters
     * 
     * @param string $middleware Middleware string
     * @return MiddlewareInterface
     * @throws InvalidArgumentException
     */
    private function resolveMiddlewareString(string $middleware): MiddlewareInterface
    {
        // Parameter parsing (throttle:60,1)
        $parameters = [];
        
        if (str_contains($middleware, ':')) {
            [$middleware, $paramString] = explode(':', $middleware, 2);
            $parameters = explode(',', $paramString);
        }
        
        // Middleware alias mapping
        $middlewareClass = $this->resolveMiddlewareAlias($middleware);
        
        // Container'dan instance al
        try {
            $instance = $this->container->make($middlewareClass);
        } catch (\Exception $e) {
            throw new InvalidArgumentException(
                "Failed to resolve middleware: {$middleware}. Error: {$e->getMessage()}"
            );
        }
        
        // MiddlewareInterface kontrolü
        if (!$instance instanceof MiddlewareInterface) {
            throw new InvalidArgumentException(
                "Middleware {$middlewareClass} must implement MiddlewareInterface"
            );
        }
        
        // Parameter injection (eğer middleware parameterized ise)
        if (!empty($parameters)) {
            $instance = $this->injectParameters($instance, $parameters);
        }
        
        return $instance;
    }
    
    /**
     * Middleware alias'ını class name'e resolve et
     * 
     * Built-in middleware mapping'i burada yapılır.
     * 
     * @param string $alias Middleware alias
     * @return string Full class name
     */
    private function resolveMiddlewareAlias(string $alias): string
    {
        // Built-in middleware mapping
        $builtInMiddleware = [
            'auth' => 'Conduit\\Security\\Middleware\\AuthMiddleware',
            'guest' => 'Conduit\\Security\\Middleware\\GuestMiddleware',
            'throttle' => 'Conduit\\Middleware\\BuiltIn\\ThrottleMiddleware',
            'cors' => 'Conduit\\Middleware\\BuiltIn\\CorsMiddleware',
            'json' => 'Conduit\\Middleware\\BuiltIn\\JsonOnlyMiddleware',
            'trim' => 'Conduit\\Middleware\\BuiltIn\\TrimStringsMiddleware',
            'convert_empty' => 'Conduit\\Middleware\\BuiltIn\\ConvertEmptyStringsToNull',
        ];
        
        // Alias varsa return et
        if (isset($builtInMiddleware[$alias])) {
            return $builtInMiddleware[$alias];
        }
        
        // Full class name olarak kabul et
        if (class_exists($alias)) {
            return $alias;
        }
        
        // App namespace'de ara
        $appMiddleware = "App\\Middleware\\{$alias}";
        if (class_exists($appMiddleware)) {
            return $appMiddleware;
        }
        
        throw new InvalidArgumentException("Middleware not found: {$alias}");
    }
    
    /**
     * Middleware'e parameter inject et
     * 
     * Middleware sınıfında setParameters() method'u varsa çağırır.
     * Yoksa reflection ile özellik set etmeye çalışır.
     * 
     * @param MiddlewareInterface $middleware Middleware instance
     * @param array<string> $parameters Parameter array
     * @return MiddlewareInterface
     */
    private function injectParameters(MiddlewareInterface $middleware, array $parameters): MiddlewareInterface
    {
        try {
            // setParameters method'u varsa direkt çağır (interface method)
            $middleware->setParameters($parameters);
            return $middleware;
            
        } catch (\Error $e) {
            // Method implemented değil, reflection ile dene
            try {
                $reflection = new ReflectionClass($middleware);
                
                // Parameters property'si varsa set et
                if ($reflection->hasProperty('parameters')) {
                    $property = $reflection->getProperty('parameters');
                    $property->setAccessible(true);
                    $property->setValue($middleware, $parameters);
                    return $middleware;
                }
                
                // Specific parameter method'ları dene (setLimit, setAttempts, etc.)
                foreach ($parameters as $index => $value) {
                    $methodNames = [
                        "setParameter{$index}",
                        'setLimit',
                        'setAttempts', 
                        'setDecayMinutes',
                        'setMaxAttempts',
                    ];
                    
                    foreach ($methodNames as $methodName) {
                        if ($reflection->hasMethod($methodName)) {
                            $middleware->$methodName($value);
                            break 2; // Break both loops
                        }
                    }
                }
                
            } catch (ReflectionException $reflectionE) {
                // Parameter injection tamamen failed, ama middleware kullanılabilir
                // Bu durum log'lanabilir debugging için
            }
        }
        
        return $middleware;
    }
    
    /**
     * Static factory method
     * 
     * @param ContainerInterface $container DI Container
     * @return self
     */
    public static function create(ContainerInterface $container): self
    {
        return new self($container);
    }
    
    /**
     * Fluent interface: Quick pipeline execution
     * 
     * @param ContainerInterface $container DI Container
     * @param RequestInterface $request HTTP request
     * @param array<string> $middleware Middleware stack
     * @param Closure $destination Final destination
     * @return ResponseInterface
     */
    public static function execute(
        ContainerInterface $container,
        RequestInterface $request,
        array $middleware,
        Closure $destination
    ): ResponseInterface {
        return (new self($container))
            ->send($request)
            ->through($middleware)
            ->then($destination)
            ->process();
    }
}