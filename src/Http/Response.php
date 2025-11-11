<?php

declare(strict_types=1);

namespace Conduit\Http;

use Conduit\Http\Contracts\ResponseInterface;
use Conduit\Http\Message\Stream;
use Psr\Http\Message\StreamInterface;
use InvalidArgumentException;

/**
 * HTTP Response
 * 
 * PSR-7 Response implementasyonu + Framework helper'ları.
 * Immutable: Her değişiklik yeni instance döner.
 * 
 * @package Conduit\Http
 */
class Response implements ResponseInterface
{
    /**
     * HTTP status code
     */
    private int $statusCode;

    /**
     * Reason phrase (status mesajı)
     */
    private string $reasonPhrase;

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
     * Response body stream
     */
    private StreamInterface $body;

    /**
     * Cookies to set
     */
    private array $cookies = [];

    /**
     * HTTP status phrases
     */
    private const PHRASES = [
        100 => 'Continue', 101 => 'Switching Protocols', 102 => 'Processing',
        200 => 'OK', 201 => 'Created', 202 => 'Accepted', 203 => 'Non-Authoritative Information',
        204 => 'No Content', 205 => 'Reset Content', 206 => 'Partial Content', 207 => 'Multi-Status',
        208 => 'Already Reported', 226 => 'IM Used',
        300 => 'Multiple Choices', 301 => 'Moved Permanently', 302 => 'Found', 303 => 'See Other',
        304 => 'Not Modified', 305 => 'Use Proxy', 307 => 'Temporary Redirect', 308 => 'Permanent Redirect',
        400 => 'Bad Request', 401 => 'Unauthorized', 402 => 'Payment Required', 403 => 'Forbidden',
        404 => 'Not Found', 405 => 'Method Not Allowed', 406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required', 408 => 'Request Timeout', 409 => 'Conflict',
        410 => 'Gone', 411 => 'Length Required', 412 => 'Precondition Failed', 413 => 'Payload Too Large',
        414 => 'URI Too Long', 415 => 'Unsupported Media Type', 416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed', 418 => "I'm a teapot", 421 => 'Misdirected Request',
        422 => 'Unprocessable Entity', 423 => 'Locked', 424 => 'Failed Dependency', 425 => 'Too Early',
        426 => 'Upgrade Required', 428 => 'Precondition Required', 429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large', 451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error', 501 => 'Not Implemented', 502 => 'Bad Gateway',
        503 => 'Service Unavailable', 504 => 'Gateway Timeout', 505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates', 507 => 'Insufficient Storage', 508 => 'Loop Detected',
        510 => 'Not Extended', 511 => 'Network Authentication Required',
    ];

    /**
     * Constructor
     * 
     * @param int $statusCode HTTP status code
     * @param array $headers Headers
     * @param StreamInterface|string|resource|null $body Body
     * @param string $protocolVersion Protocol version
     * @param string|null $reasonPhrase Custom reason phrase
     */
    public function __construct(
        int $statusCode = 200,
        array $headers = [],
        mixed $body = null,
        string $protocolVersion = '1.1',
        ?string $reasonPhrase = null
    ) {
        $this->statusCode = $statusCode;
        $this->protocolVersion = $protocolVersion;
        $this->reasonPhrase = $reasonPhrase ?? self::PHRASES[$statusCode] ?? 'Unknown';

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

    public function setBody(StreamInterface $body): void
    {
        $this->body = $body;
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

    // ==================== PSR-7 ResponseInterface ====================

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function status(): int
    {
        return $this->statusCode;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): self
    {
        if ($code < 100 || $code > 599) {
            throw new InvalidArgumentException('Invalid status code: ' . $code);
        }

        if ($code === $this->statusCode && $reasonPhrase === $this->reasonPhrase) {
            return $this;
        }

        $new = clone $this;
        $new->statusCode = $code;
        $new->reasonPhrase = $reasonPhrase !== '' ? $reasonPhrase : (self::PHRASES[$code] ?? 'Unknown');

        return $new;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    // ==================== Framework Helpers ====================

    public function send(): void
    {
        // Status line gönder
        if (!headers_sent()) {
            header(sprintf(
                'HTTP/%s %d %s',
                $this->protocolVersion,
                $this->statusCode,
                $this->reasonPhrase
            ), true, $this->statusCode);

            // Headers gönder
            foreach ($this->headers as $name => $values) {
                foreach ($values as $value) {
                    header("{$name}: {$value}", false);
                }
            }

            // Cookies gönder
            foreach ($this->cookies as $cookie) {
                setcookie(...$cookie);
            }
        }

        // Body gönder
        echo $this->body;
    }

    public function header(string $name, string|array $value): self
    {
        return $this->withHeader($name, $value);
    }

    public function withHeaders(array $headers): self
    {
        $new = clone $this;
        
        foreach ($headers as $name => $value) {
            $new = $new->withHeader($name, $value);
        }

        return $new;
    }

    public function withContentType(string $contentType): self
    {
        return $this->withHeader('Content-Type', $contentType);
    }

    public function cookie(
        string $name,
        string $value,
        int $expire = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httponly = true,
        string $sameSite = 'Lax'
    ): self {
        $new = clone $this;
        
        $new->cookies[] = [
            'name' => $name,
            'value' => $value,
            'expires_or_options' => [
                'expires' => $expire,
                'path' => $path,
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => $sameSite,
            ],
        ];

        return $new;
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    public function isServerError(): bool
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    public function isRedirect(): bool
    {
        return in_array($this->statusCode, [301, 302, 303, 307, 308]);
    }

    public function isJson(): bool
    {
        $contentType = $this->getHeaderLine('Content-Type');
        return str_contains($contentType, 'application/json');
    }

    public function content(): string
    {
        return (string) $this->body;
    }

    public function json(bool $assoc = true): mixed
    {
        return json_decode($this->content(), $assoc);
    }

    public function setEtag(string $etag, bool $weak = false): self
    {
        $value = $weak ? "W/\"{$etag}\"" : "\"{$etag}\"";
        return $this->withHeader('ETag', $value);
    }

    public function setCacheControl(string $value): self
    {
        return $this->withHeader('Cache-Control', $value);
    }

    public function setNotCacheable(): self
    {
        return $this->withHeaders([
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Security headers ekle
     * 
     * @return self
     */
    public function withSecurityHeaders(): self
    {
        return $this->withHeaders([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ]);
    }

    /**
     * CORS headers ekle
     * 
     * @param string $origin İzin verilen origin (* = all)
     * @param array $methods İzin verilen methodlar
     * @param array $headers İzin verilen header'lar
     * @param int $maxAge Preflight cache süresi (saniye)
     * @return self
     */
    public function withCorsHeaders(
        string $origin = '*',
        array $methods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        array $headers = ['Content-Type', 'Authorization'],
        int $maxAge = 86400
    ): self {
        return $this->withHeaders([
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => implode(', ', $methods),
            'Access-Control-Allow-Headers' => implode(', ', $headers),
            'Access-Control-Max-Age' => (string) $maxAge,
        ]);
    }
}