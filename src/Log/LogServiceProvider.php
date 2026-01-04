<?php

declare(strict_types=1);

namespace Conduit\Log;

use Conduit\Core\ServiceProvider;
use Conduit\Log\Logger;

class LogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(Logger::class, function ($container) {
            $config = require $this->app->basePath('config/logging.php');
            $channel = $config['default'] ?? 'app';
            $channelConfig = $config['channels'][$channel] ?? [];

            return new Logger(
                $channel,
                $channelConfig['path'] ?? storage_path('logs'),
                $channelConfig['level'] ?? 'debug'
            );
        });

        $this->container->alias(Logger::class, 'log');
    }
}
