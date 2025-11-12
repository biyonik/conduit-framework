<?php

declare(strict_types=1);

namespace Conduit\Http\Traits;

/**
 * Interacts With Headers Trait
 * 
 * Request ve Response sınıfları için header helper metodları.
 * PSR-7 header metodlarını kolaylaştırır.
 * 
 * @package Conduit\Http\Traits
 */
trait InteractsWithHeaders
{
    /**
     * Request'in JSON içerik tipinde olup olmadığını kontrol et
     * Content-Type: application/json kontrolü yapar
     * 
     * @return bool
     */
    public function isJson(): bool
    {
        $contentType = $this->getHeaderLine('Content-Type');
        
        return str_contains($contentType, 'application/json')
            || str_contains($contentType, '+json');
    }

    /**
     * Client JSON response bekliyor mu?
     * Accept: application/json kontrolü yapar
     * 
     * @return bool
     */
    public function expectsJson(): bool
    {
        $accept = $this->getHeaderLine('Accept');
        
        return str_contains($accept, 'application/json')
            || str_contains($accept, '+json');
    }

    /**
     * Client JSON response istiyor mu?
     * isJson() VEYA expectsJson() true ise true döner
     * 
     * @return bool
     */
    public function wantsJson(): bool
    {
        return $this->isJson() || $this->expectsJson();
    }

    /**
     * Request AJAX/XMLHttpRequest mi?
     * X-Requested-With: XMLHttpRequest kontrolü yapar
     * 
     * @return bool
     */
    public function ajax(): bool
    {
        return $this->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Client IP adresini al
     * Proxy header'larını da dikkate alır
     * 
     * @return string|null
     */
    public function ip(): ?string
    {
        // Trusted proxy header'ları (sırayla kontrol et)
        $headers = [
            'X-Forwarded-For',   // Standard proxy header
            'X-Real-IP',         // Nginx proxy
            'Client-IP',         // Apache proxy
            'X-Client-IP',       // Alternative
            'X-Cluster-Client-IP', // Rackspace
        ];

        foreach ($headers as $header) {
            if ($this->hasHeader($header)) {
                $ip = $this->getHeaderLine($header);
                
                // X-Forwarded-For birden fazla IP içerebilir (comma-separated)
                // İlk IP gerçek client IP'sidir
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }

                // IP validation
                if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    return $ip;
                }
            }
        }

        // Fallback: REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    /**
     * User agent string'ini al
     * 
     * @return string|null
     */
    public function userAgent(): ?string
    {
        return $this->getHeaderLine('User-Agent') ?: null;
    }

    /**
     * Authorization Bearer token'ını al
     * Authorization: Bearer {token} header'ından çeker
     * 
     * @return string|null
     */
    public function bearerToken(): ?string
    {
        $authorization = $this->getHeaderLine('Authorization');

        if (empty($authorization)) {
            return null;
        }

        // "Bearer {token}" format kontrolü
        if (preg_match('/Bearer\s+(.+)/i', $authorization, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Basic Auth credentials al
     * Authorization: Basic {base64(username:password)}
     * 
     * @return array|null ['username' => '...', 'password' => '...']
     */
    public function basicAuth(): ?array
    {
        $authorization = $this->getHeaderLine('Authorization');

        if (empty($authorization)) {
            return null;
        }

        // "Basic {credentials}" format kontrolü
        if (preg_match('/Basic\s+(.+)/i', $authorization, $matches)) {
            $credentials = base64_decode($matches[1]);
            
            if (str_contains($credentials, ':')) {
                [$username, $password] = explode(':', $credentials, 2);
                return [
                    'username' => $username,
                    'password' => $password,
                ];
            }
        }

        return null;
    }

    /**
     * Referer header'ını al
     * 
     * @return string|null
     */
    public function referer(): ?string
    {
        return $this->getHeaderLine('Referer') ?: null;
    }

    /**
     * Origin header'ını al (CORS için)
     * 
     * @return string|null
     */
    public function origin(): ?string
    {
        return $this->getHeaderLine('Origin') ?: null;
    }

    /**
     * Content-Length header'ını al
     * 
     * @return int|null Byte cinsinden
     */
    public function contentLength(): ?int
    {
        $length = $this->getHeaderLine('Content-Length');
        return $length !== '' ? (int) $length : null;
    }

    /**
     * Content-Type header'ını al
     * 
     * @return string|null
     */
    public function contentType(): ?string
    {
        return $this->getHeaderLine('Content-Type') ?: null;
    }

    /**
     * Accept header'ını al
     * 
     * @return string|null
     */
    public function acceptHeader(): ?string
    {
        return $this->getHeaderLine('Accept') ?: null;
    }

    /**
     * Accept-Language header'ını al
     * 
     * @return string|null
     */
    public function acceptLanguage(): ?string
    {
        return $this->getHeaderLine('Accept-Language') ?: null;
    }

    /**
     * Accept-Encoding header'ını al
     * 
     * @return string|null
     */
    public function acceptEncoding(): ?string
    {
        return $this->getHeaderLine('Accept-Encoding') ?: null;
    }

    /**
     * Client gzip encoding destekliyor mu?
     * 
     * @return bool
     */
    public function acceptsGzip(): bool
    {
        $encoding = $this->acceptEncoding();
        return $encoding && str_contains($encoding, 'gzip');
    }

    /**
     * Header'ın belirli bir değer içerip içermediğini kontrol et
     * 
     * @param string $header Header adı
     * @param string $value Aranacak değer
     * @return bool
     */
    public function headerContains(string $header, string $value): bool
    {
        $headerValue = $this->getHeaderLine($header);
        return str_contains(strtolower($headerValue), strtolower($value));
    }

    /**
     * Tüm header'ları normalize edilmiş formatta al
     * 
     * @return array [normalized-name => value]
     */
    public function allHeaders(): array
    {
        $headers = [];
        
        foreach ($this->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        return $headers;
    }
}