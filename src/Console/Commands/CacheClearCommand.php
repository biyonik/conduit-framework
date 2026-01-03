<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

/**
 * Cache Clear Command
 * 
 * Clears all application caches
 */
class CacheClearCommand extends Command
{
    protected string $name = 'cache:clear';
    protected string $description = 'Clear all application caches';
    
    public function handle(): int
    {
        $cleared = [];
        
        // Config cache
        $configCache = base_path('bootstrap/cache/config.php');
        if (file_exists($configCache)) {
            unlink($configCache);
            $cleared[] = 'config';
        }
        
        // Route cache
        $routeCache = base_path('bootstrap/cache/routes.php');
        if (file_exists($routeCache)) {
            unlink($routeCache);
            $cleared[] = 'routes';
        }
        
        // Container cache
        $containerCache = base_path('bootstrap/cache/container.php');
        if (file_exists($containerCache)) {
            unlink($containerCache);
            $cleared[] = 'container';
        }
        
        // Application cache directory
        $cacheDir = storage_path('cache');
        if (is_dir($cacheDir)) {
            $cacheFiles = glob($cacheDir . '/*');
            if ($cacheFiles !== false) {
                foreach ($cacheFiles as $file) {
                    if (is_file($file) && basename($file) !== '.gitkeep') {
                        unlink($file);
                    }
                }
                if (!empty($cacheFiles)) {
                    $cleared[] = 'application';
                }
            }
        }
        
        // OPcache
        if (function_exists('opcache_reset')) {
            opcache_reset();
            $cleared[] = 'opcache';
        }
        
        if (empty($cleared)) {
            $this->info('No caches to clear.');
        } else {
            $this->success('Caches cleared: ' . implode(', ', $cleared));
        }
        
        return 0;
    }
}
