<?php

declare(strict_types=1);

namespace Conduit\Cache;

use Conduit\Cache\Contracts\CacheInterface;
use Conduit\Cache\Drivers\FileDriver;
use Conduit\Cache\Drivers\DatabaseDriver;
use Conduit\Cache\Drivers\ArrayDriver;
use Conduit\Database\Contracts\ConnectionInterface;

/**
 * Cache Manager
 *
 * Factory for cache drivers.
 * Shared hosting optimize

 edilmiş cache yönetimi.
 *
 * @package Conduit\Cache
 */
class CacheManager
{
    /**
     * Configuration array
     *
     * @var array
     */
    protected array $config;

    /**
     * Active driver instances
     *
     * @var array<string, CacheInterface>
     */
    protected array $drivers = [];

    /**
     * Database connection (for database driver)
     *
     * @var ConnectionInterface|null
     */
    protected ?ConnectionInterface $connection;

    /**
     * Constructor
     *
     * @param array $config
     * @param ConnectionInterface|null $connection
     */
    public function __construct(array $config, ?ConnectionInterface $connection = null)
    {
        $this->config = $config;
        $this->connection = $connection;
    }

    /**
     * Get cache driver instance
     *
     * @param string|null $driver Driver name (null = default)
     * @return CacheInterface
     */
    public function driver(?string $driver = null): CacheInterface
    {
        $driver = $driver ?? $this->config['default'];

        if (isset($this->drivers[$driver])) {
            return $this->drivers[$driver];
        }

        return $this->drivers[$driver] = $this->createDriver($driver);
    }

    /**
     * Create driver instance
     *
     * @param string $driver
     * @return CacheInterface
     * @throws \InvalidArgumentException
     */
    protected function createDriver(string $driver): CacheInterface
    {
        if (!isset($this->config['stores'][$driver])) {
            throw new \InvalidArgumentException("Cache driver [{$driver}] is not defined.");
        }

        $config = $this->config['stores'][$driver];
        $type = $config['driver'] ?? $driver;

        return match ($type) {
            'file' => $this->createFileDriver($config),
            'database' => $this->createDatabaseDriver($config),
            'array' => $this->createArrayDriver($config),
            default => throw new \InvalidArgumentException("Unsupported cache driver [{$type}]"),
        };
    }

    /**
     * Create file driver
     *
     * @param array $config
     * @return FileDriver
     */
    protected function createFileDriver(array $config): FileDriver
    {
        return new FileDriver(
            $config['path'],
            $this->config['prefix'] ?? 'conduit',
            $config['permissions']['file'] ?? 0644,
            $config['permissions']['dir'] ?? 0755
        );
    }

    /**
     * Create database driver
     *
     * @param array $config
     * @return DatabaseDriver
     * @throws \RuntimeException
     */
    protected function createDatabaseDriver(array $config): DatabaseDriver
    {
        if ($this->connection === null) {
            throw new \RuntimeException('Database connection is required for database cache driver');
        }

        return new DatabaseDriver(
            $this->connection,
            $config['table'] ?? 'cache',
            $this->config['prefix'] ?? 'conduit'
        );
    }

    /**
     * Create array driver
     *
     * @param array $config
     * @return ArrayDriver
     */
    protected function createArrayDriver(array $config): ArrayDriver
    {
        return new ArrayDriver();
    }

    /**
     * Get the default cache driver name
     *
     * @return string
     */
    public function getDefaultDriver(): string
    {
        return $this->config['default'];
    }

    /**
     * Set the default cache driver
     *
     * @param string $driver
     * @return void
     */
    public function setDefaultDriver(string $driver): void
    {
        $this->config['default'] = $driver;
    }

    /**
     * Proxy method calls to default driver
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->driver()->$method(...$parameters);
    }
}
