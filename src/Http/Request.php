<?php

declare(strict_types=1);

namespace Conduit\Http;

use Conduit\Http\Contracts\RequestInterface;
use Conduit\Http\Exceptions\ValidationException;
use Conduit\Http\Message\Stream;
use Conduit\Http\Message\UploadedFile;
use Conduit\Http\Message\Uri;
use Conduit\Http\Traits\InteractsWithInput;
use Conduit\Http\Traits\InteractsWithHeaders;
use Conduit\Validation\ValidationSchema;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * HTTP Request
 *
 * PSR-7 uyumlu HTTP request implementation.
 * Framework'ün API-first yaklaşımı için optimize edilmiştir.
 *
 * Özellikler:
 * - PSR-7 ServerRequestInterface implementation
 * - JSON request/response detection
 * - Input handling (query, body, route parameters)
 * - File upload support
 * - Header helpers
 * - Security helpers (IP, User-Agent, Bearer token)
 * - API-first design
 *
 * @package Conduit\Http
 */
class Request implements RequestInterface
{
    use InteractsWithInput;
    use InteractsWithHeaders;

    /**
     * HTTP method
     */
    private string $method;

    /**
     * Request URI
     */
    private UriInterface $uri;

    /**
     * Protocol version
     */
    private string $protocolVersion = '1.1';

    /**
     * Headers
     *
     * @var array<string, array<string>>
     */
    private array $headers = [];

    /**
     * Message body
     */
    private ?StreamInterface $body = null;

    /**
     * Server parameters ($_SERVER)
     *
     * @var array<string, mixed>
     */
    private array $serverParams;

    /**
     * Cookie parameters ($_COOKIE)
     *
     * @var array<string, string>
     */
    private array $cookieParams = [];

    /**
     * Query parameters ($_GET)
     *
     * @var array<string, mixed>
     */
    private array $queryParams = [];

    /**
     * Uploaded files ($_FILES)
     *
     * @var array<string, UploadedFileInterface>
     */
    private array $uploadedFiles = [];

    /**
     * Parsed body (POST data, JSON, etc.)
     *
     * @var mixed
     */
    private mixed $parsedBody = null;

    /**
     * Request attributes (custom data)
     *
     * @var array<string, mixed>
     */
    private array $attributes = [];

    /**
     * Constructor
     *
     * @param string $method HTTP method
     * @param UriInterface|string $uri Request URI
     * @param array<string, mixed> $headers Headers array
     * @param StreamInterface|string|null $body Request body
     * @param string $protocolVersion HTTP protocol version
     * @param array<string, mixed> $serverParams Server parameters
     */
    public function __construct(
        string $method,
        UriInterface|string $uri,
        array $headers = [],
        StreamInterface|string|null $body = null,
        string $protocolVersion = '1.1',
        array $serverParams = []
    ) {
        $this->method = strtoupper($method);
        $this->uri = is_string($uri) ? new Uri($uri) : $uri;
        $this->protocolVersion = $protocolVersion;
        $this->serverParams = $serverParams;

        // Headers'ı normalize et
        foreach ($headers as $name => $value) {
            $this->headers[strtolower($name)] = is_array($value) ? $value : [$value];
        }

        // Body set et
        if ($body !== null) {
            $this->body = is_string($body) ? Stream::create($body) : $body;
        }
    }

    // =====================================
    // PSR-7 ServerRequestInterface Methods
    // =====================================

