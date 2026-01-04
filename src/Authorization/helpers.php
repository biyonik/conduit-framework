<?php

declare(strict_types=1);

use Conduit\Authorization\PolicyEngine;
use Conduit\Authorization\FieldRestrictor;
use Conduit\Authorization\Models\Role;
use Conduit\Authorization\Models\Permission;

if (!function_exists('authorize')) {
    /**
     * Authorize an action on a resource
     *
     * @param string $permission Permission name (e.g., 'posts.delete')
     * @param \Conduit\Database\Model|null $resource Optional resource to check
     * @param \Conduit\Database\Model|null $user User to check (defaults to current authenticated user)
     * @return bool
     */
    function authorize(string $permission, $resource = null, $user = null): bool
    {
        if ($user === null) {
            // Get current authenticated user from request
            $request = app()->make(\Conduit\Http\Request::class);
            $user = $request->getAttribute('user');
        }

        if (!$user) {
            return false;
        }

        $engine = new PolicyEngine($user);
        return $engine->authorize($permission, $resource);
    }
}

if (!function_exists('can')) {
    /**
     * Check if user can perform an action
     *
     * Alias for authorize() for Laravel-like syntax.
     *
     * @param string $permission Permission name
     * @param \Conduit\Database\Model|null $resource
     * @param \Conduit\Database\Model|null $user
     * @return bool
     */
    function can(string $permission, $resource = null, $user = null): bool
    {
        return authorize($permission, $resource, $user);
    }
}

if (!function_exists('cannot')) {
    /**
     * Check if user cannot perform an action
     *
     * @param string $permission Permission name
     * @param \Conduit\Database\Model|null $resource
     * @param \Conduit\Database\Model|null $user
     * @return bool
     */
    function cannot(string $permission, $resource = null, $user = null): bool
    {
        return !can($permission, $resource, $user);
    }
}

if (!function_exists('hasRole')) {
    /**
     * Check if user has a role
     *
     * @param string|array $role Role slug or array of role slugs
     * @param \Conduit\Database\Model|null $user
     * @return bool
     */
    function hasRole($role, $user = null): bool
    {
        if ($user === null) {
            $request = app()->make(\Conduit\Http\Request::class);
            $user = $request->getAttribute('user');
        }

        if (!$user) {
            return false;
        }

        return $user->hasRole($role);
    }
}

if (!function_exists('hasPermission')) {
    /**
     * Check if user has a permission
     *
     * @param string|array $permission Permission name or array of names
     * @param \Conduit\Database\Model|null $user
     * @return bool
     */
    function hasPermission($permission, $user = null): bool
    {
        if ($user === null) {
            $request = app()->make(\Conduit\Http\Request::class);
            $user = $request->getAttribute('user');
        }

        if (!$user) {
            return false;
        }

        return $user->hasPermissionTo($permission);
    }
}

if (!function_exists('applyFieldRestrictions')) {
    /**
     * Apply field-level restrictions to a model
     *
     * @param string $permission Permission name
     * @param \Conduit\Database\Model $model Model to restrict
     * @param \Conduit\Database\Model|null $user
     * @return array Sanitized attributes
     */
    function applyFieldRestrictions(string $permission, $model, $user = null): array
    {
        if ($user === null) {
            $request = app()->make(\Conduit\Http\Request::class);
            $user = $request->getAttribute('user');
        }

        if (!$user) {
            return [];
        }

        $restrictor = new FieldRestrictor($user);
        return $restrictor->applyRestrictions($permission, $model);
    }
}

if (!function_exists('roleExists')) {
    /**
     * Check if a role exists
     *
     * @param string $slug Role slug
     * @return bool
     */
    function roleExists(string $slug): bool
    {
        return Role::findBySlug($slug) !== null;
    }
}

if (!function_exists('permissionExists')) {
    /**
     * Check if a permission exists
     *
     * @param string $name Permission name
     * @return bool
     */
    function permissionExists(string $name): bool
    {
        return Permission::findByName($name) !== null;
    }
}

if (!function_exists('createRole')) {
    /**
     * Create a new role
     *
     * @param string $name Role name
     * @param string $slug Role slug
     * @param string|null $description
     * @return Role
     */
    function createRole(string $name, string $slug, ?string $description = null): Role
    {
        return Role::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
        ]);
    }
}

if (!function_exists('createPermission')) {
    /**
     * Create a new permission
     *
     * @param string $resource Resource name (e.g., 'posts')
     * @param string $action Action name (e.g., 'view', 'create', 'delete')
     * @param string|null $description
     * @return Permission
     */
    function createPermission(string $resource, string $action, ?string $description = null): Permission
    {
        return Permission::createOrGet($resource, $action, $description);
    }
}

if (!function_exists('assignRoleToUser')) {
    /**
     * Assign a role to a user
     *
     * @param \Conduit\Database\Model $user
     * @param string|Role $role Role slug or Role instance
     * @return \Conduit\Database\Model
     */
    function assignRoleToUser($user, $role)
    {
        return $user->assignRole($role);
    }
}

if (!function_exists('givePermissionTo')) {
    /**
     * Give a permission to a role
     *
     * @param Role|string $role Role instance or slug
     * @param Permission|string $permission Permission instance or name
     * @return Role
     */
    function givePermissionTo($role, $permission): Role
    {
        if (is_string($role)) {
            $role = Role::findBySlug($role);
            if (!$role) {
                throw new \InvalidArgumentException("Role '{$role}' not found");
            }
        }

        return $role->givePermissionTo($permission);
    }
}
