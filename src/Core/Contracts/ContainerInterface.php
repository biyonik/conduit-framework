<?php

declare(strict_types=1);

namespace Conduit\Core\Contracts;

use Psr\Container\ContainerInterface as PsrContainerInterface;

/**
 * Container Interface (PSR-11 uyumlu)
 * 
 * Dependency Injection container için sözleşme.
 * Tüm framework bileşenleri bu container üzerinden resolve edilir.
 * 
 * @package Conduit\Core\Contracts
 */
interface ContainerInterface extends PsrContainerInterface
{
    /**
     * Container'a bir servis bind et
     * 
     * @param string $abstract Servisin soyut adı (interface/class)
     * @param mixed $concrete Somut implementation (closure, class, instance)
     * @param bool $shared Singleton olarak mı bind edilsin?
     * @return void
     */
    public function bind(string $abstract, mixed $concrete = null, bool $shared = false): void;

    /**
     * Container'a singleton servis bind et
     * 
     * Servis sadece bir kez instantiate edilir, sonraki çağrılarda aynı instance döner.
     * 
     * @param string $abstract Servisin soyut adı
     * @param mixed $concrete Somut implementation
     * @return void
     */
    public function singleton(string $abstract, mixed $concrete = null): void;

    /**
     * Hazır bir instance'ı bind et
     * 
     * @param string $abstract Servisin adı
     * @param mixed $instance Hazır instance
     * @return void
     */
    public function instance(string $abstract, mixed $instance): void;

    /**
     * Servisi container'dan çözümle
     * 
     * @param string $abstract Servisin adı
     * @param array $parameters Constructor parametreleri (override)
     * @return mixed Çözümlenmiş servis
     * 
     * @throws \Conduit\Core\Exceptions\BindingResolutionException
     */
    public function make(string $abstract, array $parameters = []): mixed;

    /**
     * Servis bind edilmiş mi kontrol et
     * 
     * @param string $abstract Servisin adı
     * @return bool
     */
    public function bound(string $abstract): bool;

    /**
     * Alias tanımla (kısa isim)
     * 
     * Örnek: $container->alias(UserRepository::class, 'user.repo')
     * 
     * @param string $abstract Gerçek servis adı
     * @param string $alias Kısa isim
     * @return void
     */
    public function alias(string $abstract, string $alias): void;

    /**
     * Container'daki tüm binding'leri temizle
     * 
     * Dikkat: Bu metod sadece test ortamında kullanılmalı!
     * 
     * @return void
     */
    public function flush(): void;
}