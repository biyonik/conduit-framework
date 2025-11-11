<?php

declare(strict_types=1);

namespace Conduit\Http\Traits;

/**
 * Interacts With Input Trait
 * 
 * Request sınıfı için input helper metodları.
 * Query, POST, JSON, route params üzerinde işlemler yapar.
 * 
 * @package Conduit\Http\Traits
 */
trait InteractsWithInput
{
    /**
     * Query parameters
     */
    protected array $query = [];

    /**
     * POST parameters
     */
    protected array $post = [];

    /**
     * JSON decoded body
     */
    protected ?array $json = null;

    /**
     * Route parameters
     */
    protected array $routeParams = [];

    /**
     * Parsed body cache (query + post + json)
     */
    protected ?array $inputCache = null;

    /**
     * Herhangi bir input değerini al
     * 
     * Priority: route params > query > post > json
     * 
     * @param string|null $key Anahtar, null ise tüm input
     * @param mixed $default Varsayılan değer
     * @return mixed
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        $input = $this->all();

        if ($key === null) {
            return $input;
        }

        // Dot notation support (user.name gibi)
        return $this->dataGet($input, $key, $default);
    }

    /**
     * Tüm input verisini al
     * 
     * @return array
     */
    public function all(): array
    {
        if ($this->inputCache !== null) {
            return $this->inputCache;
        }

        // Priority order: route params > query > post > json
        $this->inputCache = array_replace_recursive(
            $this->json() ?? [],
            $this->post,
            $this->query,
            $this->routeParams
        );

        return $this->inputCache;
    }

    /**
     * Sadece belirtilen anahtarları al
     * 
     * @param array $keys İstenen anahtarlar
     * @return array
     */
    public function only(array $keys): array
    {
        $input = $this->all();
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->dataGet($input, $key);
        }

        return $results;
    }

    /**
     * Belirtilen anahtarlar hariç tüm input'u al
     * 
     * @param array $keys Hariç tutulacak anahtarlar
     * @return array
     */
    public function except(array $keys): array
    {
        $input = $this->all();

        foreach ($keys as $key) {
            unset($input[$key]);
        }

        return $input;
    }

    /**
     * Input'ta bir anahtar var mı?
     * 
     * @param string $key Kontrol edilecek anahtar
     * @return bool
     */
    public function has(string $key): bool
    {
        $input = $this->all();
        return $this->dataGet($input, $key) !== null;
    }

    /**
     * Input'ta birden fazla anahtar var mı?
     * 
     * @param array $keys Kontrol edilecek anahtarlar
     * @return bool Tümü varsa true
     */
    public function hasAny(array $keys): bool
    {
        $input = $this->all();

        foreach ($keys as $key) {
            if ($this->dataGet($input, $key) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Input'ta tüm anahtarlar var mı?
     * 
     * @param array $keys Kontrol edilecek anahtarlar
     * @return bool Tümü varsa true
     */
    public function filled(array $keys): bool
    {
        foreach ($keys as $key) {
            $value = $this->input($key);
            if (empty($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Query parameter al
     * 
     * @param string|null $key Anahtar
     * @param mixed $default Varsayılan değer
     * @return mixed
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    /**
     * POST parameter al
     * 
     * @param string|null $key Anahtar
     * @param mixed $default Varsayılan değer
     * @return mixed
     */
    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->post;
        }

        return $this->post[$key] ?? $default;
    }

    /**
     * JSON body'yi al (decoded)
     * 
     * @param string|null $key Anahtar
     * @param mixed $default Varsayılan değer
     * @return mixed
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        // Lazy parse JSON body
        if ($this->json === null && $this->isJson()) {
            $body = (string) $this->getBody();
            $this->json = json_decode($body, true) ?? [];
        }

        if ($key === null) {
            return $this->json;
        }

        return $this->dataGet($this->json ?? [], $key, $default);
    }

    /**
     * Route parametrelerini al/set et
     * 
     * @param array|null $parameters Parametreler (null ise getter)
     * @return array|static
     */
    public function routeParameters(?array $parameters = null)
    {
        if ($parameters === null) {
            return $this->routeParams;
        }

        $this->routeParams = $parameters;
        $this->inputCache = null; // Cache invalidate

        return $this;
    }

    /**
     * Integer olarak input al
     * 
     * @param string $key Anahtar
     * @param int $default Varsayılan değer
     * @return int
     */
    public function integer(string $key, int $default = 0): int
    {
        return (int) $this->input($key, $default);
    }

    /**
     * Float olarak input al
     * 
     * @param string $key Anahtar
     * @param float $default Varsayılan değer
     * @return float
     */
    public function float(string $key, float $default = 0.0): float
    {
        return (float) $this->input($key, $default);
    }

    /**
     * Boolean olarak input al
     * 
     * @param string $key Anahtar
     * @param bool $default Varsayılan değer
     * @return bool
     */
    public function boolean(string $key, bool $default = false): bool
    {
        return filter_var($this->input($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * String olarak input al
     * 
     * @param string $key Anahtar
     * @param string $default Varsayılan değer
     * @return string
     */
    public function string(string $key, string $default = ''): string
    {
        return (string) $this->input($key, $default);
    }

    /**
     * Dot notation ile array'den veri al
     * 
     * Örnek: dataGet($array, 'user.profile.name')
     * 
     * @param array $target Hedef array
     * @param string $key Dot notation key
     * @param mixed $default Varsayılan değer
     * @return mixed
     */
    protected function dataGet(array $target, string $key, mixed $default = null): mixed
    {
        if (isset($target[$key])) {
            return $target[$key];
        }

        // Dot notation parse et
        foreach (explode('.', $key) as $segment) {
            if (!is_array($target) || !array_key_exists($segment, $target)) {
                return $default;
            }
            $target = $target[$segment];
        }

        return $target;
    }
}