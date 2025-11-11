<?php

declare(strict_types=1);

namespace Conduit\Core;

use Conduit\Core\Contracts\BootableInterface;
use Conduit\Core\Contracts\ContainerInterface;

/**
 * Service Provider (Abstract Base)
 * 
 * Tüm service provider'ların base class'ı.
 * Framework ve uygulama servisleri bu class'ı extend eder.
 * 
 * İki aşamalı lifecycle:
 * 1. register() - Servisleri container'a kaydet (diğer provider'lara bağımlı olma!)
 * 2. boot() - Servisleri başlat (tüm provider'lar register edildikten sonra)
 * 
 * @package Conduit\Core
 */
abstract class ServiceProvider implements BootableInterface
{
    /**
     * Application instance
     * 
     * @var Application
     */
    protected Application $app;

    /**
     * Container instance (kısa erişim için)
     * 
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * Deferred loading mi? (lazy loading)
     * 
     * true ise provider sadece provides() metodundaki servisler istendiğinde yüklenir.
     * false ise her zaman yüklenir.
     * 
     * @var bool
     */
    protected bool $defer = false;

    /**
     * ServiceProvider constructor
     * 
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->container = $app->getContainer();
    }

    /**
     * Servisleri container'a kaydet
     * 
     * Bu metod içinde:
     * - Container'a binding'ler ekle
     * - Singleton'ları kaydet
     * - Alias'ları tanımla
     * 
     * DİKKAT: Bu aşamada diğer provider'lardan servis ÇEKME!
     * Sadece kayıt yap, kullanma.
     * 
     * @return void
     */
    abstract public function register(): void;

    /**
     * {@inheritdoc}
     * 
     * Tüm provider'lar register edildikten sonra çalışır.
     * Bu aşamada container'dan servis çekilebilir.
     * 
     * Kullanım senaryoları:
     * - Route'ları yükle
     * - Event listener'ları kaydet
     * - Observer'ları attach et
     * - Config merge et
     * 
     * @return void
     */
    public function boot(): void
    {
        // Alt class'larda override edilebilir
        // Default implementation boş
    }

    /**
     * Provider deferred (lazy) loading mi?
     * 
     * @return bool
     */
    public function isDeferred(): bool
    {
        return $this->defer;
    }

    /**
     * Provider hangi servisleri sağlıyor? (deferred loading için)
     * 
     * Deferred provider'lar için zorunlu.
     * Container bu servisleri istediğinde provider yüklenir.
     * 
     * @return array<string> Servis isimleri
     */
    public function provides(): array
    {
        return [];
    }

    /**
     * Config dosyasını merge et
     * 
     * Paket config'ini uygulama config'i ile birleştirir.
     * 
     * @param string $path Config dosyası yolu
     * @param string $key Config key'i
     * @return void
     */
    protected function mergeConfigFrom(string $path, string $key): void
    {
        if (!$this->app->configurationIsCached()) {
            $config = $this->app->make('config');
            
            $config->set($key, array_merge(
                require $path,
                $config->get($key, [])
            ));
        }
    }

    /**
     * Config dosyasını publish et
     * 
     * Kullanıcı config dosyasını kendi config klasörüne kopyalayabilir.
     * 
     * @param string $path Kaynak config dosyası
     * @param string $destination Hedef path
     * @return void
     */
    protected function publishes(string $path, string $destination): void
    {
        // Gelecekte php conduit vendor:publish komutu ile kullanılacak
        // Şimdilik sadı map'i sakla
        $this->app->make('publish.manager')->add($path, $destination);
    }

    /**
     * Route dosyasını yükle
     * 
     * @param string $path Route dosyası yolu
     * @return void
     */
    protected function loadRoutesFrom(string $path): void
    {
        if (!$this->app->routesAreCached()) {
            require $path;
        }
    }

    /**
     * Migration klasörünü yükle
     * 
     * @param string $path Migration klasörü yolu
     * @return void
     */
    protected function loadMigrationsFrom(string $path): void
    {
        $this->app->make('migrator')->path($path);
    }

    /**
     * View klasörünü kaydet
     * 
     * @param string $path View klasörü yolu
     * @param string $namespace View namespace
     * @return void
     */
    protected function loadViewsFrom(string $path, string $namespace): void
    {
        $this->app->make('view')->addNamespace($namespace, $path);
    }

    /**
     * Command'ları kaydet (CLI)
     * 
     * @param array<string> $commands Command class'ları
     * @return void
     */
    protected function commands(array $commands): void
    {
        if ($this->app->runningInConsole()) {
            $this->app->make('console')->addCommands($commands);
        }
    }
}