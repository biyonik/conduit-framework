<?php

declare(strict_types=1);

namespace Conduit\Console\Commands;

use Conduit\Routing\Router;

/**
 * Route List Command
 * 
 * List all registered routes
 */
class RouteListCommand extends Command
{
    protected string $name = 'route:list';
    protected string $description = 'List all registered routes';
    protected array $aliases = ['routes'];
    
    public function handle(): int
    {
        $router = app(Router::class);
        
        // Load route files if they exist
        $this->loadRouteFiles();
        
        $routes = $router->getRoutes();
        
        if (empty($routes)) {
            $this->warn('No routes registered.');
            return 0;
        }
        
        // Prepare table data
        $headers = ['Method', 'URI', 'Name', 'Action', 'Middleware'];
        $rows = [];
        
        foreach ($routes as $route) {
            $methods = implode('|', $route->getMethods());
            $uri = $route->getUri();
            $name = $route->getName() ?? '-';
            $action = $this->formatAction($route->getAction());
            $middleware = implode(', ', $route->getMiddleware()) ?: '-';
            
            $rows[] = [$methods, $uri, $name, $action, $middleware];
        }
        
        $this->table($headers, $rows);
        
        $this->info('Total routes: ' . count($routes));
        
        // Check if routes are cached
        if ($router->isCached()) {
            $this->success('Routes are cached (production mode)');
        } else {
            $this->info('Routes are not cached (development mode)');
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
     * Format route action for display
     * 
     * @param mixed $action
     * @return string
     */
    protected function formatAction(mixed $action): string
    {
        if (is_string($action)) {
            return $action;
        }
        
        if (is_array($action) && count($action) === 2) {
            return $action[0] . '@' . $action[1];
        }
        
        if (is_callable($action)) {
            return 'Closure';
        }
        
        return 'Unknown';
    }
}
