<?php

declare(strict_types=1);

namespace Conduit\Storage;

use Conduit\Core\ServiceProvider;
use Conduit\Storage\Storage;

class StorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(Storage::class, function ($container) {
            $config = require $this->app->basePath('config/filesystems.php');
            $disk = $config['default'] ?? 'local';
            $diskConfig = $config['disks'][$disk] ?? [];

            return new Storage(
                $diskConfig['root'] ?? storage_path('app'),
                $diskConfig['visibility'] ?? 'private'
            );
        });

        $this->container->alias(Storage::class, 'storage');
    }
}
