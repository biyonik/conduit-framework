<?php

declare(strict_types=1);

namespace Conduit\Http\Message;

use Psr\Http\Message\UriInterface;
use InvalidArgumentException;

/**
 * PSR-7 Uri Implementation
 * 
 * URL component'lerini parse eder ve manage eder.
 * Immutable: Her değişiklik yeni instance döner.
 * 
 * URI format: scheme://user:pass@host:port/path?query#fragment
 * 
 * @package Conduit\Http\Message
 */
class Uri implements UriInterface
{
    /**
     * URI scheme (http, https, ftp, etc.)
     */
    private string $scheme = '';

    /**
     * User info (username:password)
     */
    private string $userInfo = '';

    /**
     * Host (domain or IP)
     */
    private string $host = '';

    /**
     * Port number (null = default port for scheme)
     */
    private ?int $port = null;

    /**
     * Path (/path/to/resource)
     */
    private string $path = '';

    /**
     * Query string (key1=value1&key2=value2)
     */
    private string $query = '';

    /**
     * Fragment (anchor)
     */
    private string $fragment = '';

    /**
     * Default port'lar scheme bazlı
     */
    private const DEFAULT_PORTS = [
        'http' => 80,
        'https' => 443,
        'ftp' => 21,
        'ssh' => 22,
    ];

    /**
     * Constructor
     * 
     * @param string $uri Full URI string
     * @throws InvalidArgumentException
     */
    public function __construct(string $uri = '')
    {
        if ($uri !== '') {
            $this->parseUri($uri);
        }
    }

    /**
     * URI string'i parse et
     * 
     * @param string $uri URI string
     * @return void
     * @throws InvalidArgumentException
     */
    private function parseUri(string $uri): void
    {
        $parts = parse_url($uri);

        if ($parts === false) {
            throw new InvalidArgumentException('Invalid URI: ' . $uri);
        }

        $this->scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
        $this->userInfo = $parts['user'] ?? '';
        
        if (isset($parts['pass'])) {
            $this->userInfo .= ':' . $parts['pass'];
        }

        $this->host = isset($parts['host']) ? strtolower($parts['host']) : '';
        $this->port = $parts['port'] ?? null;
        $this->path = $parts['path'] ?? '';
        $this->query = $parts['query'] ?? '';
        $this->fragment = $parts['fragment'] ?? '';
    }

    /**
     * {@inheritDoc}
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * {@inheritDoc}
     */
    public function getAuthority(): string
    {
        if ($this->host === '') {
            return '';
        }

        $authority = $this->host;

        // User info ekle
        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }

        // Port ekle (eğer default değilse)
        if ($this->port !== null && !$this->isDefaultPort()) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    /**
     * {@inheritDoc}
     */
    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    /**
     * {@inheritDoc}
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * {@inheritDoc}
     */
    public function getPort(): ?int
    {
        return $this->isDefaultPort() ? null : $this->port;
    }

    /**
     * {@inheritDoc}
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * {@inheritDoc}
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * {@inheritDoc}
     */
    public function getFragment(): string
    {
        return $this->fragment;
    }

    /**
     * {@inheritDoc}
     */
    public function withScheme(string $scheme): UriInterface
    {
        $scheme = strtolower($scheme);

        if ($scheme === $this->scheme) {
            return $this;
        }

        $new = clone $this;
        $new->scheme = $scheme;

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function withUserInfo(string $user, ?string $password = null): UriInterface
    {
        $userInfo = $user;
        if ($password !== null && $password !== '') {
            $userInfo .= ':' . $password;
        }

        if ($userInfo === $this->userInfo) {
            return $this;
        }

        $new = clone $this;
        $new->userInfo = $userInfo;

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function withHost(string $host): UriInterface
    {
        $host = strtolower($host);

        if ($host === $this->host) {
            return $this;
        }

        $new = clone $this;
        $new->host = $host;

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function withPort(?int $port): UriInterface
    {
        if ($port !== null && ($port < 1 || $port > 65535)) {
            throw new InvalidArgumentException('Invalid port: ' . $port);
        }

        if ($port === $this->port) {
            return $this;
        }

        $new = clone $this;
        $new->port = $port;

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function withPath(string $path): UriInterface
    {
        if ($path === $this->path) {
            return $this;
        }

        $new = clone $this;
        $new->path = $path;

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function withQuery(string $query): UriInterface
    {
        // Leading '?' kaldır
        if (str_starts_with($query, '?')) {
            $query = substr($query, 1);
        }

        if ($query === $this->query) {
            return $this;
        }

        $new = clone $this;
        $new->query = $query;

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function withFragment(string $fragment): UriInterface
    {
        // Leading '#' kaldır
        if (str_starts_with($fragment, '#')) {
            $fragment = substr($fragment, 1);
        }

        if ($fragment === $this->fragment) {
            return $this;
        }

        $new = clone $this;
        $new->fragment = $fragment;

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        $uri = '';

        // Scheme
        if ($this->scheme !== '') {
            $uri .= $this->scheme . ':';
        }

        // Authority
        $authority = $this->getAuthority();
        if ($authority !== '') {
            $uri .= '//' . $authority;
        }

        // Path
        $path = $this->path;
        if ($path !== '') {
            // Path authority varsa / ile başlamalı
            if ($authority !== '' && !str_starts_with($path, '/')) {
                $path = '/' . $path;
            }
            $uri .= $path;
        }

        // Query
        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }

        // Fragment
        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }

        return $uri;
    }

    /**
     * Port default port mu kontrol et
     * 
     * @return bool
     */
    private function isDefaultPort(): bool
    {
        if ($this->port === null) {
            return true;
        }

        return isset(self::DEFAULT_PORTS[$this->scheme])
            && self::DEFAULT_PORTS[$this->scheme] === $this->port;
    }
}