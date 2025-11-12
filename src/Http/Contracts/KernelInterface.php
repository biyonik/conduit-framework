<?php

declare(strict_types=1);

namespace Conduit\Http\Contracts;

use Conduit\Http\Contracts\RequestInterface;
use Conduit\Http\Contracts\ResponseInterface;

/**
 * HTTP Kernel Interface
 * 
 * HTTP request lifecycle'ını yöneten çekirdek interface.
 * Request → Middleware → Router → Controller → Response pipeline'ını orkestra eder.
 * 
 * @package Conduit\Http\Contracts
 */
interface KernelInterface
{
    /**
     * HTTP request'i işle ve response dön
     * 
     * Bu method tüm HTTP request lifecycle'ını yönetir:
     * 1. Request oluştur
     * 2. Global middleware'leri çalıştır
     * 3. Router'a gönder (route matching)
     * 4. Route middleware'leri çalıştır
     * 5. Controller dispatch
     * 6. Response oluştur
     * 7. Response middleware'leri çalıştır
     * 
     * @param RequestInterface $request HTTP request
     * @return ResponseInterface HTTP response
     * @throws \Exception
     */
    public function handle(RequestInterface $request): ResponseInterface;

    /**
     * Request'i router'a gönder
     * 
     * Middleware pipeline'ından geçirilmiş request'i router'a iletir.
     * Router, uygun route'u bulur ve controller'a dispatch eder.
     * 
     * @param RequestInterface $request HTTP request
     * @return ResponseInterface HTTP response
     * @throws \Exception
     */
    public function sendRequestThroughRouter(RequestInterface $request): ResponseInterface;

    /**
     * Response gönderildikten sonra çalışacak terminasyon logic
     * 
     * Response client'a gönderildikten SONRA çalışır:
     * - Session kaydetme
     * - Log yazma
     * - Analytics
     * - Cache cleanup
     * - Background job trigger
     * 
     * Kullanıcı response'u aldı ama script hala çalışıyor!
     * 
     * @param RequestInterface $request HTTP request
     * @param ResponseInterface $response HTTP response
     * @return void
     */
    public function terminate(RequestInterface $request, ResponseInterface $response): void;

    /**
     * Global middleware'leri al
     * 
     * Her request'te çalışacak middleware listesi.
     * Sıralama önemli: İlk eklenen ilk çalışır.
     * 
     * @return array Middleware class isimleri
     */
    public function getGlobalMiddleware(): array;

    /**
     * Global middleware ekle
     * 
     * @param string $middleware Middleware class adı
     * @return self
     */
    public function pushGlobalMiddleware(string $middleware): self;

    /**
     * Middleware grubu tanımla
     * 
     * Route'larda kullanılmak üzere middleware grupları:
     * örn: 'api' => [JsonOnlyMiddleware, RateLimitMiddleware]
     * 
     * @param string $name Grup adı
     * @param array $middleware Middleware listesi
     * @return self
     */
    public function middlewareGroup(string $name, array $middleware): self;

    /**
     * Route middleware alias tanımla
     * 
     * Route tanımlarında kısa isim kullanımı için:
     * örn: 'auth' => AuthMiddleware::class
     * 
     * @param string $name Alias adı
     * @param string $middleware Middleware class adı
     * @return self
     */
    public function middlewareAlias(string $name, string $middleware): self;

    /**
     * Middleware'i resolve et
     * 
     * Middleware name/alias/class'ını alır, instantiate eder.
     * Container üzerinden dependency injection yapar.
     * 
     * @param string $middleware Middleware tanımı
     * @return object Middleware instance
     * @throws \Exception
     */
    public function resolveMiddleware(string $middleware): object;

    /**
     * Exception handler'ı al
     * 
     * @return object Exception handler instance
     */
    public function getExceptionHandler(): object;

    /**
     * Exception'ı yakala ve response'a çevir
     * 
     * Thrown exception'ları yakalayıp uygun HTTP response'a dönüştürür.
     * Production/Development mode'a göre farklı çıktı verir.
     * 
     * @param \Throwable $e Yakalanan exception
     * @param RequestInterface $request HTTP request
     * @return ResponseInterface Error response
     */
    public function handleException(\Throwable $e, RequestInterface $request): ResponseInterface;

    /**
     * Request'ten PHP globals oluştur
     * 
     * $_GET, $_POST, $_SERVER, $_FILES, $_COOKIE'den Request object oluşturur.
     * Framework'ün giriş noktasında kullanılır (public/index.php).
     * 
     * @return RequestInterface
     */
    public static function captureRequest(): RequestInterface;
}