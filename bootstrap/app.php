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

// ============================================
// Register Service Providers
// ============================================
$app->register(\Conduit\Events\EventServiceProvider::class);
$app->register(\Conduit\Routing\RouteServiceProvider::class);
$app->register(\Conduit\Database\DatabaseServiceProvider::class);
$app->register(\Conduit\Validation\ValidationServiceProvider::class);
// Cache and Auth providers will be added when those modules are implemented

// ============================================
// Return Application
// ============================================
return $app;
