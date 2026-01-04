<?php

declare(strict_types=1);

namespace Conduit\Cache\Drivers;

use Conduit\Cache\Contracts\CacheInterface;
use Conduit\Database\Contracts\ConnectionInterface;

/**
 * Database Cache Driver
 *
 * Shared hosting için MySQL/MariaDB tabanlı cache driver.
 * Redis olmayan ortamlar için File driver'a alternatif.
 *
 * @package Conduit\Cache\Drivers
 */
class DatabaseDriver implements CacheInterface
{
    /**
     * Database connection
     *
     * @var ConnectionInterface
     */
    protected ConnectionInterface $connection;

    /**
     * Cache table name
     *
     * @var string
     */
    protected string $table;

    /**
     * Cache key prefix
     *
     * @var string
     */
    protected string $prefix;

    /**
     * Constructor
     *
     * @param ConnectionInterface $connection
     * @param string $table
     * @param string $prefix
     */
    public function __construct(ConnectionInterface $connection, string $table = 'cache', string $prefix = 'conduit')
    {
        // SECURITY: Validate table name
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException("Invalid table name: '{$table}'");
        }

        $this->connection = $connection;
        $this->table = $table;
        $this->prefix = $prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $result = $this->connection->select(
            "SELECT `value`, `expiration` FROM `{$this->table}` WHERE `key` = ? LIMIT 1",
            [$this->prefixKey($key)]
        );

        if (empty($result)) {
            return $default;
        }

        $row = $result[0];

        // Check expiration
        if ($row['expiration'] > 0 && time() >= $row['expiration']) {
            $this->delete($key);
            return $default;
        }

        return unserialize($row['value']);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $prefixedKey = $this->prefixKey($key);
        $serialized = serialize($value);
        $expiration = $this->calculateExpiration($ttl);

        // Use INSERT ... ON DUPLICATE KEY UPDATE for atomic upsert
        $affected = $this->connection->statement(
            "INSERT INTO `{$this->table}` (`key`, `value`, `expiration`)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `expiration` = VALUES(`expiration`)",
            [$prefixedKey, $serialized, $expiration]
        );

        return $affected > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        $affected = $this->connection->delete(
            "DELETE FROM `{$this->table}` WHERE `key` = ?",
            [$this->prefixKey($key)]
        );

        return $affected > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        // Only clear keys with this prefix
        $this->connection->delete(
            "DELETE FROM `{$this->table}` WHERE `key` LIKE ?",
            [$this->prefix . '%']
        );

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
        $result = $this->connection->select(
            "SELECT 1 FROM `{$this->table}` WHERE `key` = ? AND (`expiration` = 0 OR `expiration` > ?) LIMIT 1",
            [$this->prefixKey($key), time()]
        );

        return !empty($result);
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
     * Garbage collection - remove expired entries
     *
     * @return int Number of rows deleted
     */
    public function gc(): int
    {
        return $this->connection->delete(
            "DELETE FROM `{$this->table}` WHERE `expiration` > 0 AND `expiration` < ?",
            [time()]
        );
    }

    /**
     * Prefix cache key
     *
     * @param string $key
     * @return string
     */
    protected function prefixKey(string $key): string
    {
        return $this->prefix . ':' . $key;
    }

    /**
     * Calculate expiration timestamp
     *
     * @param null|int|\DateInterval $ttl
     * @return int
     */
    protected function calculateExpiration(null|int|\DateInterval $ttl): int
    {
        if ($ttl === null) {
            return 0; // 0 = never expires
        }

        if ($ttl instanceof \DateInterval) {
            $now = new \DateTime();
            $now->add($ttl);
            return $now->getTimestamp();
        }

        return time() + $ttl;
    }
}
