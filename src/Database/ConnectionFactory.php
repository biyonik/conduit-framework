<?php

declare(strict_types=1);

namespace Conduit\Database;

use Conduit\Database\Contracts\ConnectionInterface;
use Conduit\Database\Exceptions\ConnectionException;

/**
 * Connection Factory
 *
 * Farklı database driver'ları için Connection instance'ları oluşturur.
 * Factory pattern implementation.
 *
 * @package Conduit\Database
 */
class ConnectionFactory
{
    /**
     * Connection pool (singleton pattern)
     *
     * Her connection name için tek bir instance.
     */
    protected array $connections = [];

    /**
     * Connection oluştur veya mevcut olanı dön
     *
     * @param string $name Connection name (config key)
     * @param array $config Connection configuration
     * @return ConnectionInterface
     * @throws ConnectionException
     */
    public function make(string $name, array $config): ConnectionInterface
    {
        // Connection pool'dan dön (singleton)
        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        // Yeni connection oluştur
        $connection = $this->createConnection($config);

        // Pool'a ekle
        $this->connections[$name] = $connection;

        return $connection;
    }

    /**
     * Connection instance oluştur
     *
     * @param array $config Connection configuration
     * @return ConnectionInterface
     * @throws ConnectionException
     */
    protected function createConnection(array $config): ConnectionInterface
    {
        $driver = $config['driver'] ?? 'mysql';

        // Driver validation
        $this->validateDriver($driver, $config);

        // Connection instance oluştur
        return new Connection($config);
    }

    /**
     * Driver validation
     *
     * Gerekli config parametrelerinin olduğunu kontrol et.
     *
     * @param string $driver Driver name
     * @param array $config Configuration
     * @return void
     * @throws ConnectionException
     */
    protected function validateDriver(string $driver, array $config): void
    {
        // Supported drivers
        $supportedDrivers = ['mysql', 'sqlite', 'pgsql'];

        if (!in_array($driver, $supportedDrivers, true)) {
            throw new ConnectionException(
                "Unsupported database driver: {$driver}. Supported: " . implode(', ', $supportedDrivers)
            );
        }

        // Driver-specific validation
        match ($driver) {
            'mysql' => $this->validateMySqlConfig($config),
            'sqlite' => $this->validateSqliteConfig($config),
            'pgsql' => $this->validatePostgresConfig($config),
        };
    }

    /**
     * MySQL config validation
     *
     * @param array $config
     * @return void
     * @throws ConnectionException
     */
    protected function validateMySqlConfig(array $config): void
    {
        $required = ['host', 'database', 'username', 'password'];

        foreach ($required as $key) {
            if (!isset($config[$key])) {
                throw new ConnectionException("MySQL connection requires '{$key}' config parameter.");
            }
        }
    }

    /**
     * SQLite config validation
     *
     * @param array $config
     * @return void
     * @throws ConnectionException
     */
    protected function validateSqliteConfig(array $config): void
    {
        if (!isset($config['database'])) {
            throw new ConnectionException("SQLite connection requires 'database' config parameter.");
        }

        // File existence check (except :memory:)
        if ($config['database'] !== ':memory:') {
            $directory = dirname($config['database']);

            if (!is_dir($directory) || !is_writable($directory)) {
                throw new ConnectionException(
                    "SQLite database directory does not exist or is not writable: {$directory}"
                );
            }
        }
    }

    /**
     * PostgreSQL config validation
     *
     * @param array $config
     * @return void
     * @throws ConnectionException
     */
    protected function validatePostgresConfig(array $config): void
    {
        $required = ['host', 'database', 'username', 'password'];

        foreach ($required as $key) {
            if (!isset($config[$key])) {
                throw new ConnectionException("PostgreSQL connection requires '{$key}' config parameter.");
            }
        }
    }

    /**
     * Tüm connection'ları kapat
     *
     * @return void
     */
    public function disconnect(): void
    {
        foreach ($this->connections as $connection) {
            $connection->disconnect();
        }

        $this->connections = [];
    }

    /**
     * Belirli bir connection'ı kapat
     *
     * @param string $name Connection name
     * @return void
     */
    public function disconnectConnection(string $name): void
    {
        if (isset($this->connections[$name])) {
            $this->connections[$name]->disconnect();
            unset($this->connections[$name]);
        }
    }

    /**
     * Connection pool'u temizle
     *
     * @return void
     */
    public function purge(): void
    {
        $this->disconnect();
    }

    /**
     * Connection mevcut mu kontrol et
     *
     * @param string $name Connection name
     * @return bool
     */
    public function hasConnection(string $name): bool
    {
        return isset($this->connections[$name]);
    }

    /**
     * Mevcut connection'ı al
     *
     * @param string $name Connection name
     * @return ConnectionInterface|null
     */
    public function getConnection(string $name): ?ConnectionInterface
    {
        return $this->connections[$name] ?? null;
    }

    /**
     * Tüm connection'ları al
     *
     * @return array
     */
    public function getConnections(): array
    {
        return $this->connections;
    }
}
