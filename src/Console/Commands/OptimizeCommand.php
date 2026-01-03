<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

/**
 * Optimize Command
 * 
 * Cache config, routes and container for production
 */
class OptimizeCommand extends Command
{
    protected string $name = 'optimize';
    protected string $description = 'Cache config, routes and container for production';
    
    public function handle(): int
    {
        $this->info('Optimizing framework...');
        $this->line();
        
        // Config cache
        $this->call('config:cache');
        
        // Route cache
        $this->call('route:cache');
        
        // Container compile
        $this->call('container:compile');
        
        $this->line();
        $this->success('Application optimized successfully!');
        $this->line();
        $this->warn('Remember to run "composer dump-autoload --optimize --no-dev" in production.');
        
        return 0;
    }
}
