<?php

declare(strict_types=1);

namespace Conduit\Core;

use Closure;
use RuntimeException;
use Conduit\Core\Contracts\ApplicationInterface;
use Conduit\Core\Contracts\ContainerInterface;
use Conduit\Core\Exceptions\NotFoundException;
use Conduit\Http\Request;
use Conduit\Http\Response;
use Conduit\Http\Kernel;

/**
 * Application
 * 
 * Framework'ün merkezi sınıfı. Orkestratör rolü oynar.
 * Tüm framework bileşenlerinin yaşam döngüsünü yönetir.
 * 
 * Sorumluluklar:
 * - Container yönetimi
 * - Service provider yükleme
 * - Bootstrap süreçleri
 * - HTTP request handling
 * - Environment yönetimi
 * 
 * @package Conduit\Core
 */
class Application implements ApplicationInterface
{
    /**
     * Framework versiyonu
     */
    public const VERSION = '1.0.0';

    /**
     * Static application instance
     * 
     * @var self|null
     */
    protected static ?self $instance = null;

    /**
     * Application base path
     * 
     * @var string
     */
    protected string $basePath;

    /**
     * Dependency injection container
     * 
     * @var Container
     */
    protected Container $container;

    /**
     * Kayıtlı service provider'lar
     * 
     * @var array<ServiceProvider>
     */
    protected array $serviceProviders = [];

    /**
     * Boot edilmiş provider'lar
     * 
     * @var array<string, bool>
     */
    protected array $bootedProviders = [];

    /**
     * Application bootstrap edildi mi?
     * 
     * @var bool
     */
    protected bool $hasBeenBootstrapped = false;

    /**
     * Application boot edildi mi?
     * 
     * @var bool
     */
    protected bool $booted = false;

    /**
     * Bootstrap callbacks
     * 
     * @var array<Closure>
     */
    protected array $bootstrappers = [];

    /**
     * Environment dosyası yolu
     * 
     * @var string
     */
    protected string $environmentPath;

    /**
     * Environment dosyası adı
     * 
     * @var string
     */
    protected string $environmentFile = '.env';

    /**
     * Application constructor
     * 
     * @param string|null $basePath Application base path
     * @param string|null $containerClass Container class to use (defaults to Container)
     */
    public function __construct(?string $basePath = null, ?string $containerClass = null)
    {
        if ($basePath) {
            $this->setBasePath($basePath);
        }

        $this->registerBaseBindings($containerClass);
        $this->registerBaseServiceProviders();
        $this->registerCoreContainerAliases();
        
        // Set static instance
        static::$instance = $this;
    }

    /**
     * Get the globally available instance of the application
     * 
     * @return self
     * @throws \RuntimeException If no instance is available
     */
    public static function getInstance(): self
    {
        if (static::$instance === null) {
            throw new \RuntimeException('Application instance not available. Create an Application instance first.');
        }
        
        return static::$instance;
    }

    /**
     * Framework versiyonunu döndür
     * 
     * @return string
     */
    public function version(): string
    {
        return static::VERSION;
    }

