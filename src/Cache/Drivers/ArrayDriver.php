<?php

declare(strict_types=1);

namespace Conduit\Cache\Drivers;

use Conduit\Cache\Contracts\CacheInterface;

/**
 * Array Cache Driver
 *
 * In-memory cache (request lifecycle only).
 * Testing ve development için kullanılır.
 *
 * @package Conduit\Cache\Drivers
 */
class ArrayDriver implements CacheInterface
{
    /**
     * Cached items
     *
     * @var array<string, array{value: mixed, expires_at: int|null}>
     */
    protected array $storage = [];

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->storage[$key])) {
            return $default;
        }

        $item = $this->storage[$key];

        // Check expiration
        if ($item['expires_at'] !== null && time() >= $item['expires_at']) {
            unset($this->storage[$key]);
            return $default;
        }

        return $item['value'];
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $this->storage[$key] = [
            'value' => $value,
            'expires_at' => $this->calculateExpiration($ttl),
        ];

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        if (isset($this->storage[$key])) {
            unset($this->storage[$key]);
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $this->storage = [];
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function increment(string $key, int $value = 1): int|bool
    {
        $current = $this->get($key, 0);

        if (!is_int($current)) {
            return false;
        }

        $new = $current + $value;
        $this->set($key, $new);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->increment($key, -$value);
    }

    /**
     * {@inheritdoc}
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value, null);
    }

    /**
     * {@inheritdoc}
     */
    public function remember(string $key, null|int|\DateInterval $ttl, \Closure $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function rememberForever(string $key, \Closure $callback): mixed
    {
        return $this->remember($key, null, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->delete($key);

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function add(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        if ($this->has($key)) {
            return false;
        }

        return $this->set($key, $value, $ttl);
    }

    /**
     * Get all cached items (for testing)
     *
     * @return array
     */
    public function all(): array
    {
        return $this->storage;
    }

    /**
     * Calculate expiration timestamp
     *
     * @param null|int|\DateInterval $ttl
     * @return int|null
     */
    protected function calculateExpiration(null|int|\DateInterval $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }

        if ($ttl instanceof \DateInterval) {
            $now = new \DateTime();
            $now->add($ttl);
            return $now->getTimestamp();
        }

        return time() + $ttl;
    }
}
