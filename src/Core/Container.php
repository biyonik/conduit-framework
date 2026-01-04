<?php

declare(strict_types=1);

namespace Conduit\Core;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use Conduit\Core\Contracts\ContainerInterface;
use Conduit\Core\Exceptions\BindingResolutionException;
use Conduit\Core\Exceptions\ContainerException;

/**
 * Dependency Injection Container
 * 
 * PSR-11 uyumlu DI container. Framework'ün kalbidir.
 * Tüm servisler bu container üzerinden resolve edilir.
 * 
 * Özellikler:
 * - Automatic dependency resolution
 * - Singleton pattern support
 * - Constructor injection
 * - Circular dependency detection
 * - Alias support
 * 
 * @package Conduit\Core
 */
class Container implements ContainerInterface
{
    /**
     * Container'ın singleton instance'ı
     * 
     * @var self|null
     */
    protected static ?self $instance = null;

    /**
     * Kayıtlı binding'ler
     * 
     * Format: ['abstract' => ['concrete' => ..., 'shared' => bool]]
     * 
     * @var array<string, array>
     */
    protected array $bindings = [];

    /**
     * Çözümlenmiş singleton instance'lar
     * 
     * @var array<string, mixed>
     */
    protected array $instances = [];

    /**
     * Alias tanımları
     * 
     * Format: ['alias' => 'abstract']
     * 
     * @var array<string, string>
     */
    protected array $aliases = [];

    /**
     * Şu anda resolve edilmekte olan servisler (circular dependency tespiti için)
     * 
     * @var array<string, bool>
     */
    protected array $resolving = [];

