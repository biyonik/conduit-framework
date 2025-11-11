<?php

declare(strict_types=1);

namespace Conduit\Http\Contracts;

use Psr\Http\Message\ServerRequestInterface;

/**
 * HTTP Request Interface
 * 
 * PSR-7 ServerRequestInterface'i extend eder ve framework'e özgü
 * helper metodlar ekler. Immutable pattern kullanır.
 * 
 * @package Conduit\Http\Contracts
 */
interface RequestInterface extends ServerRequestInterface
{
    /**
     * Herhangi bir input değerini al (query/post/json/route param)
     * Priority: route params > query > post > json
     * 
     * @param string|null $key Anahtar, null ise tüm input
     * @param mixed $default Varsayılan değer
     * @return mixed
     */
    public function input(?string $key = null, mixed $default = null): mixed;

    /**
     * Tüm input verisini al (query + post + json + route params)
     * 
     * @return array
     */
    public function all(): array;

    /**
     * Sadece belirtilen anahtarları al
     * 
     * @param array $keys İstenen anahtarlar
     * @return array
     */
    public function only(array $keys): array;

    /**
     * Belirtilen anahtarlar hariç tüm input'u al
     * 
     * @param array $keys Hariç tutulacak anahtarlar
     * @return array
     */
    public function except(array $keys): array;

    /**
     * Input'ta bir anahtar var mı?
     * 
     * @param string $key Kontrol edilecek anahtar
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Input'ta birden fazla anahtar var mı?
     * 
     * @param array $keys Kontrol edilecek anahtarlar
     * @return bool Tümü varsa true
     */
    public function hasAny(array $keys): bool;

    /**
     * Request'in JSON içerik tipinde olup olmadığını kontrol et
     * Content-Type: application/json kontrolü yapar
     * 
     * @return bool
     */
    public function isJson(): bool;

    /**
     * Client JSON response bekliyor mu?
     * Accept: application/json kontrolü yapar
     * 
     * @return bool
     */
    public function expectsJson(): bool;

    /**
     * Client JSON response istiyor mu?
     * isJson() VEYA expectsJson() true ise true döner
     * 
     * @return bool
     */
    public function wantsJson(): bool;

    /**
     * Request AJAX/XMLHttpRequest mi?
     * X-Requested-With: XMLHttpRequest kontrolü yapar
     * 
     * @return bool
     */
    public function ajax(): bool;

    /**
     * Request HTTPS üzerinden mi geldi?
     * 
     * @return bool
     */
    public function secure(): bool;

    /**
     * Client IP adresini al
     * Proxy header'larını da dikkate alır (X-Forwarded-For)
     * 
     * @return string|null
     */
    public function ip(): ?string;

    /**
     * User agent string'ini al
     * 
     * @return string|null
     */
    public function userAgent(): ?string;

    /**
     * Authorization Bearer token'ını al
     * Authorization: Bearer {token} header'ından çeker
     * 
     * @return string|null
     */
    public function bearerToken(): ?string;

    /**
     * Request method'unu al (GET, POST, PUT, DELETE, etc.)
     * 
     * @return string
     */
    public function method(): string;

    /**
     * Request path'ini al (/api/users/123)
     * Query string olmadan, sadece path
     * 
     * @return string
     */
    public function path(): string;

    /**
     * Full URL'i al (https://example.com/api/users/123?page=1)
     * 
     * @return string
     */
    public function url(): string;

    /**
     * Full URL'i query string ile al
     * 
     * @return string
     */
    public function fullUrl(): string;

    /**
     * Route parametrelerini al/set et
     * 
     * @param array|null $parameters Parametreler (null ise getter)
     * @return array|self
     */
    public function routeParameters(?array $parameters = null): array|self;

    /**
     * Authenticated user'ı al/set et
     * 
     * @param mixed|null $user User instance (null ise getter)
     * @return mixed|self
     */
    public function user(mixed $user = null): mixed;

    /**
     * Request'e custom attribute ekle
     * Middleware'ler arası veri paylaşımı için
     * 
     * @param string $key Anahtar
     * @param mixed $value Değer
     * @return self
     */
    public function setAttribute(string $key, mixed $value): self;

    /**
     * Custom attribute al
     * 
     * @param string $key Anahtar
     * @param mixed $default Varsayılan değer
     * @return mixed
     */
    public function getAttribute(string $key, mixed $default = null): mixed;
}