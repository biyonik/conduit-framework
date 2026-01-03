<?php

declare(strict_types=1);

namespace Conduit\Events;

use Conduit\Core\ServiceProvider;
use Conduit\Events\Contracts\EventDispatcherInterface;

/**
 * EventServiceProvider
 * 
 * Registers the event dispatcher in the container.
 * 
 * @package Conduit\Events
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider
     * 
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(EventDispatcherInterface::class, function ($app) {
            return new EventDispatcher($app->getContainer());
        });

        // Also bind as 'events' for convenience
        $this->app->alias(EventDispatcherInterface::class, 'events');
    }

    /**
     * Bootstrap the service provider
     * 
     * @return void
     */
    public function boot(): void
    {
        // Event listeners can be registered here or in application code
    }
}
