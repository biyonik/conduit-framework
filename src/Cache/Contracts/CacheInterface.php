<?php

declare(strict_types=1);

namespace Conduit\Cache\Contracts;

/**
 * Cache Interface
 *
 * PSR-16 SimpleCache benzeri interface.
 * Shared hosting ortamları için optimize edilmiş cache yönetimi.
 *
 * @package Conduit\Cache\Contracts
 */
interface CacheInterface
{
    /**
     * Fetch a value from the cache
     *
     * @param string $key Cache key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Persist data in the cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param null|int|\DateInterval $ttl Time to live in seconds (null = forever)
     * @return bool True on success
     */
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool;

    /**
     * Delete an item from the cache by its unique key
     *
     * @param string $key
     * @return bool True if deleted, false if not found
     */
    public function delete(string $key): bool;

    /**
     * Wipe clean the entire cache
     *
     * @return bool True on success
     */
    public function clear(): bool;

    /**
     * Obtains multiple cache items by their unique keys
     *
     * @param iterable<string> $keys
     * @param mixed $default
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable;

    /**
     * Persists a set of key => value pairs in the cache
     *
     * @param iterable<string, mixed> $values
     * @param null|int|\DateInterval $ttl
     * @return bool True on success
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool;

    /**
     * Deletes multiple cache items in a single operation
     *
     * @param iterable<string> $keys
     * @return bool True on success
     */
    public function deleteMultiple(iterable $keys): bool;

    /**
     * Determines whether an item is present in the cache
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Increment the value of an item in the cache
     *
     * @param string $key
     * @param int $value
     * @return int|bool New value or false on failure
     */
    public function increment(string $key, int $value = 1): int|bool;

    /**
     * Decrement the value of an item in the cache
     *
     * @param string $key
     * @param int $value
     * @return int|bool New value or false on failure
     */
    public function decrement(string $key, int $value = 1): int|bool;

    /**
     * Store an item in the cache indefinitely
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function forever(string $key, mixed $value): bool;

    /**
     * Get an item from the cache, or execute the given Closure and store the result
     *
     * @param string $key
     * @param null|int|\DateInterval $ttl
     * @param \Closure $callback
     * @return mixed
     */
    public function remember(string $key, null|int|\DateInterval $ttl, \Closure $callback): mixed;

    /**
     * Get an item from the cache, or execute the given Closure and store the result forever
     *
     * @param string $key
     * @param \Closure $callback
     * @return mixed
     */
    public function rememberForever(string $key, \Closure $callback): mixed;

    /**
     * Remove an item from the cache and return its value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function pull(string $key, mixed $default = null): mixed;

    /**
     * Store an item in the cache if the key does not exist
     *
     * @param string $key
     * @param mixed $value
     * @param null|int|\DateInterval $ttl
     * @return bool
     */
    public function add(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool;
}
