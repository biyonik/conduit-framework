<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

/**
 * Config Clear Command
 * 
 * Remove the configuration cache file
 */
class ConfigClearCommand extends Command
{
    protected string $name = 'config:clear';
    protected string $description = 'Remove the configuration cache file';
    
    public function handle(): int
    {
        $configCache = base_path('bootstrap/cache/config.php');
        
        if (!file_exists($configCache)) {
            $this->info('Configuration cache does not exist.');
            return 0;
        }
        
        unlink($configCache);
        
        $this->success('Configuration cache cleared!');
        
        return 0;
    }
}
