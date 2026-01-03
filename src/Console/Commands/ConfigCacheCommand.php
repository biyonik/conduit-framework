<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

/**
 * Config Cache Command
 * 
 * Cache configuration files for production
 */
class ConfigCacheCommand extends Command
{
    protected string $name = 'config:cache';
    protected string $description = 'Create a cache file for faster configuration loading';
    
    public function handle(): int
    {
        $this->info('Caching configuration...');
        
        // Get all config
        $config = $this->application->getFramework()->make('config');
        
        $configArray = $config->all();
        
        // Write to cache file
        $cachePath = base_path('bootstrap/cache/config.php');
        $cacheDir = dirname($cachePath);
        
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $content = '<?php' . PHP_EOL . PHP_EOL;
        $content .= 'return ' . var_export($configArray, true) . ';' . PHP_EOL;
        
        file_put_contents($cachePath, $content);
        
        $this->success('Configuration cached successfully!');
        
        return 0;
    }
}
