<?php

declare(strict_types=1);

namespace Conduit\RateLimiter;

use Conduit\Core\ServiceProvider;
use Conduit\RateLimiter\Contracts\RateLimiterInterface;
use Conduit\RateLimiter\Contracts\LimiterStorageInterface;
use Conduit\RateLimiter\Storage\DatabaseStorage;
use Conduit\RateLimiter\Storage\FileStorage;
use Conduit\RateLimiter\Middleware\ThrottleMiddleware;

class RateLimitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LimiterStorageInterface::class, function ($app) {
            $driver = $app->config('ratelimiter.driver', 'file');
            
            return match ($driver) {
                'database' => new DatabaseStorage($app->make('db')),
                'file' => new FileStorage($app->storagePath('framework/ratelimiter')),
                default => new FileStorage($app->storagePath('framework/ratelimiter')),
            };
        });
        
        $this->app->singleton(RateLimiter::class, function ($app) {
            return new RateLimiter($app->make(LimiterStorageInterface::class));
        });
        
        $this->app->alias(RateLimiter::class, RateLimiterInterface::class);
        
        $this->app->singleton(ThrottleMiddleware::class, function ($app) {
            return new ThrottleMiddleware($app->make(RateLimiter::class));
        });
    }
}
