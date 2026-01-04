<?php

declare(strict_types=1);

namespace Conduit\Authorization\Middleware;

use Closure;
use Conduit\Http\Request;
use Conduit\Http\Response;
use Conduit\Http\JsonResponse;
use Conduit\Authorization\PolicyEngine;
use Conduit\Middleware\MiddlewareInterface;

/**
 * CheckPermission Middleware
 *
 * Protects routes with permission checks.
 * Verifies that authenticated user has required permission(s).
 *
 * Usage in routes:
 * ```php
 * $router->get('/posts', 'PostController@index')
 *     ->middleware('permission:posts.view');
 *
 * $router->delete('/posts/{id}', 'PostController@destroy')
 *     ->middleware('permission:posts.delete');
 *
 * // Multiple permissions (OR logic)
 * $router->get('/admin', 'AdminController@index')
 *     ->middleware('permission:admin.view|super.admin');
 *
 * // Multiple permissions (AND logic)
 * $router->post('/sensitive', 'SensitiveController@store')
 *     ->middleware('permission:sensitive.create&sensitive.approve');
 * ```
 *
 * @package Conduit\Authorization\Middleware
 */
class CheckPermission implements MiddlewareInterface
{
    /**
     * Middleware parameters
     *
     * @var array
     */
    protected array $parameters = [];

    /**
     * Handle the request
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle($request, Closure $next): Response
    {
        // Get authenticated user
        $user = $request->getAttribute('user');

        if (!$user) {
            return $this->unauthorizedResponse('Authentication required');
        }

        // Parse permission parameter
        $permissionString = $this->parameters[0] ?? null;

        if (!$permissionString) {
            return $this->unauthorizedResponse('No permission specified');
        }

        // Check permissions
        if (!$this->checkPermissions($user, $permissionString)) {
            return $this->forbiddenResponse($permissionString);
        }

        return $next($request);
    }

    /**
     * {@inheritdoc}
     */
    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * Check if user has required permissions
     *
     * @param mixed $user User model with HasRoles trait
     * @param string $permissionString Permission string (e.g., 'posts.view' or 'admin|super')
     * @return bool
     */
    protected function checkPermissions($user, string $permissionString): bool
    {
        // Handle AND logic (all permissions required)
        if (str_contains($permissionString, '&')) {
            $permissions = explode('&', $permissionString);
            $permissions = array_map('trim', $permissions);

            foreach ($permissions as $permission) {
                if (!$user->hasPermissionTo($permission)) {
                    return false;
                }
            }
            return true;
        }

        // Handle OR logic (any permission required)
        if (str_contains($permissionString, '|')) {
            $permissions = explode('|', $permissionString);
            $permissions = array_map('trim', $permissions);

            foreach ($permissions as $permission) {
                if ($user->hasPermissionTo($permission)) {
                    return true;
                }
            }
            return false;
        }

        // Single permission
        return $user->hasPermissionTo($permissionString);
    }

    /**
     * Build unauthorized response (401)
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function unauthorizedResponse(string $message): JsonResponse
    {
        return new JsonResponse([
            'error' => 'Unauthorized',
            'message' => $message,
        ], 401);
    }

    /**
     * Build forbidden response (403)
     *
     * @param string $permissionString
     * @return JsonResponse
     */
    protected function forbiddenResponse(string $permissionString): JsonResponse
    {
        return new JsonResponse([
            'error' => 'Forbidden',
            'message' => 'You do not have the required permission(s) to access this resource',
            'required_permission' => $permissionString,
        ], 403);
    }
}
