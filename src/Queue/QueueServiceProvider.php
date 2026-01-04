<?php

declare(strict_types=1);

namespace Conduit\Queue;

use Conduit\Core\ServiceProvider;
use Conduit\Database\Connection;
use Conduit\Queue\Contracts\QueueInterface;

/**
 * Queue Service Provider
 * 
 * Registers queue services in the container
 */
class QueueServiceProvider extends ServiceProvider
{
    /**
     * Register services
     * 
     * @return void
     */
    public function register(): void
    {
        // Register DatabaseQueue
        $this->app->singleton(DatabaseQueue::class, function ($app) {
            return new DatabaseQueue($app->make('db.connection'));
        });
        
        // Register QueueInterface binding
        $this->app->singleton(QueueInterface::class, function ($app) {
            return $app->make(DatabaseQueue::class);
        });
        
        // Register QueueManager
        $this->app->singleton(QueueManager::class, function ($app) {
            return new QueueManager($app->make(QueueInterface::class));
        });
        
        // Register Worker
        $this->app->singleton(Worker::class, function ($app) {
            return new Worker($app->make(DatabaseQueue::class));
        });
    }
    
    /**
     * Bootstrap services
     * 
     * @return void
     */
    public function boot(): void
    {
        //
    }
}
