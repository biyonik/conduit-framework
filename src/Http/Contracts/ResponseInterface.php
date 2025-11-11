<?php

declare(strict_types=1);

namespace Conduit\Http\Contracts;

use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

/**
 * HTTP Response Interface
 * 
 * PSR-7 ResponseInterface'i extend eder ve framework'e özgü
 * helper metodlar ekler. Immutable pattern kullanır.
 * 
 * @package Conduit\Http\Contracts
 */
interface ResponseInterface extends PsrResponseInterface
{
    /**
     * Response'u client'a gönder
     * Headers + body output
     * 
     * @return void
     */
    public function send(): void;

    /**
     * Header ekle (PSR-7 withHeader wrapper)
     * 
     * @param string $name Header adı
     * @param string|string[] $value Header değeri
     * @return self
     */
    public function header(string $name, string|array $value): self;

    /**
     * Birden fazla header ekle
     * 
     * @param array $headers Associative array [name => value]
     * @return self
     */
    public function withHeaders(array $headers): self;

    /**
     * Content-Type header'ını set et
     * 
     * @param string $contentType MIME type (e.g., 'application/json')
     * @return self
     */
    public function withContentType(string $contentType): self;

    /**
     * Cookie ekle
     * 
     * @param string $name Cookie adı
     * @param string $value Cookie değeri
     * @param int $expire Expiry timestamp (0 = session cookie)
     * @param string $path Cookie path
     * @param string $domain Cookie domain
     * @param bool $secure HTTPS only
     * @param bool $httponly JavaScript erişimi engelle
     * @param string $sameSite SameSite attribute (Strict, Lax, None)
     * @return self
     */
    public function cookie(
        string $name,
        string $value,
        int $expire = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httponly = true,
        string $sameSite = 'Lax'
    ): self;

    /**
     * HTTP status code al
     * 
     * @return int
     */
    public function status(): int;

    /**
     * Response başarılı mı? (2xx status code)
     * 
     * @return bool
     */
    public function isSuccessful(): bool;

    /**
     * Response client error mı? (4xx status code)
     * 
     * @return bool
     */
    public function isClientError(): bool;

    /**
     * Response server error mı? (5xx status code)
     * 
     * @return bool
     */
    public function isServerError(): bool;

    /**
     * Response redirect mi? (3xx status code)
     * 
     * @return bool
     */
    public function isRedirect(): bool;

    /**
     * Response JSON formatında mı?
     * Content-Type: application/json kontrolü
     * 
     * @return bool
     */
    public function isJson(): bool;

    /**
     * Response content'ini al
     * 
     * @return string
     */
    public function content(): string;

    /**
     * Response'u JSON olarak al
     * Content JSON decode eder
     * 
     * @param bool $assoc Associative array olarak dön
     * @return mixed
     */
    public function json(bool $assoc = true): mixed;

    /**
     * ETag header'ını set et
     * Cache mekanizması için
     * 
     * @param string $etag ETag değeri
     * @param bool $weak Weak ETag mi?
     * @return self
     */
    public function setEtag(string $etag, bool $weak = false): self;

    /**
     * Cache-Control header'ını set et
     * 
     * @param string $value Cache-Control değeri (e.g., 'public, max-age=3600')
     * @return self
     */
    public function setCacheControl(string $value): self;

    /**
     * Response'u cache edilemez yap
     * 
     * @return self
     */
    public function setNotCacheable(): self;
}