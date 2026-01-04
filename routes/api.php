<?php

/**
 * API Routes
 * 
 * Routes for API endpoints
 */

use Conduit\Routing\Router;

/** @var Router $router */
$router = app(Router::class);

// API route group
$router->group(['prefix' => 'api'], function (Router $router) {
    
    // Users routes
    $router->get('/users', function () {
        return [
            'users' => [
                ['id' => 1, 'name' => 'John Doe'],
                ['id' => 2, 'name' => 'Jane Smith']
            ]
        ];
    })->name('api.users.index');
    
    $router->get('/users/{id}', function (string $id) {
        return [
            'user' => [
                'id' => $id,
                'name' => 'John Doe',
                'email' => 'john@example.com'
            ]
        ];
    })->name('api.users.show');
    
    $router->post('/users', function () {
        return [
            'message' => 'User created',
            'user' => ['id' => 3, 'name' => 'New User']
        ];
    })->name('api.users.store');
    
    $router->put('/users/{id}', function (string $id) {
        return [
            'message' => 'User updated',
            'user' => ['id' => $id]
        ];
    })->name('api.users.update');
    
    $router->delete('/users/{id}', function (string $id) {
        return [
            'message' => 'User deleted',
            'user_id' => $id
        ];
    })->name('api.users.destroy');
    
    // Posts routes with optional parameter
    $router->get('/posts/{id}/comments/{comment?}', function (string $id, ?string $comment = null) {
        if ($comment) {
            return [
                'post_id' => $id,
                'comment' => [
                    'id' => $comment,
                    'text' => 'Sample comment'
                ]
            ];
        }
        
        return [
            'post_id' => $id,
            'comments' => [
                ['id' => 1, 'text' => 'Comment 1'],
                ['id' => 2, 'text' => 'Comment 2']
            ]
        ];
    })->name('api.posts.comments');
    
    // Queue routes (for external cron services)
    $router->post('/queue/process', [\Conduit\Queue\QueueController::class, 'process'])
        ->name('api.queue.process');
    
    $router->get('/queue/stats', [\Conduit\Queue\QueueController::class, 'stats'])
        ->name('api.queue.stats');
});
