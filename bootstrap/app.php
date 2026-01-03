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
// 1. ÖNCE .env yükle
// ============================================
if (file_exists(dirname(__DIR__) . '/.env')) {
    \Conduit\Support\Dotenv::load(dirname(__DIR__));
}

// ============================================
// 2. Create Application Instance
// ============================================
$app = new Application(
    basePath: dirname(__DIR__)
);

// ============================================
// 3. SONRA config yükle (artık env() çalışır)
// ============================================
$app->loadConfiguration();

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
$app->register(new Conduit\Events\EventServiceProvider($app));
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
// Return Application
// ============================================
return $app;
