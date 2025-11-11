<?php

declare(strict_types=1);

namespace Conduit\Core\Contracts;

use Conduit\Http\Request;
use Conduit\Http\Response;

/**
 * Application Interface
 * 
 * Framework'ün merkezi Application sınıfı için sözleşme.
 * 
 * @package Conduit\Core\Contracts
 */
interface ApplicationInterface
{
    /**
     * Framework versiyonu
     */
    public const VERSION = '1.0.0';

    /**
     * Uygulamayı başlat (bootstrap)
     * 
     * @return void
     */
    public function bootstrap(): void;

    /**
     * HTTP request'i işle ve response döndür
     * 
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request): Response;

    /**
     * Response gönderildikten sonra cleanup işlemleri
     * 
     * @return void
     */
    public function terminate(): void;

    /**
     * Uygulama base path'ini döndür
     * 
     * @param string $path Opsiyonel path suffix
     * @return string
     */
    public function basePath(string $path = ''): string;

    /**
     * Storage path'ini döndür
     * 
     * @param string $path Opsiyonel path suffix
     * @return string
     */
    public function storagePath(string $path = ''): string;

    /**
     * Config path'ini döndür
     * 
     * @param string $path Opsiyonel path suffix
     * @return string
     */
    public function configPath(string $path = ''): string;

    /**
     * Uygulama ortamını döndür (production, development, etc.)
     * 
     * @return string
     */
    public function environment(): string;

    /**
     * Production ortamında mı?
     * 
     * @return bool
     */
    public function isProduction(): bool;

    /**
     * Debug mode aktif mi?
     * 
     * @return bool
     */
    public function isDebug(): bool;

    /**
     * Service provider kaydet
     * 
     * @param string $provider Provider class adı
     * @return void
     */
    public function register(string $provider): void;

    /**
     * Tüm provider'ları boot et
     * 
     * @return void
     */
    public function boot(): void;

    /**
     * Application container'ını döndür
     * 
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface;
}