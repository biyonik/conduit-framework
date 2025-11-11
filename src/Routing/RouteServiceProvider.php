<?php

declare(strict_types=1);

namespace Conduit\Routing;

use Conduit\Core\ServiceProvider;
use Conduit\Routing\Contracts\RouterInterface;

/**
 * Route Service Provider
 * 
 * Router'ı container'a kayıt eder ve route dosyalarını yükler.
 * 
 * @package Conduit\Routing
 */
class RouteServiceProvider extends ServiceProvider
{
    /**
     * Register router services
     * 
     * @return void
     */
    public function register(): void
    {
        // Router'ı singleton olarak kayıt et
        $this->app->singleton(RouterInterface::class, function ($app) {
            return new Router();
        });
        
        // Alias binding
        $this->app->alias(RouterInterface::class, Router::class);
        $this->app->alias(RouterInterface::class, 'router');
    }
    
    /**
     * Boot router services
     * 
     * @return void
     */
    public function boot(): void
    {
        // Route dosyalarını yükle
        $this->loadRoutes();
        
        // Global middleware'leri kayıt et
        $this->registerGlobalMiddleware();
    }
    
    /**
     * Route dosyalarını yükle
     * 
     * @return void
     */
    protected function loadRoutes(): void
    {
        $router = $this->app->make(RouterInterface::class);
        
        // API routes yükle
        $apiRoutesFile = $this->app->basePath('routes/api.php');
        if (file_exists($apiRoutesFile)) {
            // API group with prefix
            $router->group(['prefix' => '/api'], function ($router) use ($apiRoutesFile) {
                require $apiRoutesFile;
            });
        }
        
        // Web routes yükle (optional)
        $webRoutesFile = $this->app->basePath('routes/web.php');
        if (file_exists($webRoutesFile)) {
            require $webRoutesFile;
        }
    }
    
    /**
     * Global middleware'leri kayıt et
     * 
     * @return void
     */
    protected function registerGlobalMiddleware(): void
    {
        $router = $this->app->make(RouterInterface::class);
        
        // Global middleware stack
        $globalMiddleware = [
            'cors',           // CORS headers
            'json',           // JSON only for API
            'trim',           // Trim input strings
            'convert_empty',  // Empty strings to null
        ];
        
        $router->middleware($globalMiddleware);
    }
    
    /**
     * Route cache path'ini al
     * 
     * @return string
     */
    protected function getRouteCachePath(): string
    {
        return $this->app->basePath('storage/cache/routes.php');
    }
    
    /**
     * Route'lar cache'lenebilir mi?
     * 
     * @return bool
     */
    protected function routesAreCached(): bool
    {
        return file_exists($this->getRouteCachePath());
    }
    
    /**
     * Route cache'ini yükle
     * 
     * @return bool
     */
    protected function loadCachedRoutes(): bool
    {
        if (!$this->routesAreCached()) {
            return false;
        }
        
        $router = $this->app->make(RouterInterface::class);
        return $router->loadCache($this->getRouteCachePath());
    }
    
    /**
     * Route'ları cache'le
     * 
     * @return bool
     */
    public function cacheRoutes(): bool
    {
        $router = $this->app->make(RouterInterface::class);
        return $router->cacheRoutes($this->getRouteCachePath());
    }
}