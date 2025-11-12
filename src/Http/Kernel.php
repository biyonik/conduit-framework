<?php

declare(strict_types=1);

namespace Conduit\Http;

use Conduit\Core\Application;
use Conduit\Core\Contracts\ContainerInterface;
use Conduit\Http\Contracts\KernelInterface;
use Conduit\Http\Contracts\RequestInterface;
use Conduit\Http\Contracts\ResponseInterface;
use Conduit\Http\Exceptions\HttpException;
use Throwable;
use Closure;

/**
 * HTTP Kernel
 * 
 * HTTP request lifecycle'ını orkestra eder:
 * Request → Middleware → Router → Controller → Response
 * 
 * @package Conduit\Http
 */
class Kernel implements KernelInterface
{
    /**
     * Application instance
     */
    protected Application $app;

    /**
     * Container instance
     */
    protected ContainerInterface $container;

    /**
     * Global middleware stack (her request'te çalışır)
     */
    protected array $middleware = [];

    /**
     * Middleware groups (route'larda kullanılır)
     */
    protected array $middlewareGroups = [];

    /**
     * Route middleware aliases
     */
    protected array $middlewareAliases = [];

    /**
     * Bootstrap edildi mi?
     */
    protected bool $bootstrapped = false;

    /**
     * Constructor
     * 
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->container = $app->getContainer();

        // Default middleware'leri yükle
        $this->registerDefaultMiddleware();
    }

    /**
     * Default middleware'leri kaydet
     * 
     * @return void
     */
    protected function registerDefaultMiddleware(): void
    {
        // Global middleware (sıralama önemli!)
        // Bu middleware'ler her request'te çalışır
        
        // İleride eklenecek:
        // $this->middleware = [
        //     \Conduit\Http\Middleware\TrimStrings::class,
        //     \Conduit\Http\Middleware\ConvertEmptyStringsToNull::class,
        // ];

        // Middleware groups
        $this->middlewareGroups = [
            'api' => [
                // İleride eklenecek:
                // 'throttle:60,1',
                // 'json.only',
            ],
            
            'web' => [
                // İleride eklenecek (eğer web routes eklersek):
                // 'csrf',
                // 'session',
            ],
        ];

        // Middleware aliases
        $this->middlewareAliases = [
            // İleride eklenecek:
            // 'auth' => \Conduit\Http\Middleware\Authenticate::class,
            // 'throttle' => \Conduit\Http\Middleware\ThrottleRequests::class,
            // 'json.only' => \Conduit\Http\Middleware\JsonOnly::class,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function handle(RequestInterface $request): ResponseInterface
    {
        try {
            // Bootstrap (ilk request'te)
            if (!$this->bootstrapped) {
                $this->bootstrap();
            }

            // Request'i router'a gönder
            $response = $this->sendRequestThroughRouter($request);

        } catch (Throwable $e) {
            // Exception'ı yakala ve response'a çevir
            $response = $this->handleException($e, $request);
        }

        return $response;
    }

    /**
     * Bootstrap işlemleri
     * 
     * @return void
     */
    protected function bootstrap(): void
    {
        // Application bootstrap (environment, config, providers, etc.)
        $this->app->bootstrap();

        $this->bootstrapped = true;
    }

    /**
     * {@inheritDoc}
     */
    public function sendRequestThroughRouter(RequestInterface $request): ResponseInterface
    {
        // Request'i container'a bind et (middleware'lerde kullanılabilir)
        $this->container->instance(RequestInterface::class, $request);
        $this->container->instance(Request::class, $request);

        // Global middleware pipeline oluştur
        $pipeline = $this->buildMiddlewarePipeline($request);

        // Pipeline'ı çalıştır
        return $pipeline($request);
    }

    /**
     * Middleware pipeline oluştur
     * 
     * Onion pattern: Her middleware bir katman
     * Request → MW1 → MW2 → MW3 → Router → MW3 → MW2 → MW1 → Response
     * 
     * @param RequestInterface $request
     * @return Closure
     */
    protected function buildMiddlewarePipeline(RequestInterface $request): Closure
    {
        // Core handler: Router'a dispatch et
        $core = function (RequestInterface $request): ResponseInterface {
            return $this->dispatchToRouter($request);
        };

        // Middleware'leri reverse order'da wrap et
        // (Son middleware ilk çalışacak şekilde)
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            $this->wrapMiddleware(),
            $core
        );

        return $pipeline;
    }

    /**
     * Middleware wrapper closure
     * 
     * @return Closure
     */
    protected function wrapMiddleware(): Closure
    {
        return function (Closure $next, string $middleware): Closure {
            return function (RequestInterface $request) use ($next, $middleware): ResponseInterface {
                // Middleware'i resolve et
                $instance = $this->resolveMiddleware($middleware);

                // Middleware'in handle metodunu çağır
                return $instance->handle($request, $next);
            };
        };
    }

    /**
     * Request'i router'a dispatch et
     * 
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    protected function dispatchToRouter(RequestInterface $request): ResponseInterface
    {
        // Router'ı container'dan al
        // Router henüz oluşturulmadı, ama ileride eklenecek
        
        // Şimdilik basit bir response dön (placeholder)
        // TODO: Router implementasyonu eklendiğinde bu değişecek
        
        return new JsonResponse([
            'message' => 'Kernel is working! Router not yet implemented.',
            'method' => $request->method(),
            'path' => $request->path(),
            'timestamp' => time(),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function terminate(RequestInterface $request, ResponseInterface $response): void
    {
        // Terminable middleware'leri çalıştır
        // Response gönderildikten SONRA çalışacak işlemler:
        // - Session save
        // - Log write
        // - Analytics
        // - Cache cleanup
        // - Background job trigger

        foreach ($this->middleware as $middleware) {
            $instance = $this->resolveMiddleware($middleware);

            // Eğer middleware terminate() metoduna sahipse çalıştır
            if (method_exists($instance, 'terminate')) {
                $instance->terminate($request, $response);
            }
        }

        // Application terminate
        $this->app->terminate();
    }

    /**
     * {@inheritDoc}
     */
    public function getGlobalMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * {@inheritDoc}
     */
    public function pushGlobalMiddleware(string $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function middlewareGroup(string $name, array $middleware): self
    {
        $this->middlewareGroups[$name] = $middleware;
        return $this;
    }

    /**
     * Middleware group al
     * 
     * @param string $name Group name
     * @return array Middleware listesi
     */
    public function getMiddlewareGroup(string $name): array
    {
        return $this->middlewareGroups[$name] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function middlewareAlias(string $name, string $middleware): self
    {
        $this->middlewareAliases[$name] = $middleware;
        return $this;
    }

    /**
     * Middleware alias al
     * 
     * @param string $name Alias name
     * @return string|null Middleware class
     */
    public function getMiddlewareAlias(string $name): ?string
    {
        return $this->middlewareAliases[$name] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function resolveMiddleware(string $middleware): object
    {
        // Middleware parametreleri parse et
        // Örnek: 'throttle:60,1' → class: throttle, params: [60, 1]
        [$name, $parameters] = $this->parseMiddleware($middleware);

        // Alias check
        if (isset($this->middlewareAliases[$name])) {
            $name = $this->middlewareAliases[$name];
        }

        // Class check (fully qualified class name mi?)
        if (!class_exists($name)) {
            throw new \RuntimeException("Middleware not found: {$name}");
        }

        // Container'dan resolve et (dependency injection)
        $instance = $this->container->make($name);

        // Eğer parametreler varsa set et
        if (!empty($parameters) && method_exists($instance, 'setParameters')) {
            $instance->setParameters($parameters);
        }

        return $instance;
    }

    /**
     * Middleware string'ini parse et
     * 
     * Örnek: 'throttle:60,1' → ['throttle', [60, 1]]
     * 
     * @param string $middleware
     * @return array [name, parameters]
     */
    protected function parseMiddleware(string $middleware): array
    {
        // ':' ile parametre ayırma
        if (!str_contains($middleware, ':')) {
            return [$middleware, []];
        }

        [$name, $params] = explode(':', $middleware, 2);

        // Parametreleri parse et (comma-separated)
        $parameters = array_map('trim', explode(',', $params));

        return [$name, $parameters];
    }

    /**
     * {@inheritDoc}
     */
    public function getExceptionHandler(): object
    {
        // Exception handler'ı container'dan al
        // Şimdilik basit bir handler (ileride ExceptionHandler sınıfı eklenecek)
        
        return new class {
            public function report(Throwable $e): void
            {
                // Log exception
                error_log($e->getMessage());
            }

            public function render(Throwable $e, RequestInterface $request): ResponseInterface
            {
                // Exception'ı response'a çevir
                return $this->renderException($e, $request);
            }

            protected function renderException(Throwable $e, RequestInterface $request): ResponseInterface
            {
                $statusCode = $this->getStatusCode($e);
                
                // Development mode: Detaylı hata
                if (getenv('APP_DEBUG') === 'true') {
                    return JsonResponse::error(
                        message: $e->getMessage(),
                        statusCode: $statusCode,
                        code: get_class($e),
                        details: [
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'trace' => $e->getTraceAsString(),
                        ]
                    );
                }

                // Production mode: Genel hata
                return JsonResponse::error(
                    message: $this->getProductionMessage($statusCode),
                    statusCode: $statusCode
                );
            }

            protected function getStatusCode(Throwable $e): int
            {
                if ($e instanceof HttpException) {
                    return $e->getStatusCode();
                }

                return 500;
            }

            protected function getProductionMessage(int $statusCode): string
            {
                return match ($statusCode) {
                    400 => 'Bad Request',
                    401 => 'Unauthorized',
                    403 => 'Forbidden',
                    404 => 'Not Found',
                    405 => 'Method Not Allowed',
                    422 => 'Unprocessable Entity',
                    429 => 'Too Many Requests',
                    500 => 'Internal Server Error',
                    503 => 'Service Unavailable',
                    default => 'Error',
                };
            }
        };
    }

    /**
     * {@inheritDoc}
     */
    public function handleException(Throwable $e, RequestInterface $request): ResponseInterface
    {
        // Exception handler'ı al
        $handler = $this->getExceptionHandler();

        // Exception'ı report et (log)
        try {
            $handler->report($e);
        } catch (Throwable $reportException) {
            // Report sırasında hata olursa sessizce devam et
        }

        // Exception'ı render et (response)
        return $handler->render($e, $request);
    }

    /**
     * {@inheritDoc}
     */
    public static function captureRequest(): RequestInterface
    {
        return Request::capture();
    }

    /**
     * Application instance'ını al
     * 
     * @return Application
     */
    public function getApplication(): Application
    {
        return $this->app;
    }

    /**
     * Container instance'ını al
     * 
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }
}