    /**
     * @inheritDoc
     */
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    /**
     * @inheritDoc
     */
    public function withProtocolVersion(string $version): ServerRequestInterface
    {
        if ($version === $this->protocolVersion) {
            return $this;
        }

        $new = clone $this;
        $new->protocolVersion = $version;

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @inheritDoc
     */
    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    /**
     * @inheritDoc
     */
    public function getHeader(string $name): array
    {
        return $this->headers[strtolower($name)] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    /**
     * @inheritDoc
     */
    public function withHeader(string $name, $value): ServerRequestInterface
    {
        $normalized = strtolower($name);
        $new = clone $this;
        $new->headers[$normalized] = is_array($value) ? $value : [$value];

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function withAddedHeader(string $name, $value): ServerRequestInterface
    {
        $normalized = strtolower($name);
        $new = clone $this;

        if (isset($new->headers[$normalized])) {
            $new->headers[$normalized] = array_merge(
                $new->headers[$normalized],
                is_array($value) ? $value : [$value]
            );
        } else {
            $new->headers[$normalized] = is_array($value) ? $value : [$value];
        }

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function withoutHeader(string $name): ServerRequestInterface
    {
        $normalized = strtolower($name);

        if (!isset($this->headers[$normalized])) {
            return $this;
        }

        $new = clone $this;
        unset($new->headers[$normalized]);

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getBody(): StreamInterface
    {
        return $this->body ?? Stream::create();
    }

    /**
     * @inheritDoc
     */
    public function withBody(StreamInterface $body): ServerRequestInterface
    {
        if ($body === $this->body) {
            return $this;
        }

        $new = clone $this;
        $new->body = $body;

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getRequestTarget(): string
    {
        if ($this->uri === null) {
            return '/';
        }

        $target = $this->uri->getPath();

        if ($this->uri->getQuery() !== '') {
            $target .= '?' . $this->uri->getQuery();
        }

        return $target !== '' ? $target : '/';
    }

    /**
     * @inheritDoc
     */
    public function withRequestTarget(string $requestTarget): ServerRequestInterface
    {
        $new = clone $this;
        $new->uri = $this->uri->withPath($requestTarget);

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @inheritDoc
     */
    public function withMethod(string $method): ServerRequestInterface
    {
        $normalized = strtoupper($method);

        if ($normalized === $this->method) {
            return $this;
        }

        $new = clone $this;
        $new->method = $normalized;

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * @inheritDoc
     */
    public function withUri(UriInterface $uri, bool $preserveHost = false): ServerRequestInterface
    {
        if ($uri === $this->uri) {
            return $this;
        }

        $new = clone $this;
        $new->uri = $uri;

        if (!$preserveHost || !$this->hasHeader('Host')) {
            $host = $uri->getHost();
            if ($host !== '') {
                if (($port = $uri->getPort()) !== null) {
                    $host .= ':' . $port;
                }
                $new = $new->withHeader('Host', $host);
            }
        }

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /**
     * @inheritDoc
     */
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /**
     * @inheritDoc
     */
    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        $new = clone $this;
        $new->cookieParams = $cookies;

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * @inheritDoc
     */
    public function withQueryParams(array $query): ServerRequestInterface
    {
        $new = clone $this;
        $new->queryParams = $query;

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * @inheritDoc
     */
    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
    {
        $new = clone $this;
        $new->uploadedFiles = $uploadedFiles;

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    /**
     * @inheritDoc
     */
    public function withParsedBody($data): ServerRequestInterface
    {
        $new = clone $this;
        $new->parsedBody = $data;

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @inheritDoc
     */
    public function getAttribute(string $name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }

    public function hasAttribute(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    /**
     * @inheritDoc
     */
    public function withAttribute(string $name, $value): ServerRequestInterface
    {
        $new = clone $this;
        $new->attributes[$name] = $value;

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function withoutAttribute(string $name): ServerRequestInterface
    {
        if (!isset($this->attributes[$name])) {
            return $this;
        }

        $new = clone $this;
        unset($new->attributes[$name]);

        return $new;
    }

    /**
     * Gelen isteği, verilen şemaya göre doğrular.
     * Başarılı olursa, sadece doğrulanmış veriyi döndürür.
     * Başarısız olursa, otomatik olarak ValidationException fırlatır.
     *
     * @param ValidationSchema $schema Oluşturulan doğrulama şeması
     * @return array Doğrulanmış ve güvenli veri
     * @throws ValidationException
     */
    public function validate(ValidationSchema $schema): array
    {
        // Gelen tüm veriyi (GET + POST/JSON) doğrula
        $data = $this->all();

        $result = $schema->validate($data);

        if ($result->hasErrors()) {
            throw new ValidationException(
                errors: $result->getErrors(),
                message: 'Doğrulama hatası'
            );
        }

        // Sadece doğrulanmış ve temizlenmiş veriyi döndür
        return $result->getValidData();
    }

    // ===============================
    // Custom RequestInterface Methods
    // ===============================

    /**
     * HTTP method getter (alias for getMethod)
     *
     * @return string
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Request path getter (without query string)
     *
     * @return string
     */
    public function path(): string
    {
        return $this->uri->getPath();
    }

    /**
     * Full URL getter
     *
     * @return string
     */
    public function url(): string
    {
        $uri = $this->uri;
        $url = '';

        if ($uri->getScheme() !== '') {
            $url .= $uri->getScheme() . ':';
        }

        if ($uri->getAuthority() !== '') {
            $url .= '//' . $uri->getAuthority();
        }

        $url .= $uri->getPath();

        return $url;
    }

    /**
     * Full URL with query string getter
     *
     * @return string
     */
    public function fullUrl(): string
    {
        $url = $this->url();

        if ($this->uri->getQuery() !== '') {
            $url .= '?' . $this->uri->getQuery();
        }

        return $url;
    }

    /**
     * Check if request is HTTPS
     *
     * @return bool
     */
    public function isSecure(): bool
    {
        return $this->uri->getScheme() === 'https' ||
               $this->serverParams['HTTPS'] ?? false ||
               ($this->serverParams['SERVER_PORT'] ?? 80) == 443;
    }

    /**
     * Get client IP address
     *
     * @param bool $checkProxy Check proxy headers?
     * @return string
     */
    public function ip(bool $checkProxy = true): string
    {
        if ($checkProxy) {
            // Check proxy headers (in order of trust)
            $proxyHeaders = [
                'HTTP_X_REAL_IP',
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_FORWARDED',
                'HTTP_X_CLUSTER_CLIENT_IP',
                'HTTP_CLIENT_IP',
            ];

            foreach ($proxyHeaders as $header) {
                $ip = $this->serverParams[$header] ?? null;
                if ($ip) {
                    // X-Forwarded-For can contain multiple IPs, take first
                    $ip = trim(explode(',', $ip)[0]);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            }
        }

        return $this->serverParams['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get user agent
     *
     * @return string|null
     */
    public function userAgent(): ?string
    {
        return $this->header('User-Agent');
    }

    /**
     * Get header value (FIXED - No parent:: call)
     *
     * @param string $name Header name
     * @param string|null $default Default value
     * @return string|null
     */
    public function header(string $name, ?string $default = null): ?string
    {
        $value = $this->getHeaderLine($name);
        return $value !== '' ? $value : $default;
    }

    /**
     * Get bearer token from Authorization header
     *
     * @return string|null
     */
    public function bearerToken(): ?string
    {
        $authorization = $this->header('Authorization');

        if (!$authorization) {
            return null;
        }

        if (preg_match('/Bearer\s+(.+)/', $authorization, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Get input value from any source
     *
     * @param string|null $key Input key (null = all)
     * @param mixed $default Default value
     * @return mixed
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->all();
        }

        // 1. Route parameters (highest priority)
        if ($this->hasAttribute('route')) {
            $route = $this->getAttribute('route');
            if ($route && method_exists($route, 'parameter')) {
                $routeParam = $route->parameter($key);
                if ($routeParam !== null) {
                    return $routeParam;
                }
            }
        }

        // 2. Query parameters
        if (array_key_exists($key, $this->queryParams)) {
            return $this->queryParams[$key];
        }

        // 3. Body data
        $bodyParams = (array) $this->parsedBody;
        if (array_key_exists($key, $bodyParams)) {
            return $bodyParams[$key];
        }

        return $default;
    }

    /**
     * Get all input data merged
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $data = [];

        // Merge query parameters
        $data = array_merge($data, $this->queryParams);

        // Merge body parameters
        $bodyParams = (array) $this->parsedBody;
        $data = array_merge($data, $bodyParams);

        // Merge route parameters (highest priority)
        if ($this->hasAttribute('route')) {
            $route = $this->getAttribute('route');
            if ($route && method_exists($route, 'getParameters')) {
                $routeParams = $route->getParameters();
                $data = array_merge($data, $routeParams);
            }
        }

        return $data;
    }

    /**
     * Get only specified input keys
     *
     * @param array<string> $keys Keys to get
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        $all = $this->all();
        $result = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $all)) {
                $result[$key] = $all[$key];
            }
        }

        return $result;
    }

    /**
     * Get all input except specified keys
     *
     * @param array<string> $keys Keys to exclude
     * @return array<string, mixed>
     */
    public function except(array $keys): array
    {
        $all = $this->all();

        foreach ($keys as $key) {
            unset($all[$key]);
        }

        return $all;
    }

    /**
     * Check if input key exists
     *
     * @param string $key Input key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->input($key) !== null;
    }

    /**
     * Check if input key exists and is not empty
     *
     * @param string $key Input key
     * @return bool
     */
    public function filled(string $key): bool
    {
        $value = $this->input($key);

        if ($value === null) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if (is_array($value) && empty($value)) {
            return false;
        }

        return true;
    }

    /**
     * Get query parameter
     *
     * @param string $key Parameter key
     * @param mixed $default Default value
     * @return mixed
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    /**
     * Get POST/body parameter
     *
     * @param string $key Parameter key
     * @param mixed $default Default value
     * @return mixed
     */
    public function post(string $key, mixed $default = null): mixed
    {
        $bodyParams = (array) $this->parsedBody;
        return $bodyParams[$key] ?? $default;
    }

    /**
     * Get route parameter
     *
     * @param string $key Parameter key
     * @param mixed $default Default value
     * @return mixed
     */
    public function route(string $key, mixed $default = null): mixed
    {
        if (!$this->hasAttribute('route')) {
            return $default;
        }

        $route = $this->getAttribute('route');
        if (!$route || !method_exists($route, 'parameter')) {
            return $default;
        }

        return $route->parameter($key, $default);
    }

    /**
     * Get cookie value
     *
     * @param string $key Cookie key
     * @param mixed $default Default value
     * @return mixed
     */
    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookieParams[$key] ?? $default;
    }

    /**
     * Get file from upload
     *
     * @param string $key File key
     * @return UploadedFileInterface|null
     */
    public function file(string $key): ?UploadedFileInterface
    {
        return $this->uploadedFiles[$key] ?? null;
    }

    /**
     * Check if request has file upload
     *
     * @param string $key File key
     * @return bool
     */
    public function hasFile(string $key): bool
    {
        return isset($this->uploadedFiles[$key]);
    }

    /**
     * Check if request is JSON
     *
     * @return bool
     */
    public function isJson(): bool
    {
        $contentType = $this->header('Content-Type', '');
        $mimeType = strtolower(strtok($contentType, ';'));

        return in_array($mimeType, [
            'application/json',
            'application/vnd.api+json',
            'text/json',
        ], true);
    }

    /**
     * Check if request wants JSON response
     *
     * @return bool
     */
    public function wantsJson(): bool
    {
        $accept = $this->header('Accept', '');

        return str_contains($accept, 'application/json') ||
               str_contains($accept, 'application/vnd.api+json') ||
               str_contains($accept, 'text/json');
    }

    /**
     * Check if request expects JSON response
     *
     * @return bool
     */
    public function expectsJson(): bool
    {
        return $this->isAjax() || $this->wantsJson();
    }

    /**
     * Check if request is AJAX
     *
     * @return bool
     */
    public function isAjax(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Get request format
     *
     * @return string
     */
    public function format(): string
    {
        if ($this->wantsJson() || $this->isJson()) {
            return 'json';
        }

        $path = $this->path();
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return match(strtolower($extension)) {
            'xml' => 'xml',
            'html', 'htm' => 'html',
            'txt' => 'text',
            default => 'json', // API-first default
        };
    }

    // ===========================================
    // Static Factory Methods
    // ===========================================

    /**
     * Create request from PHP globals
     *
     * @return static
     */
    public static function createFromGlobals(): static
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = self::getUriFromGlobals();
        $headers = self::getHeadersFromGlobals();
        $body = self::getBodyFromGlobals();
        $protocolVersion = self::getProtocolVersionFromGlobals();

        $request = new static($method, $uri, $headers, $body, $protocolVersion, $_SERVER);

        return $request
            ->withCookieParams($_COOKIE ?? [])
            ->withQueryParams($_GET ?? [])
            ->withParsedBody(self::getParsedBodyFromGlobals())
            ->withUploadedFiles(self::getUploadedFilesFromGlobals());
    }

    /**
     * Get URI from globals
     *
     * @return string
     */
    private static function getUriFromGlobals(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                  ($_SERVER['SERVER_PORT'] ?? 80) == 443 ? 'https' : 'http';

        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        return $scheme . '://' . $host . $uri;
    }

    /**
     * Get headers from globals
     *
     * @return array<string, string>
     */
    private static function getHeadersFromGlobals(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = substr($key, 5);
                $headerName = str_replace('_', '-', strtolower($headerName));
                $headers[$headerName] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $headerName = str_replace('_', '-', strtolower($key));
                $headers[$headerName] = $value;
            }
        }

        return $headers;
    }

    /**
     * Get body from globals
     *
     * @return StreamInterface
     */
    private static function getBodyFromGlobals(): StreamInterface
    {
        return Stream::create(file_get_contents('php://input') ?: '');
    }

    /**
     * Get protocol version from globals
     *
     * @return string
     */
    private static function getProtocolVersionFromGlobals(): string
    {
        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
        return str_replace('HTTP/', '', $protocol);
    }

    /**
     * Get parsed body from globals
     *
     * @return mixed
     */
    private static function getParsedBodyFromGlobals(): mixed
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $input = file_get_contents('php://input') ?: '';
            return json_decode($input, true) ?? [];
        }

        return $_POST ?? [];
    }

    /**
     * Get uploaded files from globals
     *
     * @return array<string, UploadedFileInterface>
     */
    private static function getUploadedFilesFromGlobals(): array
    {
        $files = [];

        foreach ($_FILES ?? [] as $key => $file) {
            if (is_array($file['tmp_name'])) {
                // Multiple files
                $files[$key] = [];
                foreach ($file['tmp_name'] as $index => $tmpName) {
                    $files[$key][] = new UploadedFile(
                        $tmpName,
                        $file['size'][$index],
                        $file['error'][$index],
                        $file['name'][$index],
                        $file['type'][$index]
                    );
                }
            } else {
                // Single file
                $files[$key] = new UploadedFile(
                    $file['tmp_name'],
                    $file['size'],
                    $file['error'],
                    $file['name'],
                    $file['type']
                );
            }
        }

        return $files;
    }
}
