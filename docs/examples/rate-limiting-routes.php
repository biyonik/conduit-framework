<?php

/**
 * Rate Limiting Usage Examples
 * 
 * This file shows various ways to use the rate limiting system in your routes.
 * DO NOT include this file in production - it's for documentation purposes only.
 */

use Conduit\Routing\Router;

/** @var Router $router */
$router = app(Router::class);

// ============================================================================
// Example 1: Basic API Rate Limiting
// ============================================================================

// Limit all API routes to 60 requests per minute
$router->middleware('throttle:60,1')->group(['prefix' => 'api'], function (Router $router) {
    
    $router->get('/users', function () {
        return ['users' => []];
    })->name('api.users.index');
    
    $router->get('/posts', function () {
        return ['posts' => []];
    })->name('api.posts.index');
});

// ============================================================================
// Example 2: Authentication Endpoints with Stricter Limits
// ============================================================================

// Limit login/register to 5 attempts per minute (brute force protection)
$router->middleware('throttle:5,1')->group(['prefix' => 'auth'], function (Router $router) {
    
    $router->post('/login', function () {
        return ['message' => 'Login endpoint'];
    })->name('auth.login');
    
    $router->post('/register', function () {
        return ['message' => 'Register endpoint'];
    })->name('auth.register');
    
    $router->post('/forgot-password', function () {
        return ['message' => 'Forgot password endpoint'];
    })->name('auth.forgot');
});

// ============================================================================
// Example 3: Different Limits for Different Endpoints
// ============================================================================

// High-volume read endpoints - 100 requests per minute
$router->middleware('throttle:100,1')->group(function (Router $router) {
    $router->get('/api/public/posts', function () {
        return ['posts' => []];
    });
});

// Low-volume write endpoints - 30 requests per minute
$router->middleware('throttle:30,1')->group(function (Router $router) {
    $router->post('/api/posts', function () {
        return ['message' => 'Post created'];
    });
});

// ============================================================================
// Example 4: Expensive Operations with Longer Decay Periods
// ============================================================================

// Export operations - 3 requests per hour
$router->middleware('throttle:3,60')->group(function (Router $router) {
    $router->get('/api/export/users', function () {
        return ['message' => 'Exporting users...'];
    });
    
    $router->get('/api/export/data', function () {
        return ['message' => 'Exporting data...'];
    });
});

// ============================================================================
// Example 5: Custom Prefix for Different Rate Limit Pools
// ============================================================================

// Premium users get higher limits with custom prefix
$router->middleware('throttle:200,1,premium')->group(function (Router $router) {
    $router->get('/api/premium/data', function () {
        return ['data' => []];
    });
});

// Regular users with default prefix
$router->middleware('throttle:50,1,regular')->group(function (Router $router) {
    $router->get('/api/regular/data', function () {
        return ['data' => []];
    });
});

// ============================================================================
// Example 6: Single Route Rate Limiting
// ============================================================================

$router->get('/api/search', function () {
    return ['results' => []];
})->middleware('throttle:30,1');
