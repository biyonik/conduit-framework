<?php

declare(strict_types=1);

namespace Conduit\Cache;

use Conduit\Core\ServiceProvider;
use Conduit\Cache\CacheManager;
use Conduit\Database\Contracts\ConnectionInterface;

/**
 * Cache Service Provider
 *
 * Registers cache services.
 *
 * @package Conduit\Cache
 */
class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(CacheManager::class, function ($container) {
            $config = require $this->app->basePath('config/cache.php');
            $connection = $container->has(ConnectionInterface::class)
                ? $container->make(ConnectionInterface::class)
                : null;

            return new CacheManager($config, $connection);
        });

        $this->container->alias(CacheManager::class, 'cache');
    }

    public function boot(): void
    {
        // Register cache:clear command if in console
        if ($this->app->runningInConsole()) {
            // Command is already registered in Console
        }
    }
}
