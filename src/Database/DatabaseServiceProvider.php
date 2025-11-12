<?php

declare(strict_types=1);

namespace Conduit\Database;

use Conduit\Core\ServiceProvider;
use Conduit\Database\Schema\Schema;
use Conduit\Database\Schema\MigrationRepository;
use Conduit\Database\Schema\Migrator;

/**
 * Database Service Provider
 *
 * Database connection ve related servisleri Container'a register eder
 *
 * @package Conduit\Database
 */
class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register database services
     *
     * @return void
     */
    public function register(): void
    {
        // Connection factory'yi singleton olarak kaydet
        $this->app->singleton('db.factory', function ($app) {
            return new ConnectionFactory();
        });

        // Default database connection'ı singleton olarak kaydet
        $this->app->singleton('db.connection', function ($app) {
            $config = $app->make('config');
            $factory = $app->make('db.factory');

            $default = $config->get('database.default', 'mysql');
            $connectionConfig = $config->get("database.connections.{$default}");

            if (!$connectionConfig) {
                throw new \RuntimeException("Database connection [{$default}] not configured.");
            }

            return $factory->make($connectionConfig);
        });

        // Alias: 'db' → 'db.connection'
        $this->app->alias('db', 'db.connection');

        // MigrationRepository'yi singleton olarak kaydet
        $this->app->singleton(MigrationRepository::class, function ($app) {
            $connection = $app->make('db.connection');
            return new MigrationRepository($connection);
        });

        // Migrator'ı singleton olarak kaydet
        $this->app->singleton(Migrator::class, function ($app) {
            $connection = $app->make('db.connection');
            $repository = $app->make(MigrationRepository::class);
            
            // Migration path
            $path = $app->basePath('database/migrations');

            return new Migrator($connection, $repository, $path);
        });
    }

    /**
     * Boot database services
     *
     * Service provider'lar register edildikten sonra çalışır
     *
     * @return void
     */
    public function boot(): void
    {
        // Schema facade için connection set et
        $connection = $this->app->make('db.connection');
        Schema::setConnection($connection);

        // Model için default connection set et (Phase 2'de eklediysek)
        if (class_exists(\Conduit\Database\Model::class)) {
            \Conduit\Database\Model::setConnectionResolver(function () use ($connection) {
                return $connection;
            });
        }
    }
}