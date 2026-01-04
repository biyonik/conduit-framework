<?php

declare(strict_types=1);

namespace Conduit\Queue;

use Conduit\Core\ServiceProvider;
use Conduit\Database\Connection;

class QueueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DatabaseQueue::class, function ($app) {
            return new DatabaseQueue($app->make(Connection::class));
        });
        
        $this->app->singleton(Worker::class, function ($app) {
            return new Worker($app->make(DatabaseQueue::class));
        });
        
        $this->app->singleton(QueueManager::class, function ($app) {
            return new QueueManager($app->make(DatabaseQueue::class));
        });
    }
}
