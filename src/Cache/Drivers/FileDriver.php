<?php

declare(strict_types=1);

namespace Conduit\Cache\Drivers;

use Conduit\Cache\Contracts\CacheInterface;

/**
 * File Cache Driver
 *
 * Shared hosting için optimize edilmiş dosya tabanlı cache driver.
 *
 * Özellikler:
 * - Atomic write operations (race condition güvenli)
 * - Automatic garbage collection
 * - Configurable permissions
 * - OPcache friendly
 *
 * @package Conduit\Cache\Drivers
 */
class FileDriver implements CacheInterface
{
    /**
     * Cache directory path
     *
     * @var string
     */
    protected string $path;

    /**
     * File permissions
     *
     * @var int
     */
    protected int $filePermission;

    /**
     * Directory permissions
     *
     * @var int
     */
    protected int $dirPermission;

    /**
     * Cache key prefix
     *
     * @var string
     */
    protected string $prefix;

    /**
     * Constructor
     *
     * @param string $path
     * @param string $prefix
     * @param int $filePermission
     * @param int $dirPermission
     */
    public function __construct(
        string $path,
        string $prefix = 'conduit',
        int $filePermission = 0644,
        int $dirPermission = 0755
    ) {
        $this->path = rtrim($path, '/');
        $this->prefix = $prefix;
        $this->filePermission = $filePermission;
        $this->dirPermission = $dirPermission;

        $this->ensureDirectoryExists($this->path);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->path($key);

        if (!file_exists($file)) {
            return $default;
        }

        try {
            $contents = file_get_contents($file);

            if ($contents === false) {
                return $default;
            }

            $data = unserialize($contents);

            // Check if expired
            if ($data['expires_at'] !== null && time() >= $data['expires_at']) {
                $this->delete($key);
                return $default;
            }

            return $data['value'];
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $file = $this->path($key);
        $this->ensureDirectoryExists(dirname($file));

        $expiresAt = $this->calculateExpiration($ttl);

        $data = serialize([
            'value' => $value,
            'expires_at' => $expiresAt,
            'created_at' => time(),
        ]);

        // Atomic write using temp file + rename
        $tempFile = $file . '.' . uniqid('', true) . '.tmp';

        if (file_put_contents($tempFile, $data, LOCK_EX) === false) {
            return false;
        }

        chmod($tempFile, $this->filePermission);

        return rename($tempFile, $file);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        $file = $this->path($key);

        if (file_exists($file)) {
            return @unlink($file);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $files = glob($this->path . '/*');

        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

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
        $success = true;

        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;

        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }

        return $success;
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
     * Garbage collection - remove expired cache files
     *
     * @return int Number of files deleted
     */
    public function gc(): int
    {
        $files = glob($this->path . '/*');
        $deleted = 0;

        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            try {
                $contents = file_get_contents($file);

                if ($contents === false) {
                    continue;
                }

                $data = unserialize($contents);

                if ($data['expires_at'] !== null && time() >= $data['expires_at']) {
                    @unlink($file);
                    $deleted++;
                }
            } catch (\Throwable $e) {
                // Corrupted file - delete it
                @unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Get full file path for a cache key
     *
     * @param string $key
     * @return string
     */
    protected function path(string $key): string
    {
        $hash = sha1($this->prefix . $key);

        // Store in subdirectories for better filesystem performance
        // First 2 chars as directory
        $dir = substr($hash, 0, 2);

        return $this->path . '/' . $dir . '/' . $hash;
    }

    /**
     * Ensure directory exists
     *
     * @param string $path
     * @return void
     */
    protected function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            @mkdir($path, $this->dirPermission, true);
        }
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
