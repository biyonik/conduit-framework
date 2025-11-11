<?php

declare(strict_types=1);

namespace Conduit\Http;

use Conduit\Http\Contracts\RequestInterface;
use Conduit\Http\Message\Uri;
use Conduit\Http\Message\Stream;
use Conduit\Http\Message\UploadedFile;
use Conduit\Http\Traits\InteractsWithInput;
use Conduit\Http\Traits\InteractsWithHeaders;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\UploadedFileInterface;
use InvalidArgumentException;

/**
 * HTTP Request
 * 
 * PSR-7 ServerRequest implementasyonu + Framework helper'ları.
 * Immutable: Her değişiklik yeni instance döner.
 * 
 * @package Conduit\Http
 */
class Request implements RequestInterface
{
    use InteractsWithInput;
    use InteractsWithHeaders;

    /**
     * HTTP method (GET, POST, PUT, DELETE, etc.)
     */
    private string $method;

    /**
     * Request URI
     */
    private UriInterface $uri;

    /**
     * Protocol version (1.0, 1.1, 2.0)
     */
    private string $protocolVersion = '1.1';

    /**
     * Headers (normalized name => [values])
     */
    private array $headers = [];

    /**
     * Header name mapping (lowercase => original case)
     */
    private array $headerNames = [];

    /**
     * Request body stream
     */
    private StreamInterface $body;

    /**
     * Server parameters ($_SERVER)
     */
    private array $serverParams;

    /**
     * Cookie parameters ($_COOKIE)
     */
    private array $cookieParams;

    /**
     * Uploaded files
     */
    private array $uploadedFiles = [];

    /**
     * Parsed body (POST data)
     */
    private mixed $parsedBody = null;

    /**
     * Request attributes (middleware arası veri paylaşımı)
     */
    private array $attributes = [];

    /**
     * Request target (path + query)
     */
    private ?string $requestTarget = null;

    /**
     * Authenticated user
     */
    private mixed $user = null;

    /**
     * Constructor
     * 
     * @param string $method HTTP method
     * @param UriInterface|string $uri URI
     * @param array $headers Headers
     * @param StreamInterface|string|resource|null $body Body
     * @param string $protocolVersion Protocol version
     * @param array $serverParams Server parameters
     */
    public function __construct(
        string $method,
        UriInterface|string $uri,
        array $headers = [],
        mixed $body = null,
        string $protocolVersion = '1.1',
        array $serverParams = []
    ) {
        $this->method = strtoupper($method);
        $this->uri = is_string($uri) ? new Uri($uri) : $uri;
        $this->protocolVersion = $protocolVersion;
        $this->serverParams = $serverParams;

        // Headers set et
        $this->setHeaders($headers);

        // Body set et
        if ($body === null) {
            $body = Stream::create('');
        } elseif (is_string($body)) {
            $body = Stream::create($body);
        } elseif (is_resource($body)) {
            $body = new Stream($body);
        } elseif (!$body instanceof StreamInterface) {
            throw new InvalidArgumentException('Invalid body provided');
        }
        $this->body = $body;

        // Query parameters parse et
        parse_str($this->uri->getQuery(), $this->query);
    }

    /**
     * Factory: PHP globals'den Request oluştur
     * 
     * @return self
     */
    public static function capture(): self
    {
        // Method
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // URI oluştur
        $uri = self::createUriFromGlobals();

        // Headers
        $headers = self::getHeadersFromGlobals();

        // Body
        $body = Stream::createFromFile('php://input', 'r');

        // Protocol version
        $protocol = isset($_SERVER['SERVER_PROTOCOL'])
            ? str_replace('HTTP/', '', $_SERVER['SERVER_PROTOCOL'])
            : '1.1';

        $request = new self(
            method: $method,
            uri: $uri,
            headers: $headers,
            body: $body,
            protocolVersion: $protocol,
            serverParams: $_SERVER
        );

        // POST data
        if ($method === 'POST' && !empty($_POST)) {
            $request->post = $_POST;
            $request->parsedBody = $_POST;
        }

        // Cookies
        $request->cookieParams = $_COOKIE;

        // Uploaded files
        if (!empty($_FILES)) {
            $request->uploadedFiles = self::normalizeFiles($_FILES);
        }

        return $request;
    }

