<?php

declare(strict_types=1);

namespace Conduit\Mail;

use Conduit\Core\ServiceProvider;
use Conduit\Mail\Mailer;
use Conduit\Queue\QueueManager;

class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(Mailer::class, function ($container) {
            $config = require $this->app->basePath('config/mail.php');
            $queue = $container->has(QueueManager::class) ? $container->make(QueueManager::class) : null;

            return new Mailer($config, $queue);
        });

        $this->container->alias(Mailer::class, 'mail');
    }
}
