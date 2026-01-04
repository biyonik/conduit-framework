<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

use Conduit\Routing\Router;
use Conduit\Routing\RouteCompiler;

/**
 * Route Cache Command
 * 
 * Create a route cache file for faster route registration
 */
class RouteCacheCommand extends Command
{
    protected string $name = 'route:cache';
    protected string $description = 'Create a route cache file for faster route registration';
    
    public function handle(): int
    {
        $this->info('Compiling routes...');
        
        // Get router instance
        $router = app(Router::class);
        
        // Load route files if they exist
        $this->loadRouteFiles();
        
        // Compile routes
        $compiler = new RouteCompiler();
        $compiled = $compiler->compile($router);
        
        // Save to cache
        $cachePath = base_path('bootstrap/cache/routes.php');
        $compiler->save($compiled, $cachePath);
        
        $routeCount = count($compiled['routes']);
        
        $this->success("Routes cached successfully! ({$routeCount} routes)");
        
        // Display stats
        if (file_exists($cachePath)) {
            $size = filesize($cachePath);
            $this->info("Cache file size: " . $this->formatBytes($size));
            $this->info("Cache location: {$cachePath}");
        }
        
        return 0;
    }
    
    /**
     * Load route definition files
     * 
     * @return void
     */
    protected function loadRouteFiles(): void
    {
        $webRoutes = base_path('routes/web.php');
        $apiRoutes = base_path('routes/api.php');
        
        if (file_exists($webRoutes)) {
            require $webRoutes;
        }
        
        if (file_exists($apiRoutes)) {
            require $apiRoutes;
        }
    }
    
    /**
     * Format bytes to human readable format
     * 
     * @param int $bytes
     * @return string
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB'];
        $power = floor(($bytes > 0 ? log($bytes) : 0) / log(1024));
        $power = min($power, count($units) - 1);
        
        return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
}