    /**
     * $_SERVER'dan URI oluştur
     * 
     * @return UriInterface
     */
    private static function createUriFromGlobals(): UriInterface
    {
        $uri = new Uri('');

        // Scheme
        $scheme = 'http';
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $scheme = 'https';
        }
        $uri = $uri->withScheme($scheme);

        // Host
        if (isset($_SERVER['HTTP_HOST'])) {
            $uri = $uri->withHost($_SERVER['HTTP_HOST']);
        } elseif (isset($_SERVER['SERVER_NAME'])) {
            $uri = $uri->withHost($_SERVER['SERVER_NAME']);
        }

        // Port
        if (isset($_SERVER['SERVER_PORT'])) {
            $uri = $uri->withPort((int) $_SERVER['SERVER_PORT']);
        }

        // Path
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }
        $uri = $uri->withPath($path);

        // Query string
        if (isset($_SERVER['QUERY_STRING'])) {
            $uri = $uri->withQuery($_SERVER['QUERY_STRING']);
        }

        return $uri;
    }

    /**
     * $_SERVER'dan headers çıkar
     * 
     * @return array
     */
    private static function getHeadersFromGlobals(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            // HTTP_ ile başlayanlar header
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            }
            // Special case: CONTENT_TYPE ve CONTENT_LENGTH
            elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $name = str_replace('_', '-', $key);
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    /**
     * $_FILES array'ini normalize et (PSR-7 format)
     * 
     * @param array $files $_FILES array
     * @return array UploadedFile[]
     */
    private static function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $file) {
            if (is_array($file['tmp_name'])) {
                // Multiple file upload
                $normalized[$key] = [];
                foreach (array_keys($file['tmp_name']) as $index) {
                    $normalized[$key][$index] = new UploadedFile(
                        $file['tmp_name'][$index],
                        $file['size'][$index] ?? null,
                        $file['error'][$index] ?? UPLOAD_ERR_OK,
                        $file['name'][$index] ?? null,
                        $file['type'][$index] ?? null
                    );
                }
            } else {
                // Single file upload
                $normalized[$key] = UploadedFile::createFromFilesArray($file);
            }
        }

        return $normalized;
    }

    /**
     * Headers'ları set et
     * 
     * @param array $headers
     * @return void
     */
    private function setHeaders(array $headers): void
    {
        foreach ($headers as $name => $value) {
            $normalized = strtolower($name);
            $this->headerNames[$normalized] = $name;
            $this->headers[$name] = is_array($value) ? $value : [$value];
        }
    }

    // ==================== PSR-7 MessageInterface ====================

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): self
    {
        if ($version === $this->protocolVersion) {
            return $this;
        }

        $new = clone $this;
        $new->protocolVersion = $version;
        return $new;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    public function getHeader(string $name): array
    {
        $normalized = strtolower($name);
        
        if (!isset($this->headerNames[$normalized])) {
            return [];
        }

        $name = $this->headerNames[$normalized];
        return $this->headers[$name];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader(string $name, $value): self
    {
        $normalized = strtolower($name);
        $value = is_array($value) ? $value : [$value];

        $new = clone $this;
        $new->headerNames[$normalized] = $name;
        $new->headers[$name] = $value;

        return $new;
    }

    public function withAddedHeader(string $name, $value): self
    {
        $normalized = strtolower($name);
        $value = is_array($value) ? $value : [$value];

        $new = clone $this;
        
        if (isset($new->headerNames[$normalized])) {
            $name = $new->headerNames[$normalized];
            $new->headers[$name] = array_merge($new->headers[$name], $value);
        } else {
            $new->headerNames[$normalized] = $name;
            $new->headers[$name] = $value;
        }

        return $new;
    }

    public function withoutHeader(string $name): self
    {
        $normalized = strtolower($name);

        if (!isset($this->headerNames[$normalized])) {
            return $this;
        }

        $new = clone $this;
        $name = $new->headerNames[$normalized];
        unset($new->headers[$name], $new->headerNames[$normalized]);

        return $new;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): self
    {
        if ($body === $this->body) {
            return $this;
        }

        $new = clone $this;
        $new->body = $body;
        return $new;
    }

    // ==================== PSR-7 RequestInterface ====================

    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        
        if ($target === '') {
            $target = '/';
        }

        if ($this->uri->getQuery() !== '') {
            $target .= '?' . $this->uri->getQuery();
        }

        return $target;
    }

    public function withRequestTarget(string $requestTarget): self
    {
        $new = clone $this;
        $new->requestTarget = $requestTarget;
        return $new;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): self
    {
        $method = strtoupper($method);

        if ($method === $this->method) {
            return $this;
        }

        $new = clone $this;
        $new->method = $method;
        return $new;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): self
    {
        if ($uri === $this->uri) {
            return $this;
        }

        $new = clone $this;
        $new->uri = $uri;

        if (!$preserveHost || !$new->hasHeader('Host')) {
            if ($uri->getHost() !== '') {
                $new = $new->withHeader('Host', $uri->getHost());
            }
        }

        return $new;
    }

    // ==================== PSR-7 ServerRequestInterface ====================

    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    public function withCookieParams(array $cookies): self
    {
        $new = clone $this;
        $new->cookieParams = $cookies;
        return $new;
    }

    public function getQueryParams(): array
    {
        return $this->query;
    }

    public function withQueryParams(array $query): self
    {
        $new = clone $this;
        $new->query = $query;
        $new->inputCache = null;
        return $new;
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    public function withUploadedFiles(array $uploadedFiles): self
    {
        $new = clone $this;
        $new->uploadedFiles = $uploadedFiles;
        return $new;
    }

    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    public function withParsedBody($data): self
    {
        $new = clone $this;
        $new->parsedBody = $data;
        return $new;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name, $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute(string $name, $value): self
    {
        $new = clone $this;
        $new->attributes[$name] = $value;
        return $new;
    }

    public function withoutAttribute(string $name): self
    {
        if (!isset($this->attributes[$name])) {
            return $this;
        }

        $new = clone $this;
        unset($new->attributes[$name]);
        return $new;
    }

    // ==================== Framework Helpers ====================

    public function path(): string
    {
        return $this->uri->getPath();
    }

    public function url(): string
    {
        return (string) $this->uri->withQuery('')->withFragment('');
    }

    public function fullUrl(): string
    {
        return (string) $this->uri;
    }

    public function secure(): bool
    {
        return $this->uri->getScheme() === 'https';
    }

    public function setAttribute(string $key, mixed $value): self
    {
        return $this->withAttribute($key, $value);
    }

    /**
     * Authenticated user'ı al/set et
     * Interface'ten gelen metod - trait değil
     * 
     * @param mixed|null $user User instance (null ise getter)
     * @return mixed|self
     */
    public function user(mixed $user = null): mixed
    {
        if ($user === null) {
            return $this->user;
        }

        $this->user = $user;
        return $this;
    }

    /**
     * Route parametrelerini al/set et
     * Interface'ten gelen metod - trait'teki implementasyon kullanılıyor
     * Ama return type burada belirtiliyor (PHP trait + interface uyumu için)
     * 
     * @param array|null $parameters Parametreler (null ise getter)
     * @return array|self
     */
    public function routeParameters(?array $parameters = null): array|self
    {
        if ($parameters === null) {
            return $this->routeParams;
        }

        $this->routeParams = $parameters;
        $this->inputCache = null; // Cache invalidate

        return $this;
    }

    /**
     * Single uploaded file al
     * 
     * @param string $key File input name
     * @return UploadedFileInterface|null
     */
    public function file(string $key): ?UploadedFileInterface
    {
        return $this->uploadedFiles[$key] ?? null;
    }

    /**
     * Request'te file upload var mı?
     * 
     * @param string $key File input name
     * @return bool
     */
    public function hasFile(string $key): bool
    {
        $file = $this->file($key);
        return $file !== null && $file->getError() === UPLOAD_ERR_OK;
    }
}