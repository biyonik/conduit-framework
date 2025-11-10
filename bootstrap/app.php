<?php

/**
 * CONDUIT PHP FRAMEWORK
 * Application Bootstrap
 *
 * Bu dosya framework'ü başlatır ve konfigüre eder.
 * Tüm servis provider'lar burada kayıt edilir.
 */

use Conduit\Core\Application;

// ============================================
// Create Application Instance
// ============================================
$app = new Application(
    basePath: dirname(__DIR__)
);

// ============================================
// Bind Important Interfaces
// ============================================
$app->singleton(
    Conduit\Http\Kernel::class
);

$app->singleton(
    Conduit\Routing\Router::class
);

$app->singleton(
    Conduit\Database\Connection::class
);

$app->singleton(
    Conduit\Cache\CacheManager::class
);

// ============================================
// Register Service Providers
// ============================================
$app->register(new Conduit\Routing\RoutingServiceProvider($app));
$app->register(new Conduit\Database\DatabaseServiceProvider($app));
$app->register(new Conduit\Cache\CacheServiceProvider($app));
$app->register(new Conduit\Security\Auth\AuthServiceProvider($app));
$app->register(new Conduit\Validation\ValidationServiceProvider($app));

// ============================================
// Register Error Handler
// ============================================
$app->singleton(
    Conduit\Exceptions\Handler::class
);

// Set error and exception handlers
set_error_handler([app(Conduit\Exceptions\Handler::class), 'handleError']);
set_exception_handler([app(Conduit\Exceptions\Handler::class), 'handleException']);
register_shutdown_function([app(Conduit\Exceptions\Handler::class), 'handleShutdown']);

// ============================================
// Load Configuration
// ============================================
$app->loadConfiguration();

// ============================================
// Load Environment Variables
// ============================================
if (file_exists(dirname(__DIR__) . '/.env')) {
    $dotenv = Conduit\Support\Dotenv::load(dirname(__DIR__));
}

// ============================================
// Return Application
// ============================================
return $app;