    /**
     * Container singleton instance'ını döndür
     * 
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Singleton instance'ı set et
     * 
     * @param ContainerInterface|null $container
     * @return void
     */
    public static function setInstance(?ContainerInterface $container = null): void
    {
        self::$instance = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function bind(string $abstract, mixed $concrete = null, bool $shared = false): void
    {
        // Concrete belirtilmemişse, abstract'ı kendisi için kullan
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared,
        ];

        // Eğer bu abstract için daha önce instance varsa, temizle
        // (yeni binding eski instance'ı geçersiz kılar)
        if (isset($this->instances[$abstract])) {
            unset($this->instances[$abstract]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * {@inheritdoc}
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * {@inheritdoc}
     */
    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * {@inheritdoc}
     * 
     * PSR-11 required method
     */
    public function get(string $id): mixed
    {
        try {
            return $this->make($id);
        } catch (BindingResolutionException $e) {
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     * 
     * PSR-11 required method
     */
    public function has(string $id): bool
    {
        return $this->bound($id);
    }

    /**
     * {@inheritdoc}
     */
    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) 
            || isset($this->instances[$abstract])
            || isset($this->aliases[$abstract]);
    }

    /**
     * {@inheritdoc}
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        // Alias çözümle
        $abstract = $this->getAlias($abstract);

        // Eğer singleton instance mevcutsa, direkt döndür
        if (isset($this->instances[$abstract]) && empty($parameters)) {
            return $this->instances[$abstract];
        }

        // Binding'den concrete elde et
        $concrete = $this->getConcrete($abstract);

        // Concrete'i build et
        $object = $this->isBuildable($concrete, $abstract)
            ? $this->build($concrete, $parameters)
            : $this->make($concrete, $parameters);

        // Eğer shared (singleton) ise, instance'ı sakla
        if ($this->isShared($abstract) && empty($parameters)) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Alias'ı çözümle, yoksa orijinal abstract'ı döndür
     * 
     * @param string $abstract
     * @return string
     */
    protected function getAlias(string $abstract): string
    {
        return $this->aliases[$abstract] ?? $abstract;
    }

    /**
     * Abstract için concrete tanımını al
     * 
     * @param string $abstract
     * @return mixed
     */
    protected function getConcrete(string $abstract): mixed
    {
        // Binding varsa concrete'i al
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        // Binding yoksa abstract'ın kendisini kullan (class adı olabilir)
        return $abstract;
    }

    /**
     * Concrete build edilebilir mi?
     * 
     * @param mixed $concrete
     * @param string $abstract
     * @return bool
     */
    protected function isBuildable(mixed $concrete, string $abstract): bool
    {
        return $concrete === $abstract || !is_string($concrete);
    }

    /**
     * Abstract shared (singleton) olarak mı kayıtlı?
     * 
     * @param string $abstract
     * @return bool
     */
    protected function isShared(string $abstract): bool
    {
        return isset($this->bindings[$abstract]['shared'])
            && $this->bindings[$abstract]['shared'] === true;
    }

    /**
     * Concrete'i build et (instantiate)
     * 
     * @param mixed $concrete
     * @param array $parameters
     * @return mixed
     * 
     * @throws BindingResolutionException
     * @throws ContainerException
     */
    protected function build(mixed $concrete, array $parameters = []): mixed
    {
        // Closure ise direkt çalıştır
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }

        // String değilse (zaten instantiate edilmiş), direkt döndür
        if (!is_string($concrete)) {
            return $concrete;
        }

        // Circular dependency kontrolü
        if (isset($this->resolving[$concrete])) {
            throw ContainerException::circularDependency($concrete);
        }

        $this->resolving[$concrete] = true;

        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            throw BindingResolutionException::cannotInstantiate($concrete, 'Class does not exist');
        }

        // Instantiate edilemez mi? (abstract class, interface)
        if (!$reflector->isInstantiable()) {
            throw BindingResolutionException::unboundInterface($concrete);
        }

        $constructor = $reflector->getConstructor();

        // Constructor yoksa direkt instantiate et
        if ($constructor === null) {
            unset($this->resolving[$concrete]);
            return new $concrete();
        }

        // Constructor parametrelerini resolve et
        $dependencies = $this->resolveDependencies(
            $constructor->getParameters(),
            $parameters
        );

        unset($this->resolving[$concrete]);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Constructor bağımlılıklarını resolve et
     * 
     * @param array<ReflectionParameter> $parameters
     * @param array $primitives Override parametreleri
     * @return array Çözümlenmiş bağımlılıklar
     * 
     * @throws BindingResolutionException
     */
    protected function resolveDependencies(array $parameters, array $primitives = []): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            // Override edilmiş parametre varsa kullan
            if (array_key_exists($name, $primitives)) {
                $dependencies[] = $primitives[$name];
                continue;
            }

            // Type hint varsa resolve et
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->make($type->getName());
                continue;
            }

            // Default değer varsa kullan
            if ($parameter->isDefaultValueAvailable()) {
                try {
                    $dependencies[] = $parameter->getDefaultValue();
                } catch (ReflectionException $e) {
                    throw BindingResolutionException::unresolvedParameter(
                        $parameter->getDeclaringClass()->getName(),
                        $name
                    );
                }
                continue;
            }

            // Nullable ise null ver
            if ($parameter->allowsNull()) {
                $dependencies[] = null;
                continue;
            }

            // Hiçbiri yoksa hata fırlat
            throw BindingResolutionException::unresolvedParameter(
                $parameter->getDeclaringClass()->getName(),
                $name
            );
        }

        return $dependencies;
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->aliases = [];
        $this->resolving = [];
    }

    /**
     * Magic method: Container'a array gibi erişim
     * 
     * @param string $key
     * @return mixed
     */
    public function offsetGet(mixed $key): mixed
    {
        return $this->make($key);
    }

    /**
     * Magic method: Container'a array gibi set
     * 
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function offsetSet(mixed $key, mixed $value): void
    {
        $this->bind($key, $value instanceof Closure ? $value : fn() => $value);
    }

    /**
     * Magic method: Bound kontrolü
     * 
     * @param string $key
     * @return bool
     */
    public function offsetExists(mixed $key): bool
    {
        return $this->bound($key);
    }

    /**
     * Magic method: Binding'i kaldır
     * 
     * @param string $key
     * @return void
     */
    public function offsetUnset(mixed $key): void
    {
        unset($this->bindings[$key], $this->instances[$key], $this->aliases[$key]);
    }

    /**
     * Magic method: Servise property gibi erişim
     * 
     * @param string $key
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        return $this->make($key);
    }

    /**
     * Magic method: Servisi property gibi set
     * 
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function __set(string $key, mixed $value): void
    {
        $this->bind($key, $value);
    }

    /**
     * Get all bindings (for compilation)
     * 
     * @return array<string, array>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Get all aliases (for compilation)
     * 
     * @return array<string, string>
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * Get singleton instances (for compilation)
     * 
     * @return array<string, array>
     */
    public function getSingletons(): array
    {
        return array_filter($this->bindings, fn($b) => $b['shared']);
    }
}