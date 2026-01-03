<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

/**
 * Route Clear Command
 * 
 * Remove the route cache file
 */
class RouteClearCommand extends Command
{
    protected string $name = 'route:clear';
    protected string $description = 'Remove the route cache file';
    
    public function handle(): int
    {
        $routeCache = base_path('bootstrap/cache/routes.php');
        
        if (!file_exists($routeCache)) {
            $this->info('Route cache does not exist.');
            return 0;
        }
        
        unlink($routeCache);
        
        $this->success('Route cache cleared!');
        
        return 0;
    }
}
