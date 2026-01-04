<?php

declare(strict_types=1);

namespace Conduit\Authorization;

use Conduit\Core\ServiceProvider;
use Conduit\Authorization\PolicyEngine;
use Conduit\Authorization\FieldRestrictor;
use Conduit\Authorization\Middleware\CheckPermission;

/**
 * Authorization Service Provider
 *
 * Registers RBAC (Role-Based Access Control) services and middleware.
 *
 * Services registered:
 * - PolicyEngine: Core authorization engine
 * - FieldRestrictor: Field-level security handler
 * - CheckPermission middleware
 *
 * @package Conduit\Authorization
 */
class AuthorizationServiceProvider extends ServiceProvider
{
    /**
     * Register RBAC services
     *
     * @return void
     */
    public function register(): void
    {
        // Register PolicyEngine
        $this->container->bind(PolicyEngine::class, function ($container) {
            $request = $container->make(\Conduit\Http\Request::class);
            $user = $request->getAttribute('user');

            if (!$user) {
                throw new \RuntimeException('User must be authenticated to use PolicyEngine');
            }

            return new PolicyEngine($user);
        });

        // Register FieldRestrictor
        $this->container->bind(FieldRestrictor::class, function ($container) {
            $request = $container->make(\Conduit\Http\Request::class);
            $user = $request->getAttribute('user');

            if (!$user) {
                throw new \RuntimeException('User must be authenticated to use FieldRestrictor');
            }

            return new FieldRestrictor($user);
        });

        // Register CheckPermission middleware
        $this->container->bind('middleware.permission', function ($container) {
            return new CheckPermission();
        });

        // Register aliases
        $this->container->alias(PolicyEngine::class, 'policy.engine');
        $this->container->alias(FieldRestrictor::class, 'field.restrictor');
    }

    /**
     * Boot RBAC services
     *
     * @return void
     */
    public function boot(): void
    {
        // Load helper functions
        $this->loadHelpers();

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    /**
     * Load helper functions
     *
     * @return void
     */
    protected function loadHelpers(): void
    {
        $helpersPath = __DIR__ . '/helpers.php';

        if (file_exists($helpersPath)) {
            require_once $helpersPath;
        }
    }

    /**
     * Get services provided by this provider
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            PolicyEngine::class,
            FieldRestrictor::class,
            'policy.engine',
            'field.restrictor',
            'middleware.permission',
        ];
    }
}