    /**
     * Base path'i set et
     * 
     * @param string $basePath
     * @return self
     */
    public function setBasePath(string $basePath): self
    {
        $this->basePath = rtrim($basePath, '\/');
        $this->environmentPath = $this->basePath;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    /**
     * {@inheritdoc}
     */
    public function storagePath(string $path = ''): string
    {
        return $this->basePath('storage') . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    /**
     * {@inheritdoc}
     */
    public function configPath(string $path = ''): string
    {
        return $this->basePath('config') . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    /**
     * Database path'ini döndür
     * 
     * @param string $path
     * @return string
     */
    public function databasePath(string $path = ''): string
    {
        return $this->basePath('database') . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    /**
     * Public path'ini döndür (web root)
     * 
     * @param string $path
     * @return string
     */
    public function publicPath(string $path = ''): string
    {
        return $this->basePath('public') . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    /**
     * Resource path'ini döndür
     * 
     * @param string $path
     * @return string
     */
    public function resourcePath(string $path = ''): string
    {
        return $this->basePath('resources') . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    /**
     * Core binding'leri kaydet
     * 
     * @param string|null $containerClass Container class to use
     * @return void
     */
    protected function registerBaseBindings(?string $containerClass = null): void
    {
        // Use specified container class or default to Container
        $containerClass = $containerClass ?? Container::class;
        
        // Validate container class
        if (!class_exists($containerClass)) {
            throw new \InvalidArgumentException("Container class does not exist: {$containerClass}");
        }
        
        if (!is_subclass_of($containerClass, ContainerInterface::class) && $containerClass !== Container::class) {
            throw new \InvalidArgumentException(
                "Container class must implement ContainerInterface: {$containerClass}"
            );
        }
        
        $this->container = new $containerClass();
        
        // Container'ı kendisine bind et (singleton)
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(ContainerInterface::class, $this->container);
        
        // Application'ı container'a bind et
        $this->container->instance(Application::class, $this);
        $this->container->instance(ApplicationInterface::class, $this);
        $this->container->instance('app', $this);

        // Container'ı static instance olarak set et
        Container::setInstance($this->container);
    }

    /**
     * Core service provider'ları kaydet
     * 
     * @return void
     */
    protected function registerBaseServiceProviders(): void
    {
        // Core provider'lar buraya eklenecek
        // Şimdilik boş, diğer katmanlar tamamlanınca eklenecek
    }

    /**
     * Core container alias'larını kaydet
     * 
     * @return void
     */
    protected function registerCoreContainerAliases(): void
    {
        $aliases = [
            'app' => [Application::class, ApplicationInterface::class],
            'container' => [Container::class, ContainerInterface::class],
            // Diğer alias'lar katmanlar tamamlanınca eklenecek
        ];

        foreach ($aliases as $key => $aliasArray) {
            foreach ($aliasArray as $alias) {
                $this->container->alias($key, $alias);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function bootstrap(): void
    {
        if ($this->hasBeenBootstrapped) {
            return;
        }

        // Environment dosyasını yükle
        $this->loadEnvironmentVariables();

        // Config dosyalarını yükle
        $this->loadConfiguration();

        // Timezone ayarla
        date_default_timezone_set($this->container->make('config')->get('app.timezone', 'UTC'));

        // Error handler'ı kaydet
        $this->registerErrorHandler();

        // Custom bootstrapper'ları çalıştır
        foreach ($this->bootstrappers as $bootstrapper) {
            $bootstrapper($this);
        }

        $this->hasBeenBootstrapped = true;
    }

    /**
     * Environment değişkenlerini yükle (.env)
     * 
     * @return void
     */
    protected function loadEnvironmentVariables(): void
    {
        $envPath = $this->environmentPath . DIRECTORY_SEPARATOR . $this->environmentFile;

        if (!file_exists($envPath)) {
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Comment satırlarını atla
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            // KEY=VALUE parse et
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Tırnak işaretlerini temizle
                $value = trim($value, '"\'');

                // Environment'a ekle
                if (!array_key_exists($key, $_ENV)) {
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }
    }

    /**
     * Config dosyalarını yükle
     * 
     * @return void
     */
    public function loadConfiguration(): void
    {
        $configPath = $this->configPath();

        if (!is_dir($configPath)) {
            return;
        }

        $config = [];

        // Tüm config dosyalarını yükle
        foreach (glob($configPath . '/*.php') as $file) {
            $key = basename($file, '.php');
            $config[$key] = require $file;
        }

        // Config'i container'a bind et
        $this->container->instance('config', new class($config) {
            protected array $items = [];

            public function __construct(array $items)
            {
                $this->items = $items;
            }

            public function get(string $key, mixed $default = null): mixed
            {
                $keys = explode('.', $key);
                $value = $this->items;

                foreach ($keys as $segment) {
                    if (!isset($value[$segment])) {
                        return $default;
                    }
                    $value = $value[$segment];
                }

                return $value;
            }

            public function set(string $key, mixed $value): void
            {
                $keys = explode('.', $key);
                $config = &$this->items;

                while (count($keys) > 1) {
                    $segment = array_shift($keys);
                    if (!isset($config[$segment]) || !is_array($config[$segment])) {
                        $config[$segment] = [];
                    }
                    $config = &$config[$segment];
                }

                $config[array_shift($keys)] = $value;
            }

            public function has(string $key): bool
            {
                return $this->get($key) !== null;
            }

            public function all(): array
            {
                return $this->items;
            }
        });
    }

    /**
     * Error handler'ı kaydet
     * 
     * @return void
     */
    protected function registerErrorHandler(): void
    {
        error_reporting(E_ALL);

        set_error_handler(function ($level, $message, $file = '', $line = 0) {
            if (error_reporting() & $level) {
                throw new \ErrorException($message, 0, $level, $file, $line);
            }
        });

        set_exception_handler(function (\Throwable $e) {
            $this->renderException($e);
        });

        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
                $this->renderException(new \ErrorException(
                    $error['message'],
                    0,
                    $error['type'],
                    $error['file'],
                    $error['line']
                ));
            }
        });
    }

    /**
     * Exception'ı render et
     * 
     * @param \Throwable $e
     * @return void
     */
    protected function renderException(\Throwable $e): void
    {
        $debug = $this->isDebug();

        if (php_sapi_name() === 'cli') {
            // CLI mode
            echo "Error: {$e->getMessage()}\n";
            if ($debug) {
                echo "File: {$e->getFile()}:{$e->getLine()}\n";
                echo "Trace:\n{$e->getTraceAsString()}\n";
            }
        } else {
            // HTTP mode
            http_response_code(500);
            header('Content-Type: application/json');

            $response = [
                'success' => false,
                'error' => [
                    'message' => $debug ? $e->getMessage() : 'Internal Server Error',
                    'code' => 500,
                ],
            ];

            if ($debug) {
                $response['error']['file'] = $e->getFile();
                $response['error']['line'] = $e->getLine();
                $response['error']['trace'] = explode("\n", $e->getTraceAsString());
            }

            echo json_encode($response, JSON_PRETTY_PRINT);
        }

        exit(1);
    }

    /**
     * {@inheritdoc}
     */
    public function register(string $provider): void
    {
        // Zaten kayıtlıysa atla
        if ($this->isProviderRegistered($provider)) {
            return;
        }

        // Provider instance'ı oluştur
        $providerInstance = new $provider($this);

        // Register metodunu çağır
        $providerInstance->register();

        // Provider'ı kaydet
        $this->serviceProviders[] = $providerInstance;

        // Eğer application zaten boot edilmişse, provider'ı da hemen boot et
        if ($this->booted) {
            $this->bootProvider($providerInstance);
        }
    }

    /**
     * Provider kayıtlı mı?
     * 
     * @param string $provider
     * @return bool
     */
    protected function isProviderRegistered(string $provider): bool
    {
        foreach ($this->serviceProviders as $registered) {
            if (get_class($registered) === $provider) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // Tüm provider'ları boot et
        foreach ($this->serviceProviders as $provider) {
            $this->bootProvider($provider);
        }

        $this->booted = true;
    }

    /**
     * Tek bir provider'ı boot et
     * 
     * @param ServiceProvider $provider
     * @return void
     */
    protected function bootProvider(ServiceProvider $provider): void
    {
        $class = get_class($provider);

        if (isset($this->bootedProviders[$class])) {
            return;
        }

        $provider->boot();
        $this->bootedProviders[$class] = true;
    }

    /**
     * Bootstrap callback kaydet
     * 
     * @param Closure $callback
     * @return void
     */
    public function bootstrapWith(Closure $callback): void
    {
        $this->bootstrappers[] = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request): Response
    {
        try {
            // Bootstrap if not done
            if (!$this->hasBeenBootstrapped) {
                $this->bootstrap();
            }

            // Boot providers if not done
            if (!$this->booted) {
                $this->boot();
            }

            // HTTP Kernel'den geçir
            $kernel = $this->container->make(Kernel::class);
            
            return $kernel->handle($request);
        } catch (\Throwable $e) {
            // Exception handling (production-ready response)
            return $this->renderHttpException($e);
        }
    }

    /**
     * HTTP exception'ını JSON response'a çevir
     * 
     * @param \Throwable $e
     * @return Response
     */
    protected function renderHttpException(\Throwable $e): Response
    {
        $debug = $this->isDebug();

        $response = [
            'success' => false,
            'error' => [
                'message' => $debug ? $e->getMessage() : 'Internal Server Error',
                'code' => method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500,
            ],
        ];

        if ($debug) {
            $response['error']['file'] = $e->getFile();
            $response['error']['line'] = $e->getLine();
            $response['error']['trace'] = explode("\n", $e->getTraceAsString());
        }

        // Response class henüz yok, basit response döndür
        http_response_code($response['error']['code']);
        header('Content-Type: application/json');
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * {@inheritdoc}
     */
    public function terminate(): void
    {
        // Cleanup işlemleri
        // - Session write
        // - Log flush
        // - Cache cleanup

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function environment(): string
    {
        return $this->container->make('config')->get('app.env', 'production');
    }

    /**
     * {@inheritdoc}
     */
    public function isProduction(): bool
    {
        return $this->environment() === 'production';
    }

    /**
     * {@inheritdoc}
     */
    public function isDebug(): bool
    {
        return (bool) $this->container->make('config')->get('app.debug', false);
    }

    /**
     * Console'da mı çalışıyor?
     * 
     * @return bool
     */
    public function runningInConsole(): bool
    {
        return php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg';
    }

    /**
     * Unit test ortamında mı?
     * 
     * @return bool
     */
    public function runningUnitTests(): bool
    {
        return $this->environment() === 'testing';
    }

    /**
     * Config cache'lenmiş mi?
     * 
     * @return bool
     */
    public function configurationIsCached(): bool
    {
        return file_exists($this->storagePath('cache/config.php'));
    }

    /**
     * Route'lar cache'lenmiş mi?
     * 
     * @return bool
     */
    public function routesAreCached(): bool
    {
        return file_exists($this->storagePath('cache/routes.php'));
    }

    /**
     * {@inheritdoc}
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Container'dan servis çek (helper method)
     * 
     * @param string $abstract
     * @param array $parameters
     * @return mixed
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        return $this->container->make($abstract, $parameters);
    }

    /**
     * Magic method: Container'a proxy
     * 
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->container->$method(...$arguments);
    }
}