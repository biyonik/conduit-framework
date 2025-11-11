<?php

declare(strict_types=1);

namespace Conduit\Http;

use InvalidArgumentException;

/**
 * Redirect Response
 * 
 * HTTP redirect response helper.
 * 301, 302, 303, 307, 308 redirect'leri destekler.
 * 
 * @package Conduit\Http
 */
class RedirectResponse extends Response
{
    /**
     * Target URL
     */
    private string $targetUrl;

    /**
     * Constructor
     * 
     * @param string $url Redirect edilecek URL
     * @param int $statusCode HTTP status code (default: 302)
     * @param array $headers Ek headers
     * @throws InvalidArgumentException
     */
    public function __construct(
        string $url,
        int $statusCode = 302,
        array $headers = []
    ) {
        // Redirect status code validation
        if (!in_array($statusCode, [301, 302, 303, 307, 308])) {
            throw new InvalidArgumentException('Invalid redirect status code: ' . $statusCode);
        }

        $this->targetUrl = $url;

        // Location header ekle
        $headers['Location'] = $url;

        parent::__construct(
            statusCode: $statusCode,
            headers: $headers,
            body: null
        );
    }

    /**
     * 301 Moved Permanently redirect
     * 
     * SEO için - kalıcı taşınma
     * Search engine'ler eski URL'i indexten çıkarır
     * 
     * @param string $url Target URL
     * @param array $headers Ek headers
     * @return self
     */
    public static function permanent(string $url, array $headers = []): self
    {
        return new self($url, 301, $headers);
    }

    /**
     * 302 Found redirect (temporary)
     * 
     * Geçici redirect (default)
     * Eski URL index'te kalır
     * 
     * @param string $url Target URL
     * @param array $headers Ek headers
     * @return self
     */
    public static function temporary(string $url, array $headers = []): self
    {
        return new self($url, 302, $headers);
    }

    /**
     * 303 See Other redirect
     * 
     * POST sonrası GET'e redirect için
     * POST-Redirect-GET pattern
     * 
     * @param string $url Target URL
     * @param array $headers Ek headers
     * @return self
     */
    public static function seeOther(string $url, array $headers = []): self
    {
        return new self($url, 303, $headers);
    }

    /**
     * 307 Temporary Redirect
     * 
     * Method korunur (POST → POST)
     * 302'nin strict versiyonu
     * 
     * @param string $url Target URL
     * @param array $headers Ek headers
     * @return self
     */
    public static function temporaryPreserveMethod(string $url, array $headers = []): self
    {
        return new self($url, 307, $headers);
    }

    /**
     * 308 Permanent Redirect
     * 
     * Method korunur (POST → POST)
     * 301'in strict versiyonu
     * 
     * @param string $url Target URL
     * @param array $headers Ek headers
     * @return self
     */
    public static function permanentPreserveMethod(string $url, array $headers = []): self
    {
        return new self($url, 308, $headers);
    }

    /**
     * Back redirect (Referer'a dön)
     * 
     * @param string $fallback Referer yoksa fallback URL
     * @param int $statusCode HTTP status code
     * @param array $headers Ek headers
     * @return self
     */
    public static function back(
        string $fallback = '/',
        int $statusCode = 302,
        array $headers = []
    ): self {
        $url = $_SERVER['HTTP_REFERER'] ?? $fallback;
        return new self($url, $statusCode, $headers);
    }

    /**
     * Route'a redirect (named route)
     * 
     * Bu metod Router ile entegre olacak
     * Şimdilik placeholder
     * 
     * @param string $name Route name
     * @param array $parameters Route parameters
     * @param int $statusCode HTTP status code
     * @param array $headers Ek headers
     * @return self
     */
    public static function route(
        string $name,
        array $parameters = [],
        int $statusCode = 302,
        array $headers = []
    ): self {
        // TODO: Router ile entegre edilecek
        // $url = app('router')->route($name, $parameters);
        
        // Şimdilik fallback
        throw new \RuntimeException('RedirectResponse::route() not yet implemented. Use RedirectResponse::to() instead.');
    }

