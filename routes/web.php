<?php

/**
 * Web Routes
 * 
 * Routes for web interface (if needed)
 */

use Conduit\Routing\Router;

/** @var Router $router */
$router = app(Router::class);

// Welcome route
$router->get('/', function () {
    return [
        'message' => 'Welcome to Conduit Framework',
        'version' => '1.0.0',
        'docs' => 'https://github.com/biyonik/conduit-framework'
    ];
});

// Health check
$router->get('/health', function () {
    return [
        'status' => 'ok',
        'timestamp' => time()
    ];
});