    /**
     * URL'e redirect (alias for constructor)
     * 
     * @param string $url Target URL
     * @param int $statusCode HTTP status code
     * @param array $headers Ek headers
     * @return self
     */
    public static function to(
        string $url,
        int $statusCode = 302,
        array $headers = []
    ): self {
        return new self($url, $statusCode, $headers);
    }

    /**
     * External URL'e redirect (nofollow, noopener ekler)
     * 
     * @param string $url External URL
     * @param int $statusCode HTTP status code
     * @param array $headers Ek headers
     * @return self
     */
    public static function away(
        string $url,
        int $statusCode = 302,
        array $headers = []
    ): self {
        // Güvenlik: External link için rel attribute'leri
        // (HTML meta tag olarak değil, redirect ise sadece redirect yapıyoruz)
        return new self($url, $statusCode, $headers);
    }

    /**
     * Target URL'i al
     * 
     * @return string
     */
    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }

    /**
     * Flash data ekle (session ile kullanılacak)
     * 
     * Redirect sonrası data taşımak için:
     * - Success messages
     * - Error messages
     * - Old input (validation error sonrası)
     * 
     * Bu metod şimdilik placeholder - Session katmanı eklendiğinde aktif olacak
     * 
     * @param string $key Flash key
     * @param mixed $value Flash value
     * @return self
     */
    public function with(string $key, mixed $value): self
    {
        // TODO: Session flash data
        // session()->flash($key, $value);
        
        return $this;
    }

    /**
     * Flash message ekle (with() alias)
     * 
     * @param string $message Mesaj
     * @param string $type Mesaj tipi (success, error, warning, info)
     * @return self
     */
    public function withMessage(string $message, string $type = 'success'): self
    {
        return $this->with('message', [
            'text' => $message,
            'type' => $type,
        ]);
    }

    /**
     * Success message ekle
     * 
     * @param string $message Başarı mesajı
     * @return self
     */
    public function withSuccess(string $message): self
    {
        return $this->withMessage($message, 'success');
    }

    /**
     * Error message ekle
     * 
     * @param string $message Hata mesajı
     * @return self
     */
    public function withError(string $message): self
    {
        return $this->withMessage($message, 'error');
    }

    /**
     * Warning message ekle
     * 
     * @param string $message Uyarı mesajı
     * @return self
     */
    public function withWarning(string $message): self
    {
        return $this->withMessage($message, 'warning');
    }

    /**
     * Info message ekle
     * 
     * @param string $message Bilgi mesajı
     * @return self
     */
    public function withInfo(string $message): self
    {
        return $this->withMessage($message, 'info');
    }

    /**
     * Validation error'lar ile redirect (old input + errors)
     * 
     * @param array $errors Validation errors
     * @param array $input Old input data
     * @return self
     */
    public function withErrors(array $errors, array $input = []): self
    {
        $this->with('errors', $errors);
        
        if (!empty($input)) {
            $this->with('old', $input);
        }

        return $this;
    }

    /**
     * Query string parametreleri ekle
     * 
     * @param array $query Query parametreleri
     * @return self
     */
    public function withQuery(array $query): self
    {
        // URL'e query string ekle
        $separator = str_contains($this->targetUrl, '?') ? '&' : '?';
        $queryString = http_build_query($query);
        
        if ($queryString !== '') {
            $this->targetUrl .= $separator . $queryString;
            
            // Location header'ı güncelle
            $new = $this->withHeader('Location', $this->targetUrl);
            $new->targetUrl = $this->targetUrl;
            
            return $new;
        }

        return $this;
    }

    /**
     * Fragment (anchor) ekle
     * 
     * @param string $fragment Fragment (#section gibi)
     * @return self
     */
    public function withFragment(string $fragment): self
    {
        // # prefix kaldır
        $fragment = ltrim($fragment, '#');
        
        // URL'e fragment ekle
        $this->targetUrl .= '#' . $fragment;
        
        // Location header'ı güncelle
        $new = $this->withHeader('Location', $this->targetUrl);
        $new->targetUrl = $this->targetUrl;
        
        return $new;
    }
}